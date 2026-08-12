<?php

namespace App\Services\Leave;

use App\Enums\ApproverSource;
use App\Models\Employee;
use App\Models\LeaveApprovalFlowStep;
use App\Models\User;
use RuntimeException;

class LeaveApproverResolver
{
    public function resolve(LeaveApprovalFlowStep $flowStep, Employee $employee): ?User
    {
        return match ($flowStep->approver_source) {
            ApproverSource::ImmediateSupervisor => $this->resolveImmediateSupervisor($employee, $flowStep),
            ApproverSource::DepartmentHead => $this->resolveDepartmentHead($employee, $flowStep),
            ApproverSource::Role => $this->resolveByRole($flowStep),
            ApproverSource::FixedUser => $this->resolveFixedUser($flowStep),
        };
    }

    private function resolveImmediateSupervisor(Employee $employee, LeaveApprovalFlowStep $flowStep): ?User
    {
        $supervisor = $employee->parents()->first();

        if (! $supervisor || ! $supervisor->user) {
            $this->failIfRequired($flowStep, 'Employee has no immediate supervisor with a linked user account.');
            return null;
        }

        return $supervisor->user;
    }

    private function resolveDepartmentHead(Employee $employee, LeaveApprovalFlowStep $flowStep): ?User
    {
        $head = $employee->primaryDepartmentAssignment?->effective_head;

        if (! $head) {
            $departmentId = $employee->department_ids[0] ?? null;
            if ($departmentId) {
                $head = \App\Models\Department::find($departmentId)?->head;
            }
        }

        if (! $head || ! $head->user) {
            $this->failIfRequired($flowStep, 'Employee\'s department has no designated head with a linked user account.');
            return null;
        }

        if ($employee->user && $head->user->id === $employee->user->id) {
            return null;
        }

        return $head->user;
    }

    private function resolveByRole(LeaveApprovalFlowStep $flowStep): ?User
    {
        if (! $flowStep->approver_role) {
            throw new RuntimeException("Flow step {$flowStep->id} is source=role but has no approver_role configured.");
        }

        $approver = User::role($flowStep->approver_role)->approved()->orderBy('id')->first();

        if (! $approver) {
            $this->failIfRequired($flowStep, "No active user holds the role '{$flowStep->approver_role}'.");
            return null;
        }

        return $approver;
    }

    private function resolveFixedUser(LeaveApprovalFlowStep $flowStep): ?User
    {
        if (! $flowStep->approver_user_id) {
            throw new RuntimeException("Flow step {$flowStep->id} is source=fixed_user but has no approver_user_id configured.");
        }

        return $flowStep->fixedApprover;
    }

    private function failIfRequired(LeaveApprovalFlowStep $flowStep, string $message): void
    {
        if ($flowStep->is_required) {
            throw new RuntimeException($message);
        }
    }
}