<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;

class EmployeePolicy
{
    public function viewSignature(User $user, Employee $employee): bool
    {
        if ($user->employee_id !== null && $user->employee_id === $employee->id) {
            return true;
        }

        if ($user->hasAnyRole(['SuperAdmin', 'HR'])) {
            return true;
        }

        if ($user->can('leave.request.manage') || $user->can('leave.approval.manage')) {
            return true;
        }

        return $this->sharesVisibleLeaveRequest($user, $employee);
    }

    private function sharesVisibleLeaveRequest(User $user, Employee $employee): bool
    {
        $employeeUserId = $employee->user?->id;

        return LeaveRequest::query()
            ->visibleTo($user)
            ->where(function ($query) use ($employee, $employeeUserId) {
                $query->where('employee_id', $employee->id);

                if ($employeeUserId) {
                    $query->orWhereHas('approvalSteps', function ($stepQuery) use ($employeeUserId) {
                        $stepQuery->where('approver_id', $employeeUserId)
                            ->orWhere('acted_by', $employeeUserId);
                    });
                }
            })
            ->exists();
    }
}
