<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Applicant;
use App\Models\Contract;
use App\Models\Interview;
use App\Models\Onboarding;
use App\Models\OnboardingTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApplicantController extends Controller
{
    private const DEFAULT_TASKS = [
        'Submit government IDs',
        'Sign employment contract',
        'Complete HR orientation',
        'Set up company email',
        'Meet the team',
        'Complete mandatory trainings',
    ];

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function format(Applicant $applicant): array
    {
        return [
            'id' => $applicant->id,
            'first_name' => $applicant->first_name,
            'last_name' => $applicant->last_name,
            'full_name' => $applicant->full_name,
            'email' => $applicant->email,
            'age' => $applicant->age,
            'phone' => $applicant->phone,
            'address' => $applicant->address,
            'birthdate' => $applicant->birthdate?->toDateString(),
            'source' => $applicant->source,
            'position' => $applicant->position,
            'department' => $applicant->department,
            'date_applied' => $applicant->date_applied?->toDateString(),
            'submission' => $applicant->submission,
            'status' => $applicant->status,
            'remarks' => $applicant->remarks,
            'documents' => $applicant->documents,
            'interview' => $applicant->interview,
            'contract' => $applicant->contract,
            'onboarding' => $applicant->onboarding,
            'created_at' => $applicant->created_at,
        ];
    }

    public function index(Request $request)
    {
        $query = Applicant::with(['documents', 'interview', 'contract', 'onboarding'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('position', 'like', "%{$term}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        $paginated = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => $paginated->getCollection()->map(fn($a) => $this->format($a)),
            'pagination' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:100'],
            'age' => ['nullable', 'string', 'max:5'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'birthdate' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:60'],
            'position' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'date_applied' => ['nullable', 'date'],
            'submission' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:60'],
            'remarks' => ['nullable', 'string'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['string', 'max:255'],
        ]);

        $applicant = DB::transaction(function () use ($request, $data) {
            $applicant = Applicant::create(
                collect($data)->except('documents')->toArray()
            );

            Interview::create(['applicant_id' => $applicant->id]);

            foreach ($request->documents ?? [] as $docName) {
                $applicant->documents()->create([
                    'name' => trim($docName),
                    'status' => 'Submitted',
                ]);
            }

            return $applicant->fresh()->load(['documents', 'interview', 'contract', 'onboarding']);
        });

        ActivityLog::record(
            action: 'applicant.created',
            userId: $request->user()?->id,
            subjectType: Applicant::class,
            subjectId: $applicant->id,
            subjectLabel: $applicant->full_name,
            payload: [
                'username' => $applicant->username,
                'position' => $applicant->position,
                'department' => $applicant->department,
                'source' => $applicant->source,
                'document_count' => $applicant->documents->count(),
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json($this->format($applicant), 201);
    }

    public function show(Applicant $applicant)
    {
        $applicant->load(['documents', 'interview', 'contract', 'onboarding']);
        return response()->json($this->format($applicant));
    }

    public function update(Request $request, Applicant $applicant)
    {
        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:100'],
            'age' => ['nullable', 'string', 'max:5'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'birthdate' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:60'],
            'position' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'date_applied' => ['nullable', 'date'],
            'submission' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:60'],
            'remarks' => ['nullable', 'string'],
        ]);

        $before = [
            'status' => $applicant->status,
            'position' => $applicant->position,
            'department' => $applicant->department,
            'remarks' => $applicant->remarks,
        ];

        $applicant->update($data);

        ActivityLog::record(
            action: 'applicant.updated',
            userId: $request->user()?->id,
            subjectType: Applicant::class,
            subjectId: $applicant->id,
            subjectLabel: $applicant->full_name,
            payload: [
                'before' => $before,
                'after' => [
                    'status' => $applicant->status,
                    'position' => $applicant->position,
                    'department' => $applicant->department,
                    'remarks' => $applicant->remarks,
                ],
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        $applicant->load(['documents', 'interview', 'contract', 'onboarding']);
        return response()->json($this->format($applicant));
    }

    /**
     * IMPROVED: Soft delete instead of hard delete for audit trail
     */
    public function destroy(Request $request, Applicant $applicant)
    {
        $id = $applicant->id;
        $label = $applicant->full_name;
        $payload = [
            'position' => $applicant->position,
            'department' => $applicant->department,
            'status' => $applicant->status,
        ];

        $applicant->delete(); // Soft delete if model uses SoftDeletes trait

        ActivityLog::record(
            action: 'applicant.deleted',
            userId: $request->user()?->id,
            subjectType: Applicant::class,
            subjectId: $id,
            subjectLabel: $label,
            payload: $payload,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'message' => 'Applicant deleted successfully.',
        ]);
    }

    /**
     * Restore soft-deleted applicant
     */
    public function restore(Request $request, $id)
    {
        $applicant = Applicant::onlyTrashed()->find($id);

        if (!$applicant) {
            return response()->json([
                'message' => 'Applicant not found.',
            ], 404);
        }

        $applicant->restore();

        ActivityLog::record(
            action: 'applicant.restored',
            userId: $request->user()?->id,
            subjectType: Applicant::class,
            subjectId: $applicant->id,
            subjectLabel: $applicant->full_name,
            payload: [
                'position' => $applicant->position,
                'department' => $applicant->department,
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'message' => 'Applicant restored successfully.',
        ]);
    }

    public function summary()
    {
        return response()->json([
            'total' => Applicant::count(),
            'for_interview' => Applicant::where('status', 'FOR INTERVIEW')->count(),
            'hired' => Applicant::where('status', 'HIRED')->count(),
            'rejected' => Applicant::where('status', 'REJECTED')->count(),
            'no_show' => Applicant::where('status', 'NO SHOW')->count(),
        ]);
    }

    /**
     * MOVE TO ONBOARDING — POST /hiring/applicants/{applicant}/move-to-onboarding
     * FIXED: Contract creation logic now works correctly
     */
    public function moveToOnboarding(Request $request, Applicant $applicant)
    {
        // Prevent duplicate onboarding
        if (Onboarding::where('applicant_id', $applicant->id)->exists()) {
            return response()->json([
                'message' => 'This applicant already has an onboarding record.',
            ], 422);
        }

        DB::transaction(function () use ($request, $applicant) {
            // Mark as Hired if not yet
            if ($applicant->status !== 'Hired') {
                $applicant->update(['status' => 'Hired']);
            }

            // Create onboarding
            $onboarding = Onboarding::create([
                'applicant_id' => $applicant->id,
                'name' => $applicant->full_name,
                'position' => $applicant->position,
                'department' => $applicant->department,
                'status' => 'Pending',
            ]);

            // Create default onboarding tasks
            foreach (self::DEFAULT_TASKS as $index => $title) {
                OnboardingTask::create([
                    'onboarding_id' => $onboarding->id,
                    'title' => $title,
                    'completed' => false,
                    'sort_order' => $index,
                ]);
            }

            // FIXED: Check contract before creating, and store the result
            $contractAlreadyExists = Contract::where('applicant_id', $applicant->id)->exists();
            $contractCreated = false;

            if (!$contractAlreadyExists) {
                Contract::create([
                    'applicant_id' => $applicant->id,
                    'employee_name' => $applicant->full_name,
                    'position' => $applicant->position,
                    'status' => 'Draft',
                ]);
                $contractCreated = true;
            }

            ActivityLog::record(
                action: 'applicant.moved_to_onboarding',
                userId: $request->user()?->id,
                subjectType: Applicant::class,
                subjectId: $applicant->id,
                subjectLabel: $applicant->full_name,
                payload: [
                    'position' => $applicant->position,
                    'department' => $applicant->department,
                    'onboarding_id' => $onboarding->id,
                    'contract_created' => $contractCreated,
                ],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        });

        $applicant->load(['documents', 'interview', 'contract', 'onboarding']);

        return response()->json([
            'message' => "{$applicant->full_name} has been moved to onboarding.",
            'applicant' => $this->format($applicant),
        ]);
    }
}
