<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        $isOwner = $leaveRequest->employee_id === $user->employee_id;

        if ($leaveRequest->is_confidential && ! $isOwner && ! $this->canSeeConfidential($user, $leaveRequest)) {
            return false;
        }

        if ($isOwner) {
            return true;
        }

        if ($user->can('leave.request.view') || $user->can('leave.request.manage')) {
            return true;
        }

        $currentStep = $leaveRequest->currentStep();
        if ($currentStep && $currentStep->approver_role && $user->hasRole($currentStep->approver_role)) {
            return true;
        }

        return $leaveRequest->approvalSteps->contains('approver_id', $user->id);
    }

    public function create(User $user, int $targetEmployeeId): bool
    {
        if ($user->employee_id === $targetEmployeeId) {
            return true;
        }

        return $user->can('leave.request.manage');
    }

    public function cancel(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($leaveRequest->employee_id === $user->employee_id) {
            return true;
        }

        return $user->can('leave.request.manage');
    }

    public function retract(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($leaveRequest->employee_id === $user->employee_id) {
            return true;
        }

        return $user->can('leave.request.manage');
    }

    public function resubmit(User $user, LeaveRequest $leaveRequest): bool
    {
        return $leaveRequest->employee_id === $user->employee_id || $user->hasRole('SuperAdmin');
    }

    public function act(User $user, LeaveRequest $leaveRequest): bool
    {
        if (! $user->can('leave.approval.manage')) {
            return false;
        }

        if ($leaveRequest->is_confidential && ! $this->canSeeConfidential($user, $leaveRequest)) {
            return false;
        }

        $currentStep = $leaveRequest->currentStep();

        $isAssigned = $currentStep && $currentStep->approver_id === $user->id;
        $isRoleMatch = $currentStep && $currentStep->approver_role && $user->hasRole($currentStep->approver_role);

        return $isAssigned || $isRoleMatch || $user->hasRole('SuperAdmin');
    }

    public function canSeeConfidential(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->can('leave.request.manage')
            || $leaveRequest->employee_id === $user->employee_id
            || $leaveRequest->approvalSteps->contains('approver_id', $user->id);
    }
}