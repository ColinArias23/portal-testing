<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class LeaveBalancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('leave.balance.view');
    }

    public function view(User $user, Employee $employee): bool
    {
        if ($employee->id === $user->employee_id) {
            return true;
        }

        return $user->can('leave.balance.view');
    }

    public function adjust(User $user): bool
    {
        return $user->can('leave.balance.manage');
    }
}
