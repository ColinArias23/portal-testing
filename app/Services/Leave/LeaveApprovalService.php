<?php

namespace App\Services\Leave;

use App\Enums\ApprovalStepStatus;
use App\Enums\LeaveRequestStatus;
use App\Events\Leave\LeaveRequestFinalized;
use App\Events\Leave\LeaveRequestStepActioned;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestLog;
use App\Models\User;
use App\Support\Leave\LeaveApprovalNotifier;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LeaveApprovalService
{
    public function __construct(
        private readonly LeaveBalanceService $balanceService,
        private readonly LeaveApprovalNotifier $notifier,
    ) {
    }

    public function approveStep(LeaveRequest $leaveRequest, User $actor, ?string $remarks): LeaveRequest
    {
        return $this->actOnStep($leaveRequest, $actor, ApprovalStepStatus::Approved, $remarks);
    }

    public function rejectStep(LeaveRequest $leaveRequest, User $actor, string $remarks): LeaveRequest
    {
        if (trim($remarks) === '') {
            throw new RuntimeException('Remarks are required when rejecting a leave request.');
        }

        return $this->actOnStep($leaveRequest, $actor, ApprovalStepStatus::Rejected, $remarks);
    }

    public function returnForRevision(LeaveRequest $leaveRequest, User $actor, string $remarks): LeaveRequest
    {
        if (trim($remarks) === '') {
            throw new RuntimeException('Remarks are required when returning a leave request for revision.');
        }

        return DB::transaction(function () use ($leaveRequest, $actor, $remarks) {
            $leaveRequest = LeaveRequest::query()
                ->whereKey($leaveRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($leaveRequest->status !== LeaveRequestStatus::Pending) {
                throw new RuntimeException('This request is no longer pending and cannot be returned.');
            }

            $step = $leaveRequest->approvalSteps()
                ->where('step_order', $leaveRequest->current_step_order)
                ->where('status', ApprovalStepStatus::Pending)
                ->lockForUpdate()
                ->first();

            if (! $step) {
                throw new RuntimeException('No pending approval step found for this request.');
            }

            $isAssignedApprover = $step->approver_id === $actor->id;
            $isRoleMatch = $step->approver_role && $actor->hasRole($step->approver_role);

            if (! $isAssignedApprover && ! $isRoleMatch && ! $actor->hasRole('SuperAdmin')) {
                throw new RuntimeException('You are not the assigned approver for this step.');
            }

            $step->update(['remarks' => $remarks]);

            $leaveRequest->update([
                'status' => LeaveRequestStatus::ReturnedForRevision,
                'returned_at' => now(),
                'return_reason' => $remarks,
            ]);

            LeaveRequestLog::create([
                'leave_request_id' => $leaveRequest->id,
                'action' => 'returned_for_revision',
                'actor_id' => $actor->id,
                'remarks' => $remarks,
                'metadata' => ['step_order' => $step->step_order],
            ]);

            $leaveRequest = $leaveRequest->fresh(['leaveType', 'employee', 'approvalSteps.approver']);

            $employeeUserId = $leaveRequest->employee->user?->id;
            if ($employeeUserId) {
                $this->notifier->notifyUser(
                    $employeeUserId,
                    'leave',
                    'Leave request returned for revision',
                    "Your {$leaveRequest->leaveType->name} request ({$leaveRequest->request_number}) was returned for revision: {$remarks}",
                    ['leave_request_id' => $leaveRequest->id, 'status' => LeaveRequestStatus::ReturnedForRevision->value],
                );
            }

            return $leaveRequest;
        });
    }

    public function bulkApprove(array $leaveRequestIds, User $actor): array
    {
        $approved = [];
        $skipped = [];

        foreach ($leaveRequestIds as $id) {
            try {
                $leaveRequest = LeaveRequest::findOrFail($id);
                $this->approveStep($leaveRequest, $actor, null);
                $approved[] = $id;
            } catch (\Throwable $e) {
                $skipped[$id] = $e->getMessage();
            }
        }

        return ['approved' => $approved, 'skipped' => $skipped];
    }

    private function actOnStep(LeaveRequest $leaveRequest, User $actor, ApprovalStepStatus $decision, ?string $remarks): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $actor, $decision, $remarks) {
            $leaveRequest = LeaveRequest::query()
                ->whereKey($leaveRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($leaveRequest->status !== LeaveRequestStatus::Pending) {
                throw new RuntimeException('This request is no longer pending and cannot be acted on.');
            }

            $step = $leaveRequest->approvalSteps()
                ->where('step_order', $leaveRequest->current_step_order)
                ->where('status', ApprovalStepStatus::Pending)
                ->lockForUpdate()
                ->first();

            if (! $step) {
                throw new RuntimeException('No pending approval step found for this request.');
            }

            $isAssignedApprover = $step->approver_id === $actor->id;
            $isRoleMatch = $step->approver_role && $actor->hasRole($step->approver_role);

            if (! $isAssignedApprover && ! $isRoleMatch && ! $actor->hasRole('SuperAdmin')) {
                throw new RuntimeException('You are not the assigned approver for this step.');
            }

            $step->update([
                'status' => $decision,
                'remarks' => $remarks,
                'acted_at' => now(),
                'acted_by' => $actor->id,
            ]);

            LeaveRequestLog::create([
                'leave_request_id' => $leaveRequest->id,
                'action' => $decision === ApprovalStepStatus::Approved ? 'step_approved' : 'step_rejected',
                'actor_id' => $actor->id,
                'remarks' => $remarks,
                'metadata' => ['step_order' => $step->step_order],
            ]);

            event(new LeaveRequestStepActioned($step->fresh()));

            if ($decision === ApprovalStepStatus::Rejected) {
                return $this->finalize($leaveRequest, LeaveRequestStatus::Rejected);
            }

            return $this->advanceToNextActionableStep($leaveRequest);
        });
    }

    private function advanceToNextActionableStep(LeaveRequest $leaveRequest): LeaveRequest
    {
        $remainingSteps = $leaveRequest->approvalSteps()
            ->where('step_order', '>', $leaveRequest->current_step_order)
            ->where('status', ApprovalStepStatus::Pending)
            ->orderBy('step_order')
            ->get();

        $nextActionableStep = $remainingSteps->first(
            fn ($step) => $step->approver_id !== null || $step->approver_role !== null
        );

        $stepsToSkip = $nextActionableStep
            ? $remainingSteps->where('step_order', '<', $nextActionableStep->step_order)
            : $remainingSteps;

        foreach ($stepsToSkip as $skippedStep) {
            $skippedStep->update([
                'status' => ApprovalStepStatus::Skipped,
                'remarks' => 'Auto-skipped: no approver could be resolved for this optional step.',
                'acted_at' => now(),
            ]);

            LeaveRequestLog::create([
                'leave_request_id' => $leaveRequest->id,
                'action' => 'step_skipped',
                'actor_id' => null,
                'metadata' => ['step_order' => $skippedStep->step_order],
            ]);
        }

        if (! $nextActionableStep) {
            return $this->finalize($leaveRequest, LeaveRequestStatus::Approved);
        }

        $leaveRequest->update(['current_step_order' => $nextActionableStep->step_order]);

        $this->notifier->notifyActionableStep(
            $nextActionableStep,
            'Leave request awaiting your approval',
            "{$leaveRequest->employee->full_name}'s {$leaveRequest->leaveType->name} request needs your review.",
        );

        return $leaveRequest->fresh(['approvalSteps.approver']);
    }

    private function assertFinalApprovalAuthority(LeaveRequest $leaveRequest): void
    {
        $leaveType = $leaveRequest->leaveType;

        if (in_array($leaveType->code, config('leave.exempt_from_final_approval_role', []), true)) {
            return;
        }

        $requiredRole = config('leave.final_approval_role');

        $terminalStep = $leaveRequest->approvalSteps()
            ->where('status', ApprovalStepStatus::Approved)
            ->orderByDesc('step_order')
            ->first();

        $clearedByRequiredRole = $terminalStep
            && $terminalStep->approver_role === $requiredRole;

        if (! $clearedByRequiredRole) {
            report(new RuntimeException(
                "Leave request #{$leaveRequest->id} ({$leaveType->code}) reached final approval "
                . "without a '{$requiredRole}' step in its chain. Check LeaveApprovalFlowSeeder / "
                . "leave_approval_flow_steps for this leave type, or add it to "
                . "config('leave.exempt_from_final_approval_role') if this is intentional."
            ));

            throw new RuntimeException(
                "This leave request cannot be finalized as Approved: its approval chain is "
                . "missing the required '{$requiredRole}' approval step. Please contact HR/system admin."
            );
        }
    }

    private function finalize(LeaveRequest $leaveRequest, LeaveRequestStatus $finalStatus): LeaveRequest
    {
        if ($finalStatus === LeaveRequestStatus::Approved) {
            $this->assertFinalApprovalAuthority($leaveRequest);
        }

        $leaveRequest->update([
            'status' => $finalStatus,
            'finalized_at' => now(),
        ]);

        if ($finalStatus === LeaveRequestStatus::Approved) {
            $this->balanceService->debitForApproval($leaveRequest);
        }

        LeaveRequestLog::create([
            'leave_request_id' => $leaveRequest->id,
            'action' => $finalStatus === LeaveRequestStatus::Approved ? 'finalized_approved' : 'finalized_rejected',
            'actor_id' => auth()->id(),
        ]);

        $leaveRequest = $leaveRequest->fresh(['leaveType', 'employee', 'approvalSteps.approver']);

        $employeeUserId = $leaveRequest->employee->user?->id;
        if ($employeeUserId) {
            $verb = $finalStatus === LeaveRequestStatus::Approved ? 'approved' : 'rejected';
            $this->notifier->notifyUser(
                $employeeUserId,
                'leave',
                "Leave request {$verb}",
                "Your {$leaveRequest->leaveType->name} request ({$leaveRequest->request_number}) has been {$verb}.",
                ['leave_request_id' => $leaveRequest->id, 'status' => $finalStatus->value],
            );
        }

        event(new LeaveRequestFinalized($leaveRequest));

        return $leaveRequest;
    }
}