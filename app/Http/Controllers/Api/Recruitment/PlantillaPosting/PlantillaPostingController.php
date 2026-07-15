<?php

namespace App\Http\Controllers\Api\Recruitment\PlantillaPosting;

use App\Http\Controllers\Controller;
use App\Models\PlantillaPosition;
use App\Models\PlantillaPosting;
use App\Models\PlantillaPostingApplication;
use App\Models\PlantillaPostingApplicationDocument;
use App\Models\StepIncrement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlantillaPostingController extends Controller
{
    private const DOC_KEYS = [
        'resume', 'pds', 'transcript', 'diploma', 'eligibility', 'certificates', 'others'];
    private const REVIEW_STATUSES = [
        'Initial Review/Evaluation',
        'For Initial Deliberation',
        'Scheduled for Interview',
        'For HRMPSB Compliance',
        'For HRMPSB Deliberation',
        'Completed',
        'Rejected',
    ];

    public function __construct()
    {
        $this->middleware('auth:sanctum');

        $this->middleware('permission:hiring.plantilla.postings.view')->only(['index']);
        $this->middleware('permission:hiring.plantilla.postings.manage')->only(['store', 'update', 'destroy']);
        $this->middleware('permission:hiring.plantilla.applications.view')->only(['applications']);
        $this->middleware('permission:hiring.plantilla.applications.manage')->only(['reviewApplication']);
    }
// ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = PlantillaPosting::withCount('applications')
            ->with(['department', 'division', 'salaryGrade']);

        $this->applyFilters($query, $request);

        $perPage = (int) $request->get('per_page', 10);
        $postings = $query->latest('date_posted')->paginate($perPage);

        return response()->json($postings);
    }
// ─── available ─────────────────────────────────────────────────────
    public function available(Request $request)
    {
        $query = PlantillaPosting::adminOpen()
            ->withCount('applications')
            ->with(['department', 'division', 'salaryGrade']);

        $this->applyFilters($query, $request);

        $perPage = (int) $request->get('per_page', 10);
        $candidates = $query->latest('date_posted')->paginate($perPage);

        $filtered = $candidates->getCollection()
            ->filter(fn (PlantillaPosting $p) => $p->isAvailableForApplication())
            ->values();

        $employeeId = $request->user()?->employee_id ?? $request->user()?->employee?->id;

        $appliedPostingIds = $employeeId
            ? PlantillaPostingApplication::where('employee_id', $employeeId)
                ->whereIn('plantilla_posting_id', $filtered->pluck('id'))
                ->pluck('plantilla_posting_id')
                ->all()
            : [];

        $filtered->each(function (PlantillaPosting $posting) use ($appliedPostingIds) {
            $posting->already_applied = in_array($posting->id, $appliedPostingIds, true);
        });

        $candidates->setCollection($filtered);

        return response()->json($candidates);
    }
// ─── applyFilters ─────────────────────────────────────────────────────
    private function applyFilters($query, Request $request): void
    {
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('base_item_number', 'like', "%{$search}%");
            });
        }
        if ($departmentId = $request->get('department_id')) {
            $query->where('display_department_id', $departmentId);
        }
        if ($divisionId = $request->get('division_id')) {
            $query->where('display_division_id', $divisionId);
        }
        if ($salaryGradeId = $request->get('salary_grade_id')) {
            $query->where('salary_grade_id', $salaryGradeId);
        }
        if ($employmentStatus = $request->get('employment_status')) {
            $query->where('employment_status', $employmentStatus);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
    }
