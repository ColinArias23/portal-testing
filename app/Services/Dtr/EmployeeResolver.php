<?php

namespace App\Services\Dtr;

use App\Models\Employee;
use App\Models\User;

class EmployeeResolver
{
    public function byNumber(string $number): ?Employee
    {
        $normalized = mb_strtoupper(trim($number));

        return $normalized === ''
            ? null
            : Employee::query()->whereRaw('UPPER(TRIM(employee_number)) = ?', [$normalized])->first();
    }

    public function forUser(User $user): ?Employee
    {
        return $user->employee_id ? Employee::query()->find($user->employee_id) : null;
    }
}
