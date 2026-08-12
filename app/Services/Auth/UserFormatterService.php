<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Repositories\Shared\EmployeeRepository;
class UserFormatterService
{
    public function __construct(
        private readonly EmployeeRepository $employees,
    ) {
    }

    public function format(User $user): array
    {
        $employee = $user->employee;
        $division = $employee?->division;
        $primaryDepartment = $employee?->primaryDepartmentAssignment?->department;

        $departments = [];
        if ($employee && !empty($employee->department_ids)) {
            $departments = $this->employees->findDepartmentsByIds($employee->department_ids);
        }

        $avatarUrl = $this->resolveAvatarUrl(
            $employee?->getRawOriginal('avatar_url')
        );

        $roles = $user->getRoleNames();
        $permissions = $user->getAllPermissions()->pluck('name')->values();

        $positionLabel = $employee?->position_title;

        $departmentLabel = $primaryDepartment;
        if (!$departmentLabel && !empty($departments)) {
            $departmentLabel = (object) $departments[0];
        }

        return [
            'id'              => $user->id,
            'username'        => $user->username,
            'email'           => $user->email,
            'is_active'       => $user->approval_status === 'APPROVED',
            'approval_status' => $user->approval_status,
            'has_completed_orientation' => (bool) $user->has_completed_orientation,
            'first_name'      => $employee?->first_name ?? $user->given_name ?? null,
            'last_name'       => $employee?->last_name  ?? $user->last_name  ?? null,
            'roles'           => $roles,
            'permissions'     => $permissions,
            'employee'        => $employee ? [
                'id'              => $employee->id,
                'employee_number' => $employee->employee_number,
                'full_name'       => trim(
                    ($employee->first_name ?? '') . ' ' .
                    ($employee->middle_name ?? '') . ' ' .
                    ($employee->last_name ?? '')
                ),
                'first_name'            => $employee->first_name,
                'middle_name'           => $employee->middle_name,
                'last_name'             => $employee->last_name,
                'employment_status'     => $employee->employment_status ?? null,
                'employment_type'       => $employee->employment_type ?? null,
                'contact_number'        => $employee->info?->mobile_no ?? null,
                'position'              => $positionLabel,
                'position_designation'  => $employee->position_designation ?? [],
                'role_position'         => $employee->role_position ?? [],
                'department'            => $departmentLabel ? [
                    'id'   => $departmentLabel->id,
                    'name' => $departmentLabel->name,
                ] : null,
            ] : null,
            'avatar_url'      => $avatarUrl,
            'departments'     => $departments,
            'department_ids'  => $employee?->department_ids ?? [],
            'division'        => $division?->name,
            'position'        => $positionLabel,
            'employee_number' => $employee?->employee_number ?? null,
        ];
    }

    private function resolveAvatarUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $baseDir = realpath(storage_path('app/public'));
        $filePath = realpath(storage_path('app/public/' . $path));

        if (!$baseDir || !$filePath || !str_starts_with($filePath, $baseDir . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return rtrim(config('app.url'), '/') . '/storage/' . $path;
    }
}
