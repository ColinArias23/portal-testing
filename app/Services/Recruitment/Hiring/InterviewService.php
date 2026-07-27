<?php

namespace App\Services\Recruitment\Hiring;

use App\Models\ActivityLog;
use App\Models\Applicant;
use App\Models\Interview;
use App\Repositories\Recruitment\Hiring\InterviewRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class InterviewService
{
    private const STATUS_FIELDS = ['hr_status', 'head_status', 'final_status', 'overall_status'];

    public function __construct(
        private readonly InterviewRepository $interviews,
    ) {
    }

    public function index(int $perPage): LengthAwarePaginator
    {
        return $this->interviews->paginate($perPage);
    }

    public function show(Applicant $applicant): Interview
    {
        return $this->interviews->findForApplicant($applicant->id);
    }

    public function update(Request $request, Applicant $applicant, array $data): Interview
    {
        foreach (self::STATUS_FIELDS as $field) {
            if (isset($data[$field])) {
                $data[$field] = strtoupper($data[$field]);
            }
        }

        $existing = $this->interviews->findExistingForApplicant($applicant->id);
        $before   = $this->snapshotStatuses($existing);

        $interview = $this->interviews->updateOrCreateForApplicant($applicant->id, $data);

        broadcast(new \App\Events\InterviewUpdated($interview->load('applicant')))->toOthers();

        ActivityLog::record(
            action: 'interview.updated',
            userId: $request->user()?->id,
            subjectType: Applicant::class,
            subjectId: $applicant->id,
            subjectLabel: $applicant->full_name,
            payload: [
                'before' => $before,
                'after'  => $this->snapshotStatuses($interview),
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return $interview;
    }

    private function snapshotStatuses(?Interview $interview): ?array
    {
        if (! $interview) {
            return null;
        }

        return collect(self::STATUS_FIELDS)
            ->mapWithKeys(fn ($field) => [$field => $interview->$field])
            ->toArray();
    }
}