// ─── VacantItems ───────────────────────────────────────────────────────
    public function vacantItems()
    {
        $baseItemNumbers = PlantillaPosition::selectRaw('DISTINCT base_item_number')
            ->vacant()
            ->pluck('base_item_number');

        $items = $baseItemNumbers->map(function ($baseItemNumber) {
            $position = PlantillaPosition::where('base_item_number', $baseItemNumber)
                ->with([
                    'displayDepartment:id,name,code,head_employee_id',
                    'displayDepartment.head:id,first_name,last_name,prefix,suffix',
                    'displayDivision:id,name,code,head_employee_id',
                    'displayDivision.head:id,first_name,last_name,prefix,suffix',
                    'salaryGrade',
                    'stepIncrement',
                ])
                ->first();

            if (!$position) {
                return null;
            }

            $vacantCount = PlantillaPosition::where('base_item_number', $baseItemNumber)
                ->vacant()
                ->count();

            $steps = $position->salary_grade_id
                ? StepIncrement::where('salary_grade_id', $position->salary_grade_id)
                    ->orderBy('step')
                    ->get(['id', 'step', 'monthly_salary', 'annual_salary'])
                : collect();

            $defaultStep = $position->stepIncrement ?? $steps->first();

            $supervisor = $position->displayDepartment?->head
                ?? $position->displayDivision?->head;

            return [
                'base_item_number'      => $position->base_item_number,
                'title'                 => $position->title,
                'vacant_count'          => $vacantCount,

                'display_department_id' => $position->display_department_id,
                'display_department'    => $position->displayDepartment
                    ? $position->displayDepartment->only(['id', 'name', 'code'])
                    : null,
                'display_division_id'   => $position->display_division_id,
                'display_division'      => $position->displayDivision
                    ? $position->displayDivision->only(['id', 'name', 'code'])
                    : null,

                'salary_grade_id'       => $position->salary_grade_id,
                'salary_grade'          => $position->salaryGrade ? [
                    'id'           => $position->salaryGrade->id,
                    'salary_grade' => $position->salaryGrade->salary_grade,
                ] : null,

                'step_increment_id'     => $defaultStep?->id,
                'step_increments'       => $steps,

                'monthly_salary'        => $defaultStep?->monthly_salary,
                'annual_salary'         => $defaultStep?->annual_salary,

                'immediate_supervisor'  => $supervisor?->full_name,
            ];
        })->filter()->values();

        return response()->json($items);
    }
// ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $this->validatePosting($request);

        $vacantSlots = PlantillaPosition::where('base_item_number', $data['base_item_number'])
            ->where('status', 'VACANT')
            ->count();

        if ($vacantSlots < 1) {
            throw ValidationException::withMessages([
                'base_item_number' => 'Walang vacant slot ang item na ito ngayon.',
            ]);
        }

        if ($data['vacancies'] > $vacantSlots) {
            throw ValidationException::withMessages([
                'vacancies' => "Vacant slots lang ({$vacantSlots}) ang available para sa item na ito.",
            ]);
        }

        $hasActivePosting = PlantillaPosting::where('base_item_number', $data['base_item_number'])
            ->adminOpen()
            ->exists();

        if ($hasActivePosting) {
            throw ValidationException::withMessages([
                'base_item_number' => 'May aktibong posting pa ang item na ito. I-close muna bago gumawa ng bago.',
            ]);
        }

        $data['created_by'] = $request->user()?->id;

        $posting = PlantillaPosting::create($data);

        return response()->json($posting->fresh(['department', 'division', 'salaryGrade']), 201);
    }
// ─── Show ─────────────────────────────────────────────────────
    public function show($id)
    {
        $posting = PlantillaPosting::withCount('applications')
            ->with(['department', 'division', 'salaryGrade', 'stepIncrement'])
            ->findOrFail($id);

        return response()->json($posting);
    }
// ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $posting = PlantillaPosting::findOrFail($id);
        $data = $this->validatePosting($request, $posting->id);

        $posting->update($data);

        return response()->json($posting->fresh(['department', 'division', 'salaryGrade']));
    }
// ─── Destroy ─────────────────────────────────────────────────────
    public function destroy($id)
    {
        $posting = PlantillaPosting::findOrFail($id);
        $posting->delete();

        return response()->json(['message' => 'Posting deleted.']);
    }
