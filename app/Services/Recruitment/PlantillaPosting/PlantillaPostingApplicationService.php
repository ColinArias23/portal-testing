<?php

namespace App\Services\Recruitment\PlantillaPosting;

use App\Models\ActivityLog;
use App\Models\PlantillaPosting;
use App\Models\PlantillaPostingApplication;
use App\Models\PlantillaPostingApplicationDocument;
use App\Repositories\Recruitment\PlantillaPosting\PlantillaPostingApplicationRepository;
use App\Repositories\Recruitment\PlantillaPosting\PlantillaPostingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlantillaPostingApplicationService
{
    private const DOC_KEYS = ['resume', 'pds', 'transcript', 'diploma', 'eligibility', 'certificates', 'others'];

    private const REVIEW_STATUSES = [
        'Initial Review/Evaluation',
        'For Initial Deliberation',
        'Scheduled for Interview',
        'For HRMPSB Compliance',
        'For HRMPSB Deliberation',
        'Completed',
        'Rejected',
    ];

    private const TERMINAL_STATUSES = [
        'Completed',
        'Rejected',
        PlantillaPostingApplication::STATUS_POSTING_CANCELLED,
    ];

    public function __construct(
        private readonly PlantillaPostingApplicationRepository $applications,
        private readonly PlantillaPostingRepository $postings,
    ) {
    }

    public function resolveEmployeeId(Request $request): int
    {
        $user = $request->user();
        $employeeId = $user->employee_id ?? $user->employee?->id ?? null;

        if (! $employeeId) {
            throw ValidationException::withMessages([
                'employee' => 'Your account is not linked to an employee record.',
            ]);
        }

        return $employeeId;
    }

    public function apply(Request $request, int $postingId, array $validated): PlantillaPostingApplication
    {
        $posting = $this->postings->find($postingId);

        if (! $posting->isAvailableForApplication()) {
            throw ValidationException::withMessages([
                'posting' => 'This posting is closed or has no more open slots.',
            ]);
        }

        $employeeId = $this->resolveEmployeeId($request);

        if ($this->applications->existsFor($posting->id, $employeeId)) {
            throw ValidationException::withMessages([
                'posting' => 'Nag-apply ka na sa position na ito.',
            ]);
        }

        $application = DB::transaction(function () use ($request, $posting, $employeeId, $validated) {
            $application = $this->applications->create([
                'plantilla_posting_id' => $posting->id,
                'employee_id'          => $employeeId,
                'notes'                => $validated['notes'] ?? null,
                'certified'            => true,
                'status'               => 'Pending',
                'submitted_at'         => now(),
            ]);

            foreach (self::DOC_KEYS as $key) {
                $file = $request->file("documents.{$key}");
                if (! $file) {
                    continue;
                }

                $path = $file->store("plantilla-postings/{$posting->id}/{$application->id}", 'local');

                $this->applications->createDocument([
                    'plantilla_posting_application_id' => $application->id,
                    'document_key'       => $key,
                    'original_filename'  => $file->getClientOriginalName(),
                    'stored_path'        => $path,
                    'mime_type'          => $file->getClientMimeType(),
                    'size'               => $file->getSize(),
                ]);
            }

            return $application;
        });

        return $application->load('documents');
    }

    public function applyValidationRules(): array
    {
        $rules = [
            'notes'     => 'nullable|string|max:2000',
            'certified' => 'required|accepted',
        ];

        foreach (self::DOC_KEYS as $key) {
            $rules["documents.{$key}"] = ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'];
        }

        return $rules;
    }

    public function uploadDocumentsRules(): array
    {
        $rules = [];
        foreach (self::DOC_KEYS as $key) {
            $rules["documents.{$key}"] = ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'];
        }

        return $rules;
    }

    public function myApplications(Request $request): Collection
    {
        $user = $request->user();
        $employeeId = $user->employee_id ?? $user->employee?->id;

        if (! $employeeId) {
            return collect();
        }

        return $this->applications->forEmployee($employeeId);
    }

    public function uploadDocuments(Request $request, PlantillaPostingApplication $application, array $validated): PlantillaPostingApplication
    {
        $employeeId = $this->resolveEmployeeId($request);

        if ($application->employee_id !== $employeeId) {
            abort(403, 'You do not own this application.');
        }

        if (in_array($application->status, ['Completed', 'Rejected'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This application is closed — documents can no longer be submitted or changed.',
            ]);
        }

        if ($application->status === PlantillaPostingApplication::STATUS_POSTING_CANCELLED) {
            throw ValidationException::withMessages([
                'status' => 'This application was closed because the posting was cancelled/deleted — documents can no longer be submitted or changed.',
            ]);
        }

        if (empty($validated['documents'] ?? [])) {
            throw ValidationException::withMessages([
                'documents' => 'You have not selected any file to upload.',
            ]);
        }

        DB::transaction(function () use ($request, $application) {
            foreach (self::DOC_KEYS as $key) {
                $file = $request->file("documents.{$key}");
                if (! $file) {
                    continue;
                }

                $existing = $this->applications->findExistingDocument($application->id, $key);

                if ($existing) {
                    Storage::disk('local')->delete($existing->stored_path);
                    $existing->delete();
                }

                $path = $file->store(
                    "plantilla-postings/{$application->plantilla_posting_id}/{$application->id}",
                    'local'
                );

                $this->applications->createDocument([
                    'plantilla_posting_application_id' => $application->id,
                    'document_key'       => $key,
                    'original_filename'  => $file->getClientOriginalName(),
                    'stored_path'        => $path,
                    'mime_type'          => $file->getClientMimeType(),
                    'size'               => $file->getSize(),
                ]);
            }
        });

        return $this->applications->loadApplicationRelations($application);
    }

    public function applications(int $postingId): Collection
    {
        $posting = $this->postings->find($postingId);

        return $this->applications->forPosting($posting);
    }

    public function allApplications(Request $request): Collection
    {
        $query = $this->applications->newAllQuery();

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        } else {
            $query->where('status', '!=', PlantillaPostingApplication::STATUS_POSTING_CANCELLED);
        }

        if ($postingId = $request->get('plantilla_posting_id')) {
            $query->where('plantilla_posting_id', $postingId);
        }

        $user = $request->user();
        $canViewAll = $user->hasRole('SuperAdmin') || $user->can('hiring.plantilla.applications.manage');

        if (! $canViewAll) {
            $employeeId = $user->employee_id ?? $user->employee?->id;

            if (! $employeeId) {
                return collect();
            }

            $query->where('employee_id', $employeeId);
        }

        return $query->get();
    }

    public function reviewValidationRules(): array
    {
        return [
            'status'  => ['required', Rule::in(self::REVIEW_STATUSES)],
            'remarks' => 'nullable|string|max:2000',
        ];
    }

    public function reviewApplication(Request $request, int $applicationId, array $validated): PlantillaPostingApplication
    {
        $application = $this->applications->findWithReviewRelations($applicationId);

        if ($application->status === PlantillaPostingApplication::STATUS_POSTING_CANCELLED) {
            throw ValidationException::withMessages([
                'status' => 'This application was closed because the associated posting was cancelled/deleted. No further review action can be taken.',
            ]);
        }

        if ($validated['status'] === 'Completed' && ! $application->interview?->hasCompleted()) {
            throw ValidationException::withMessages([
                'status' => 'This application cannot be marked as Completed yet. The interview must first be scheduled and marked as COMPLETED.',
            ]);
        }

        return $this->applications->updateReview($application, [
            'status'      => $validated['status'],
            'remarks'     => $validated['remarks'] ?? null,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);
    }

    public function cancelApplicationsForPosting(Request $request, PlantillaPosting $posting, ?int $cancelledByUserId): Collection
    {
        $remarks = sprintf(
            'Automatically closed: the plantilla posting "%s" (Item No. %s) was cancelled/deleted. This is not a rejection — no evaluation of the applicant\'s qualifications was made.',
            $posting->title,
            $posting->position_slot_name,
        );

        $previousStatuses = PlantillaPostingApplication::where('plantilla_posting_id', $posting->id)
            ->whereNotIn('status', self::TERMINAL_STATUSES)
            ->pluck('status', 'id');

        $cancelledApplications = $this->applications->cancelForPosting(
            $posting->id,
            self::TERMINAL_STATUSES,
            $remarks,
            $cancelledByUserId,
        );

        $cancelledApplications->each(function (PlantillaPostingApplication $application) use (
            $request,
            $posting,
            $cancelledByUserId,
            $previousStatuses,
        ) {
            ActivityLog::record(
                action: 'plantilla_application.posting_cancelled',
                userId: $cancelledByUserId,
                subjectType: PlantillaPostingApplication::class,
                subjectId: $application->id,
                subjectLabel: optional($application->employee)->full_name ?? "Application #{$application->id}",
                payload: [
                    'plantilla_posting_id' => $posting->id,
                    'posting_title'        => $posting->title,
                    'position_slot_name'   => $posting->position_slot_name,
                    'previous_status'      => $previousStatuses->get($application->id),
                    'new_status'           => PlantillaPostingApplication::STATUS_POSTING_CANCELLED,
                    'reason'               => 'plantilla_posting_deleted',
                ],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        });

        return $cancelledApplications;
    }

    public function downloadDocument(Request $request, int $documentId): \Symfony\Component\HttpFoundation\Response
    {
        $document = $this->applications->findDocumentWithApplication($documentId);

        $isOwner = $document->application->employee_id === (
            $request->user()->employee_id ?? $request->user()->employee?->id
        );

        $canManage = $request->user()->can('hiring.plantilla.applications.view');

        if (! $isOwner && ! $canManage) {
            abort(403);
        }

        if (! Storage::disk('local')->exists($document->stored_path)) {
            abort(404, 'File not found. It may have been deleted.');
        }

        return Storage::disk('local')->download($document->stored_path, $document->original_filename);
    }
}