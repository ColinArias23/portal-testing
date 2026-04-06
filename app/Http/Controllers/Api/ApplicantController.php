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

    private function format(Applicant $applicant): array
    {
        return [
            'id'           => $applicant->id,
            'first_name'   => $applicant->first_name,
            'last_name'    => $applicant->last_name,
            'full_name'    => $applicant->full_name,
            'email'        => $applicant->email,
            'age'          => $applicant->age,
            'phone'        => $applicant->phone,
            'address'      => $applicant->address,
            'birthdate'    => $applicant->birthdate?->toDateString(),
            'source'       => $applicant->source,
            'position'     => $applicant->position,
            'department'   => $applicant->department,
            'date_applied' => $applicant->date_applied?->toDateString(),
            'submission'   => $applicant->submission,
            'status'       => $applicant->status,
            'remarks'      => $applicant->remarks,
            'documents'    => $applicant->documents,
            'interview'    => $applicant->interview,
            'contract'     => $applicant->contract,
            'onboarding'   => $applicant->onboarding,
            'created_at'   => $applicant->created_at,
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
                  ->orWhere('last_name',  'like', "%{$term}%")
                  ->orWhere('email',      'like', "%{$term}%")
                  ->orWhere('position',   'like', "%{$term}%");
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
            'data'       => $paginated->getCollection()->map(fn($a) => $this->format($a)),
            'pagination' => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name'   => ['required', 'string', 'max:100'],
            'last_name'    => ['required', 'string', 'max:100'],
            'email'        => ['nullable', 'email', 'max:100'],
            'age'          => ['nullable', 'string', 'max:5'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'address'      => ['nullable', 'string'],
            'birthdate'    => ['nullable', 'date'],
            'source'       => ['nullable', 'string', 'max:60'],
            'position'     => ['nullable', 'string', 'max:100'],
            'department'   => ['nullable', 'string', 'max:100'],
            'date_applied' => ['nullable', 'date'],
            'submission'   => ['nullable', 'string', 'max:100'],
            'status'       => ['nullable', 'string', 'max:60'],
            'remarks'      => ['nullable', 'string'],
            'documents'    => ['nullable', 'array'],
            'documents.*'  => ['string'],
        ]);

        $applicant = DB::transaction(function () use ($request, $data) {
            $applicant = Applicant::create(
                collect($data)->except('documents')->toArray()
            );

            Interview::create(['applicant_id' => $applicant->id]);

            foreach ($request->documents ?? [] as $docName) {
                $applicant->documents()->create([
                    'name'   => trim($docName),
                    'status' => 'Submitted',
                ]);
            }

            return $applicant->fresh()->load(['documents', 'interview', 'contract', 'onboarding']);
        });

        ActivityLog::record(
            action:       'applicant.created',
            userId:       $request->user()?->id,
            subjectType:  Applicant::class,
            subjectId:    $applicant->id,
            subjectLabel: $applicant->full_name,
            payload:      [
                'email'          => $applicant->email,
                'position'       => $applicant->position,
                'department'     => $applicant->department,
                'source'         => $applicant->source,
                'document_count' => $applicant->documents->count(),
            ],
            ip:        $request->ip(),
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
            'first_name'   => ['sometimes', 'string', 'max:100'],
            'last_name'    => ['sometimes', 'string', 'max:100'],
            'email'        => ['nullable', 'email', 'max:100'],
            'age'          => ['nullable', 'string', 'max:5'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'address'      => ['nullable', 'string'],
            'birthdate'    => ['nullable', 'date'],
            'source'       => ['nullable', 'string', 'max:60'],
            'position'     => ['nullable', 'string', 'max:100'],
            'department'   => ['nullable', 'string', 'max:100'],
            'date_applied' => ['nullable', 'date'],
            'submission'   => ['nullable', 'string', 'max:100'],
            'status'       => ['nullable', 'string', 'max:60'],
            'remarks'      => ['nullable', 'string'],
        ]);

        $before = [
            'status'     => $applicant->status,
            'position'   => $applicant->position,
            'department' => $applicant->department,
            'remarks'    => $applicant->remarks,
        ];

        $applicant->update($data);

        ActivityLog::record(
            action:       'applicant.updated',
            userId:       $request->user()?->id,
            subjectType:  Applicant::class,
            subjectId:    $applicant->id,
            subjectLabel: $applicant->full_name,
            payload:      [
                'before' => $before,
                'after'  => [
                    'status'     => $applicant->status,
                    'position'   => $applicant->position,
                    'department' => $applicant->department,
                    'remarks'    => $applicant->remarks,
                ],
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        $applicant->load(['documents', 'interview', 'contract', 'onboarding']);
        return response()->json($this->format($applicant));
    }

    public function destroy(Request $request, Applicant $applicant)
    {
        $id    = $applicant->id;
        $label = $applicant->full_name;

        $applicant->delete();

        ActivityLog::record(
            action:       'applicant.deleted',
            userId:       $request->user()?->id,
            subjectType:  Applicant::class,
            subjectId:    $id,
            subjectLabel: $label,
            payload:      [
                'position'   => $applicant->position,
                'department' => $applicant->department,
                'status'     => $applicant->status,
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json(['message' => 'Applicant deleted successfully.']);
    }

    public function summary()
    {
        return response()->json([
            'total'         => Applicant::count(),
            'for_interview' => Applicant::where('status', 'For Interview')->count(),
            'hired'         => Applicant::where('status', 'Hired')->count(),
            'rejected'      => Applicant::where('status', 'Rejected')->count(),
            'no_show'       => Applicant::where('status', 'No Show')->count(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | MOVE TO ONBOARDING — POST /hiring/applicants/{applicant}/move-to-onboarding
    |--------------------------------------------------------------------------
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
                'name'         => $applicant->full_name,
                'position'     => $applicant->position,
                'department'   => $applicant->department,
                'status'       => 'Pending',
            ]);

            foreach (self::DEFAULT_TASKS as $index => $title) {
                OnboardingTask::create([
                    'onboarding_id' => $onboarding->id,
                    'title'         => $title,
                    'completed'     => false,
                    'sort_order'    => $index,
                ]);
            }

            // Create draft contract if none exists
            if (!Contract::where('applicant_id', $applicant->id)->exists()) {
                Contract::create([
                    'applicant_id'  => $applicant->id,
                    'employee_name' => $applicant->full_name,
                    'position'      => $applicant->position,
                    'status'        => 'Draft',
                ]);
            }

            ActivityLog::record(
                action:       'applicant.moved_to_onboarding',
                userId:       $request->user()?->id,
                subjectType:  Applicant::class,
                subjectId:    $applicant->id,
                subjectLabel: $applicant->full_name,
                payload:      [
                    'position'       => $applicant->position,
                    'department'     => $applicant->department,
                    'onboarding_id'  => $onboarding->id,
                    'contract_created' => !Contract::where('applicant_id', $applicant->id)->exists(),
                ],
                ip:        $request->ip(),
                userAgent: $request->userAgent(),
            );
        });

        $applicant->load(['documents', 'interview', 'contract', 'onboarding']);

        return response()->json([
            'message'   => "{$applicant->full_name} has been moved to onboarding.",
            'applicant' => $this->format($applicant),
        ]);
    }
}