// ─── VAlidatePosting ─────────────────────────────────────────────────────
    private function validatePosting(Request $request, $ignoreId = null): array
    {
        $validated = $request->validate([
            'base_item_number' => 'required|string|max:50',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'display_department_id' => 'nullable|exists:departments,id',
            'display_division_id' => 'nullable|exists:divisions,id',
            'section' => 'nullable|string|max:255',
            'salary_grade_id' => 'nullable|exists:salary_grades,id',
            'step_increment_id' => 'nullable|exists:step_increments,id',
            'monthly_salary' => 'nullable|numeric|min:0',
            'employment_status' => ['required', Rule::in(['Permanent'])],
            'vacancies' => 'required|integer|min:1|max:99',
            'immediate_supervisor' => 'nullable|string|max:255',
            'qualification_education' => 'nullable|string',
            'qualification_experience' => 'nullable|string',
            'qualification_training' => 'nullable|string',
            'qualification_eligibility' => 'nullable|string',
            'qualification_competency' => 'nullable|string',
            'job_description' => 'required|string',
            'required_documents' => 'required|array',
            'required_documents.*' => 'boolean',
            'date_posted' => 'required|date',
            'closing_date' => 'required|date|after_or_equal:date_posted',
            'expected_appointment_date' => 'nullable|date',
            'status' => ['required', Rule::in(['Open', 'Closing Soon', 'Closed'])],        ]);

        $validated['required_documents'] = collect(self::DOC_KEYS)
            ->mapWithKeys(fn ($key) => [$key => (bool) ($validated['required_documents'][$key] ?? false)])
            ->toArray();

        return $validated;
    }
