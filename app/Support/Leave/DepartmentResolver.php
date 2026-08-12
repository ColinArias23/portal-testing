<?php

namespace App\Support\Leave;

use App\Models\Department;
use App\Models\Employee;

class DepartmentResolver
{
    private static array $cache = [];

    public static function forEmployee(Employee $employee): ?Department
    {
        $department = $employee->primaryDepartmentAssignment?->department;

        if ($department) {
            return $department;
        }

        $fallbackId = $employee->department_ids[0] ?? null;

        if (! $fallbackId) {
            return null;
        }

        if (! array_key_exists($fallbackId, self::$cache)) {
            self::$cache[$fallbackId] = Department::with(['division', 'parentDivision'])
                ->find($fallbackId);
        }

        return self::$cache[$fallbackId];
    }
}