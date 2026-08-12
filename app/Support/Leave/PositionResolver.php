<?php

namespace App\Support\Leave;

use App\Models\Employee;

class PositionResolver
{
    public static function forEmployee(Employee $employee): ?string
    {
        $employmentType = strtolower(trim($employee->employment_type ?? ''));

        return match ($employmentType) {
            'plantilla' => $employee->primaryAssignment?->plantillaPosition?->position_title,
            'contract of service', 'cos' => $employee->cosPosition?->title,
            'consultant' => $employee->consultantPosition?->title,
            default => null,
        };
    }
}