// ─── Apply ─────────────────────────────────────────────────────
    public function apply(Request $request, $id)
    {
        $posting = PlantillaPosting::findOrFail($id);

        if (! $posting->isAvailableForApplication()) {
            throw ValidationException::withMessages([
                'posting' => 'Sarado na ang posting na ito o wala nang bakanteng slot.',
            ]);
        }

        $employeeId = $this->resolveEmployeeId($request);

        $alreadyApplied = PlantillaPostingApplication::where('plantilla_posting_id', $posting->id)
            ->where('employee_id', $employeeId)
            ->exists();

        if ($alreadyApplied) {
            throw ValidationException::withMessages([
                'posting' => 'Nag-apply ka na sa position na ito.',
            ]);
        }

        $rules = [
            'notes' => 'nullable|string|max:2000',
            'certified' => 'required|accepted',
        ];

        foreach (self::DOC_KEYS as $key) {
            $rules["documents.{$key}"] = [
                'nullable',
                'file',
                'mimes:pdf,doc,docx',
                'max:10240',
            ];
        }

        $validated = $request->validate($rules);

        $application = DB::transaction(function () use ($request, $posting, $employeeId, $validated) {
            $application = PlantillaPostingApplication::create([
                'plantilla_posting_id' => $posting->id,
                'employee_id' => $employeeId,
                'notes' => $validated['notes'] ?? null,
                'certified' => true,
                'status' => 'Pending',
                'submitted_at' => now(),
            ]);

            foreach (self::DOC_KEYS as $key) {
                $file = $request->file("documents.{$key}");
                if (! $file) {
                    continue;
                }

                $path = $file->store("plantilla-postings/{$posting->id}/{$application->id}", 'local');

                PlantillaPostingApplicationDocument::create([
                    'plantilla_posting_application_id' => $application->id,
                    'document_key' => $key,
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            return $application;
        });

        return response()->json($application->load('documents'), 201);
    }
// ─── ResolveEmployeeID ─────────────────────────────────────────────────────
    private function resolveEmployeeId(Request $request): int
    {
        $user = $request->user();

        $employeeId = $user->employee_id ?? $user->employee?->id ?? null;

        if (! $employeeId) {
            throw ValidationException::withMessages([
                'employee' => 'Walang naka-link na employee record ang account mo.',
            ]);
        }

        return $employeeId;
    }
// ─── MyApplications ─────────────────────────────────────────────────────
    public function myApplications(Request $request)
    {
        $user = $request->user();
        $employeeId = $user->employee_id ?? $user->employee?->id;
        if (! $employeeId) {
            return response()->json([]);
        }

        $applications = PlantillaPostingApplication::where('employee_id', $employeeId)
            ->with(['posting.department', 'posting.division', 'documents', 'interview'])
            ->latest('submitted_at')
            ->get();

        return response()->json($applications);
    }
// ─── UploadDocuments (submit missing docs / resubmit existing ones) ──────
    public function uploadDocuments(Request $request, PlantillaPostingApplication $application)
    {
        $employeeId = $this->resolveEmployeeId($request);

        if ($application->employee_id !== $employeeId) {
            abort(403, 'Hindi mo pag-aari ang application na ito.');
        }

        if (in_array($application->status, ['Completed', 'Rejected'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Sarado na ang application na ito — hindi na pwedeng magpasa o magpalit ng dokumento.',
            ]);
        }

        $rules = [];
        foreach (self::DOC_KEYS as $key) {
            $rules["documents.{$key}"] = [
                'nullable',
                'file',
                'mimes:pdf,doc,docx,jpg,jpeg,png',
                'max:10240',
            ];
        }

        $validated = $request->validate($rules);

        if (empty($validated['documents'] ?? [])) {
            throw ValidationException::withMessages([
                'documents' => 'Wala kang pinili na file na i-upload.',
            ]);
        }

        DB::transaction(function () use ($request, $application) {
            foreach (self::DOC_KEYS as $key) {
                $file = $request->file("documents.{$key}");
                if (! $file) {
                    continue;
                }

                // Resubmission: replace the existing document under the same key.
                $existing = PlantillaPostingApplicationDocument::where('plantilla_posting_application_id', $application->id)
                    ->where('document_key', $key)
                    ->first();

                if ($existing) {
                    Storage::disk('local')->delete($existing->stored_path);
                    $existing->delete();
                }

                $path = $file->store(
                    "plantilla-postings/{$application->plantilla_posting_id}/{$application->id}",
                    'local'
                );

                PlantillaPostingApplicationDocument::create([
                    'plantilla_posting_application_id' => $application->id,
                    'document_key' => $key,
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        });

        return response()->json(
            $application->fresh(['posting.department', 'posting.division', 'documents', 'interview'])
        );
    }
// ─── Applications ─────────────────────────────────────────────────────
    public function applications($postingId)
    {
        $posting = PlantillaPosting::findOrFail($postingId);

        $applications = $posting->applications()
            ->with(['employee', 'documents', 'interview'])
            ->latest('submitted_at')
            ->get();

        return response()->json($applications);
    }
// ─── AllApplications ─────────────────────────────────────────────────────
    public function allApplications(Request $request)
    {
        $query = PlantillaPostingApplication::with(['posting.department', 'employee', 'documents', 'interview'])
            ->latest('submitted_at');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($postingId = $request->get('plantilla_posting_id')) {
            $query->where('plantilla_posting_id', $postingId);
        }

        $user = $request->user();
        $canViewAll = $user->hasRole('SuperAdmin')
            || $user->can('hiring.plantilla.applications.manage');

        if (! $canViewAll) {
            $employeeId = $user->employee_id ?? $user->employee?->id;

            // No linked employee record → nothing to show, but don't 500/403.
            if (! $employeeId) {
                return response()->json([]);
            }

            $query->where('employee_id', $employeeId);
        }

        return response()->json($query->get());
    }
// ─── ReviewApplication ─────────────────────────────────────────────────────
    public function reviewApplication(Request $request, $applicationId)
    {
        $application = PlantillaPostingApplication::with(['posting.department', 'employee.info', 'employee.user', 'interview'])
            ->findOrFail($applicationId);

        $validated = $request->validate([
            'status' => ['required', Rule::in(self::REVIEW_STATUSES)],
            'remarks' => 'nullable|string|max:2000',
        ]);

        if ($validated['status'] === 'Completed' && ! $application->interview?->hasCompleted()) {
            throw ValidationException::withMessages([
                'status' => 'Hindi pa pwedeng i-mark as Completed ang application na ito. Kailangan munang i-schedule at i-mark as COMPLETED ang interview.',
            ]);
        }

        $application->update([
            'status' => $validated['status'],
            'remarks' => $validated['remarks'] ?? null,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        return response()->json(
            $application->fresh(['employee', 'documents', 'interview'])
        );
    }
// ─── DownloadDocument ─────────────────────────────────────────────────────
    public function downloadDocument(Request $request, $documentId)
    {
        $document = PlantillaPostingApplicationDocument::with('application')->findOrFail($documentId);

        $isOwner = $document->application->employee_id === ($request->user()->employee_id
            ?? $request->user()->employee?->id);

        $canManage = $request->user()->can('hiring.plantilla.applications.view');

        if (! $isOwner && ! $canManage) {
            abort(403);
        }

        return Storage::disk('local')->download(
            $document->stored_path,
            $document->original_filename
        );
    }
}