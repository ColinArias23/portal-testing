<?php

namespace App\Services\Leave;

use App\Enums\ApprovalStepStatus;
use App\Enums\ApproverSource;
use App\Enums\LeaveRequestStatus;
use App\Events\Leave\LeaveRequestSubmitted;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestLog;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\Leave\LeaveApprovalNotifier;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LeaveRequestService
{
    public function __construct(
        private readonly LeaveApproverResolver $approverResolver,
        private readonly LeaveBalanceService $balanceService,
        private readonly LeaveApprovalNotifier $notifier,
    ) {
    }

    public function submit(array $data, User $submittedBy): LeaveRequest
    {
        return DB::transaction(function () use ($data, $submittedBy) {
            [$employee, $leaveType, $totalDays, $flowSteps] = $this->validateForSubmission($data);

            $leaveRequest = LeaveRequest::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'status' => LeaveRequestStatus::Pending,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'is_half_day' => $data['is_half_day'] ?? false,
                'total_days' => $totalDays,
                'reason' => $data['reason'] ?? null,
                'details' => $data['details'] ?? [],
                'is_confidential' => $leaveType->is_confidential,
                'current_step_order' => 1,
                'total_steps' => $flowSteps->count(),
                'submitted_at' => now(),
                'created_by' => $submittedBy->id,
            ]);

            $this->attachApprovalFlow($leaveRequest, $flowSteps, $employee);

            LeaveRequestLog::create([
                'leave_request_id' => $leaveRequest->id,
                'action' => 'submitted',
                'actor_id' => $submittedBy->id,
                'metadata' => ['total_days' => $totalDays],
            ]);

            $fresh = $leaveRequest->fresh(['approvalSteps']);

            $currentStep = $fresh->approvalSteps->firstWhere('step_order', $fresh->current_step_order);
            if ($currentStep) {
                $this->notifier->notifyActionableStep(
                    $currentStep,
                    'New leave request to review',
                    "{$employee->full_name} filed a {$leaveType->name} request awaiting your approval.",
                );
            }

            event(new LeaveRequestSubmitted($fresh));

            return $leaveRequest->fresh(['leaveType', 'employee', 'approvalSteps.approver', 'documents']);
        });
    }

    public function saveDraft(array $data, User $actor): LeaveRequest
    {
        return DB::transaction(function () use ($data, $actor) {
            $employee = Employee::findOrFail($data['employee_id']);
            $leaveType = LeaveType::findOrFail($data['leave_type_id']);

            $leaveRequest = LeaveRequest::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'status' => LeaveRequestStatus::Draft,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'is_half_day' => $data['is_half_day'] ?? false,
                'reason' => $data['reason'] ?? null,
                'details' => $data['details'] ?? [],
                'is_confidential' => $leaveType->is_confidential,
                'current_step_order' => null,
                'total_steps' => 0,
                'created_by' => $actor->id,
            ]);

            LeaveRequestLog::create([
                'leave_request_id' => $leaveRequest->id,
                'action' => 'draft_saved',
                'actor_id' => $actor->id,
            ]);

            return $leaveRequest->fresh(['leaveType', 'employee']);
        });
    }

    public function updateDraft(LeaveRequest $leaveRequest, array $data, User $actor): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $data, $actor) {
            $leaveRequest = LeaveRequest::query()
                ->whereKey($leaveRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($leaveRequest->status !== LeaveRequestStatus::Draft) {
                throw new RuntimeException('Only a request still in Draft can be edited this way.');
            }

            $leaveType = isset($data['leave_type_id'])
                ? LeaveType::findOrFail($data['leave_type_id'])
                : $leaveRequest->leaveType;

            $leaveRequest->update([
                'leave_type_id' => $leaveType->id,
                'start_date' => array_key_exists('start_date', $data) ? $data['start_date'] : $leaveRequest->start_date,
                'end_date' => array_key_exists('end_date', $data) ? $data['end_date'] : $leaveRequest->end_date,
                'is_half_day' => $data['is_half_day'] ?? $leaveRequest->is_half_day,
                'reason' => array_key_exists('reason', $data) ? $data['reason'] : $leaveRequest->reason,
                'details' => $data['details'] ?? $leaveRequest->details,
                'is_confidential' => $leaveType->is_confidential,
                'updated_by' => $actor->id,
            ]);

            return $leaveRequest->fresh(['leaveType', 'employee']);
        });
    }

    public function deleteDraft(LeaveRequest $leaveRequest, User $actor): void
    {
        DB::transaction(function () use ($leaveRequest, $actor) {
            $leaveRequest = LeaveRequest::query()
                ->whereKey($leaveRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($leaveRequest->status !== LeaveRequestStatus::Draft) {
                throw new RuntimeException('Only a request still in Draft can be deleted.');
            }

            LeaveRequestLog::create([
                'leave_request_id' => $leaveRequest->id,
                'action' => 'draft_deleted',
                'actor_id' => $actor->id,
            ]);

            $leaveRequest->delete();
        });
    }

    public function submitDraft(LeaveRequest $leaveRequest, array $data, User $submittedBy): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $data, $submittedBy) {
            $leaveRequest = LeaveRequest::query()
                ->whereKey($leaveRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($leaveRequest->status !== LeaveRequestStatus::Draft) {
                throw new RuntimeException('Only a request still in Draft can be submitted.');
            }

            $merged = array_merge(
                $leaveRequest->only(['leave_type_id', 'start_date', 'end_date', 'is_half_day', 'reason', 'details']),
                array_filter($data, fn ($value) => $value !== null),
            );
            $merged['employee_id'] = $leaveRequest->employee_id;

            [$employee, $leaveType, $totalDays, $flowSteps] = $this->validateForSubmission($merged);

            $leaveRequest->update([
                'leave_type_id' => $leaveType->id,
                'start_date' => $merged['start_date'] ?? null,
                'end_date' => $merged['end_date'] ?? null,
                'is_half_day' => $merged['is_half_day'] ?? false,
                'total_days' => $totalDays,
                'reason' => $merged['reason'] ?? null,
                'details' => $merged['details'] ?? [],
                'is_confidential' => $leaveType->is_confidential,
                'status' => LeaveRequestStatus::Pending,
                'current_step_order' => 1,
                'total_steps' => $flowSteps->count(),
                'submitted_at' => now(),
                'updated_by' => $submittedBy->id,
            ]);

            $this->attachApprovalFlow($leaveRequest, $flowSteps, $employee);

            LeaveRequestLog::create([
                'leave_request_id' => $leaveRequest->id,
                'action' => 'submitted',
                'actor_id' => $submittedBy->id,
                'metadata' => ['total_days' => $totalDays, 'from_draft' => true],
            ]);

            $fresh = $leaveRequest->fresh(['approvalSteps']);

            $currentStep = $fresh->approvalSteps->firstWhere('step_order', $fresh->current_step_order);
            if ($currentStep) {
                $this->notifier->notifyActionableStep(
                    $currentStep,
                    'New leave request to review',
                    "{$employee->full_name} filed a {$leaveType->name} request awaiting your approval.",
                );
            }

            event(new LeaveRequestSubmitted($fresh));

            return $leaveRequest->fresh(['leaveType', 'employee', 'approvalSteps.approver', 'documents']);
        });
    }

    private function validateForSubmission(array $data): array
    {
        $employee = Employee::findOrFail($data['employee_id']);
        $leaveType = LeaveType::findOrFail($data['leave_type_id']);

        $totalDays = $this->computeDays($leaveType, $data);

        $this->assertNoOverlap($employee, $data);

        if ($leaveType->max_days && $totalDays && $totalDays > $leaveType->max_days) {
            throw new RuntimeException("{$leaveType->name} cannot exceed {$leaveType->max_days} days.");
        }

        if ($leaveType->requires_balance_check && $totalDays) {
            $year = isset($data['start_date']) ? \Carbon\Carbon::parse($data['start_date'])->year : now()->year;
            $available = $this->balanceService->available($employee, $leaveType, $year);
            if ($available < $totalDays) {
                throw new RuntimeException(
                    "Insufficient {$leaveType->name} balance. Available: "
                    . number_format($available, 2) . ' day(s), requested: ' . number_format($totalDays, 2) . '.'
                );
            }
        }

        if ($leaveType->code === 'monetization') {
            $credits = (float) ($data['details']['creditsToMonetize'] ?? 0);
            if ($credits <= 0) {
                throw new RuntimeException('creditsToMonetize must be a positive number.');
            }
            $available = $this->balanceService->available($employee, $leaveType, now()->year);
            if ($available < $credits) {
                throw new RuntimeException(
                    "Insufficient balance to monetize. Available: " . number_format($available, 2)
                    . ' day(s), requested: ' . number_format($credits, 2) . '.'
                );
            }
        }

        $flowSteps = $leaveType->approvalFlowSteps;

        if ($employee->employment_type !== 'Plantilla') {
            $flowSteps = $flowSteps
                ->where('approver_source', ApproverSource::ManagementChain)
                ->values();
        }

        if ($flowSteps->isEmpty()) {
            throw new RuntimeException("No approval workflow is configured for '{$leaveType->name}'. Contact HR/system admin.");
        }

        return [$employee, $leaveType, $totalDays, $flowSteps];
    }

    private function attachApprovalFlow(LeaveRequest $leaveRequest, $flowSteps, Employee $employee): void
    {
        foreach ($flowSteps as $flowStep) {
            $approver = $this->approverResolver->resolve($flowStep, $employee);

            $label = $flowStep->approver_source === ApproverSource::ManagementChain
                ? $this->approverResolver->describeManagementChainLabel($employee)
                : $flowStep->label;

            $leaveRequest->approvalSteps()->create([
                'flow_step_id' => $flowStep->id,
                'step_order' => $flowStep->step_order,
                'approver_source' => $flowStep->approver_source,
                'label' => $label,
                'approver_id' => $approver?->id,
                'approver_role' => $flowStep->approver_role,
                'status' => ApprovalStepStatus::Pending,
            ]);
        }

        $this->initializeCurrentStep($leaveRequest);
    }

    private function initializeCurrentStep(LeaveRequest $leaveRequest): void
    {
        $steps = $leaveRequest->approvalSteps()->orderBy('step_order')->get();

        $firstActionable = $steps->first(
            fn ($step) => $step->approver_id !== null || $step->approver_role !== null
        );

        if (! $firstActionable) {
            throw new RuntimeException(
                'No valid approver could be resolved for any step in this leave request\'s approval chain. Contact HR/system admin.'
            );
        }

        $stepsToSkip = $steps->where('step_order', '<', $firstActionable->step_order);

        foreach ($stepsToSkip as $skippedStep) {
            $skippedStep->update([
                'status' => ApprovalStepStatus::Skipped,
                'remarks' => 'Auto-skipped: no approver could be resolved for this step (e.g. the applicant is the assigned approver).',
                'acted_at' => now(),
            ]);

            LeaveRequestLog::create([
                'leave_request_id' => $leaveRequest->id,
                'action' => 'step_skipped',
                'actor_id' => null,
                'metadata' => ['step_order' => $skippedStep->step_order],
            ]);
        }

        if ($firstActionable->step_order !== $leaveRequest->current_step_order) {
            $leaveRequest->update(['current_step_order' => $firstActionable->step_order]);
        }
    }

    public function reschedule(
        LeaveRequest $leaveRequest,
        array $newDates,
        string $reason,
        User $actor,
    ): LeaveRequest {
        return DB::transaction(function () use ($leaveRequest, $newDates, $reason, $actor) {
            if ($leaveRequest->status->isFinal()) {
                throw new RuntimeException('This request has already been finalized and cannot be rescheduled.');
            }

            $this->cancel($leaveRequest, $actor, "Rescheduled: {$reason}");

            $newRequest = $this->submit([
                'employee_id' => $leaveRequest->employee_id,
                'leave_type_id' => $leaveRequest->leave_type_id,
                'start_date' => $newDates['start_date'] ?? null,
                'end_date' => $newDates['end_date'] ?? null,
                'is_half_day' => $leaveRequest->is_half_day,
                'reason' => $leaveRequest->reason,
                'details' => $leaveRequest->details ?? [],
            ], $actor);

            $newRequest->update(['rescheduled_from_id' => $leaveRequest->id]);

            LeaveRequestLog::create([
                'leave_request_id' => $leaveRequest->id,
                'action' => 'rescheduled',
                'actor_id' => $actor->id,
                'remarks' => $reason,
                'metadata' => ['new_leave_request_id' => $newRequest->id],
            ]);

            return $newRequest->fresh(['leaveType', 'employee', 'approvalSteps.approver', 'documents']);
        });
    }

    public function cancel(LeaveRequest $leaveRequest, User $actor, string $reason): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $actor, $reason) {
            $cancellableFrom = [LeaveRequestStatus::Pending, LeaveRequestStatus::ReturnedForRevision];

            if (! in_array($leaveRequest->status, $cancellableFrom, true)) {
                throw new RuntimeException(
                    $leaveRequest->status === LeaveRequestStatus::Approved
                        ? 'This request has already been approved. Use retract instead.'
                        : 'This request has already been finalized and cannot be cancelled.'
                );
            }

            $leaveRequest->update([
                'status' => LeaveRequestStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'updated_by' => $actor->id,
            ]);

            LeaveRequestLog::create([
                'leave_request_id' => $leaveRequest->id,
                'action' => 'cancelled',
                'actor_id' => $actor->id,
                'remarks' => $reason,
            ]);

            return $leaveRequest->fresh();
        });
    }

    public function retract(LeaveRequest $leaveRequest, User $actor, string $reason): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $actor, $reason) {
            $leaveRequest = LeaveRequest::query()
                ->whereKey($leaveRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($leaveRequest->status !== LeaveRequestStatus::Approved) {
                throw new RuntimeException('Only a fully approved leave request can be retracted.');
            }

            $leaveRequest->update([
                'status' => LeaveRequestStatus::Retracted,
                'retracted_at' => now(),
                'retraction_reason' => $reason,
                'updated_by' => $actor->id,
            ]);

            $this->balanceService->restore($leaveRequest, "Restored due to retraction of {$leaveRequest->request_number}");

            LeaveRequestLog::create([
                'leave_request_id' => $leaveRequest->id,
                'action' => 'retracted',
                'actor_id' => $actor->id,
                'remarks' => $reason,
            ]);

            return $leaveRequest->fresh();
        });
    }

    public function resubmit(LeaveRequest $leaveRequest, User $actor): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $actor) {
            $leaveRequest = LeaveRequest::query()
                ->whereKey($leaveRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($leaveRequest->status !== LeaveRequestStatus::ReturnedForRevision) {
                throw new RuntimeException('Only a request returned for revision can be resubmitted.');
            }

            if ($leaveRequest->employee_id !== $actor->employee_id && ! $actor->hasRole('SuperAdmin')) {
                throw new RuntimeException('Only the filing employee can resubmit this request.');
            }

            $leaveRequest->approvalSteps()
                ->where('step_order', $leaveRequest->current_step_order)
                ->update([
                    'status' => ApprovalStepStatus::Pending,
                    'remarks' => null,
                    'acted_at' => null,
                    'acted_by' => null,
                ]);

            $leaveRequest->update([
                'status' => LeaveRequestStatus::Pending,
                'submitted_at' => now(),
            ]);

            LeaveRequestLog::create([
                'leave_request_id' => $leaveRequest->id,
                'action' => 'resubmitted',
                'actor_id' => $actor->id,
            ]);

            $fresh = $leaveRequest->fresh(['approvalSteps']);
            $currentStep = $fresh->approvalSteps->firstWhere('step_order', $fresh->current_step_order);

            if ($currentStep) {
                $this->notifier->notifyActionableStep(
                    $currentStep,
                    'Revised leave request ready for review',
                    "{$leaveRequest->employee->full_name} resubmitted a {$leaveRequest->leaveType->name} request.",
                );
            }

            return $leaveRequest->fresh(['leaveType', 'employee', 'approvalSteps.approver', 'documents']);
        });
    }

    private function assertNoOverlap(Employee $employee, array $data): void
    {
        if (empty($data['start_date']) || empty($data['end_date'])) {
            return;
        }

        $overlapExists = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [
                LeaveRequestStatus::Pending,
                LeaveRequestStatus::ReturnedForRevision,
                LeaveRequestStatus::Approved,
            ])
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->where('start_date', '<=', $data['end_date'])
            ->where('end_date', '>=', $data['start_date'])
            ->exists();

        if ($overlapExists) {
            throw new RuntimeException(
                'You already have a pending or approved leave request that overlaps these dates.'
            );
        }
    }

    private function computeDays(LeaveType $leaveType, array $data): ?float
    {
        if (! $leaveType->requires_date_range) {
            return null;
        }

        if (empty($data['start_date']) || empty($data['end_date'])) {
            throw new RuntimeException('Start date and end date are required for this leave type.');
        }

        $start = \Carbon\Carbon::parse($data['start_date'])->startOfDay();
        $end = \Carbon\Carbon::parse($data['end_date'])->startOfDay();

        if ($end->lt($start)) {
            throw new RuntimeException('End date cannot be before start date.');
        }

        $days = $start->diffInDays($end) + 1;

        return ! empty($data['is_half_day']) ? $days - 0.5 : (float) $days;
    }
}