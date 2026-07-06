<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlantillaPosition;
use App\Models\PlantillaPosting;
use App\Models\PlantillaPostingApplication;
use App\Models\PlantillaPostingApplicationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlantillaPostingController extends Controller
{
    private const DOC_KEYS = [
        'resume', 'pds', 'transcript', 'diploma', 'eligibility', 'certificates', 'others',
    ];

    public function __construct()
    {
        $this->middleware('auth:sanctum');

        $this->middleware('role:SuperAdmin|HR')->only([
            'index',              
            'store',
            'update',
            'destroy',
            'applications',       
            'allApplications',    
            'reviewApplication',
        ]);
    }

    public function index(Request $request)
    {
        $query = PlantillaPosting::withCount('applications')
            ->with(['department', 'division', 'salaryGrade']);

        $this->applyFilters($query, $request);

        $perPage = (int) $request->get('per_page', 10);
        $postings = $query->latest('date_posted')->paginate($perPage);

        return response()->json($postings);
    }

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

    public function vacantItems()
    {
        $vacant = PlantillaPosition::where('status', 'VACANT')
            ->get()
            ->groupBy('base_item_number');

        $items = $vacant->map(function ($positions, $baseItemNumber) {
            $first = $positions->first();

            return [
                'base_item_number' => $baseItemNumber,
                'title' => $first->position_title ?: $first->title,
                'vacant_count' => $positions->count(),
                'salary_grade_id' => $first->salary_grade_id,
                'step_increment_id' => $first->step_increment_id,
                'display_department_id' => $first->display_department_id,
                'display_division_id' => null,
            ];
        })->values();

        return response()->json($items);
    }

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

    public function show($id)
    {
        $posting = PlantillaPosting::withCount('applications')
            ->with(['department', 'division', 'salaryGrade', 'stepIncrement'])
            ->findOrFail($id);

        return response()->json($posting);
    }

    public function update(Request $request, $id)
    {
        $posting = PlantillaPosting::findOrFail($id);
        $data = $this->validatePosting($request, $posting->id);

        $posting->update($data);

        return response()->json($posting->fresh(['department', 'division', 'salaryGrade']));
    }

    public function destroy($id)
    {
        $posting = PlantillaPosting::findOrFail($id);
        $posting->delete();

        return response()->json(['message' => 'Posting deleted.']);
    }

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

    public function myApplications(Request $request)
    {
        $employeeId = $this->resolveEmployeeId($request);

        $applications = PlantillaPostingApplication::where('employee_id', $employeeId)
            ->with(['posting.department', 'posting.division', 'documents', 'interview'])
            ->latest('submitted_at')
            ->get();

        return response()->json($applications);
    }

    public function applications($postingId)
    {
        $posting = PlantillaPosting::findOrFail($postingId);

        $applications = $posting->applications()
            ->with(['employee', 'documents', 'interview'])
            ->latest('submitted_at')
            ->get();

        return response()->json($applications);
    }

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

        return response()->json($query->get());
    }

    public function reviewApplication(Request $request, $applicationId)
    {
        $application = PlantillaPostingApplication::with(['posting.department', 'employee.info', 'employee.user', 'interview'])
            ->findOrFail($applicationId);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['Under Review', 'Approved', 'Rejected'])],
            'remarks' => 'nullable|string|max:2000',
        ]);

        if ($validated['status'] === 'Approved' && ! $application->interview?->hasCompleted()) {
            throw ValidationException::withMessages([
                'status' => 'Hindi pa pwedeng i-approve ang application na ito. Kailangan munang i-schedule at i-mark as COMPLETED ang interview.',
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

    public function downloadDocument(Request $request, $documentId)
    {
        $document = PlantillaPostingApplicationDocument::with('application')->findOrFail($documentId);

        $isOwner = $document->application->employee_id === ($request->user()->employee_id
            ?? $request->user()->employee?->id);

        $isHrOrAdmin = $request->user()->hasRole(['SuperAdmin', 'HR']);

        if (! $isOwner && ! $isHrOrAdmin) {
            abort(403);
        }

        return Storage::disk('local')->download(
            $document->stored_path,
            $document->original_filename
        );
    }
}