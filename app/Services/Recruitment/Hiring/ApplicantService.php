<?php

namespace App\Services\Recruitment\Hiring;

use App\Models\ActivityLog;
use App\Models\Applicant;
use App\Repositories\Recruitment\Hiring\ApplicantRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ApplicantService
{
    private const DEFAULT_TASKS = [
        'Submit government IDs',
        'Sign employment contract',
        'Complete HR orientation',
        'Set up company email',
        'Meet the team',
        'Complete mandatory trainings',
    ];

    public function __construct(
        private readonly ApplicantRepository $applicants,
    ) {
    }

    public function format(Applicant $applicant): array
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

    public function index(Request $request): LengthAwarePaginator
    {
        $query = $this->applicants->newQuery();

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

        return $this->applicants->paginate($query, (int) ($request->per_page ?? 15));
    }

    public function store(Request $request, array $data): Applicant
    {
        $applicant = DB::transaction(function () use ($request, $data) {
            $applicant = $this->applicants->create(collect($data)->except('documents')->toArray());

            $this->applicants->createInterview($applicant->id);

            foreach ($request->documents ?? [] as $docName) {
                $this->applicants->addDocument($applicant, $docName);
            }

            return $this->applicants->loadRelations($applicant->fresh());
        });

        ActivityLog::record(
            action: 'applicant.created',
            userId: $request->user()?->id,
            subjectType: Applicant::class,
            subjectId: $applicant->id,
            subjectLabel: $applicant->full_name,
            payload: [
                'username'       => $applicant->username,
                'position'       => $applicant->position,
                'department'     => $applicant->department,
                'source'         => $applicant->source,
                'document_count' => $applicant->documents->count(),
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return $applicant;
    }

    public function show(Applicant $applicant): Applicant
    {
        return $this->applicants->loadRelations($applicant);
    }

    public function update(Request $request, Applicant $applicant, array $data): Applicant
    {
        $before = [
            'status'     => $applicant->status,
            'position'   => $applicant->position,
            'department' => $applicant->department,
            'remarks'    => $applicant->remarks,
        ];

        $this->applicants->update($applicant, $data);

        ActivityLog::record(
            action: 'applicant.updated',
            userId: $request->user()?->id,
            subjectType: Applicant::class,
            subjectId: $applicant->id,
            subjectLabel: $applicant->full_name,
            payload: [
                'before' => $before,
                'after'  => [
                    'status'     => $applicant->status,
                    'position'   => $applicant->position,
                    'department' => $applicant->department,
                    'remarks'    => $applicant->remarks,
                ],
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return $this->applicants->loadRelations($applicant);
    }

    public function destroy(Request $request, Applicant $applicant): void
    {
        $id      = $applicant->id;
        $label   = $applicant->full_name;
        $payload = [
            'position'   => $applicant->position,
            'department' => $applicant->department,
            'status'     => $applicant->status,
        ];

        $this->applicants->delete($applicant);

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
    }

    public function restore(Request $request, int $id): array
    {
        $applicant = $this->applicants->findTrashed($id);

        if (! $applicant) {
            return ['status' => 404, 'body' => ['message' => 'Applicant not found.']];
        }

        $applicant->restore();

        ActivityLog::record(
            action: 'applicant.restored',
            userId: $request->user()?->id,
            subjectType: Applicant::class,
            subjectId: $applicant->id,
            subjectLabel: $applicant->full_name,
            payload: [
                'position'   => $applicant->position,
                'department' => $applicant->department,
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return ['status' => 200, 'body' => ['message' => 'Applicant restored successfully.']];
    }

    public function summary(): array
    {
        return $this->applicants->summaryCounts();
    }

    public function moveToOnboarding(Request $request, Applicant $applicant): array
    {
        if ($this->applicants->onboardingExistsFor($applicant->id)) {
            return ['status' => 422, 'body' => ['message' => 'This applicant already has an onboarding record.']];
        }

        DB::transaction(function () use ($request, $applicant) {
            if ($applicant->status !== 'Hired') {
                $applicant->update(['status' => 'Hired']);
            }

            $onboarding = $this->applicants->createOnboarding([
                'applicant_id' => $applicant->id,
                'name'         => $applicant->full_name,
                'position'     => $applicant->position,
                'department'   => $applicant->department,
                'status'       => 'Pending',
            ]);

            $this->applicants->createOnboardingTasks($onboarding->id, self::DEFAULT_TASKS);

            $contractAlreadyExists = $this->applicants->contractExistsFor($applicant->id);
            $contractCreated       = false;

            if (! $contractAlreadyExists) {
                $this->applicants->createContract([
                    'applicant_id'  => $applicant->id,
                    'employee_name' => $applicant->full_name,
                    'position'      => $applicant->position,
                    'status'        => 'Draft',
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
                    'position'         => $applicant->position,
                    'department'       => $applicant->department,
                    'onboarding_id'    => $onboarding->id,
                    'contract_created' => $contractCreated,
                ],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        });

        $this->applicants->loadRelations($applicant);

        return [
            'status' => 200,
            'body' => [
                'message'   => "{$applicant->full_name} has been moved to onboarding.",
                'applicant' => $this->format($applicant),
            ],
        ];
    }
}
