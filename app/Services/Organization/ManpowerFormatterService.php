<?php

namespace App\Services\Organization;

use App\Repositories\Shared\EmployeeRepository;
use App\Repositories\Organization\ManpowerMappingRepository;
use App\Traits\Concerns\ResolvesAvatarUrl;
use Illuminate\Support\Collection;

class ManpowerFormatterService
{
    use ResolvesAvatarUrl;

    private const HEAD_ROLES = ['CHIEF', 'DIRECTOR', 'ASSISTANT DIRECTOR', 'OFFICER IN CHARGE', 'CHAIRMAN', 'HEAD', 'SUPERVISOR'];

    public function __construct(
        private readonly EmployeeRepository $employeeRepository,
        private readonly ManpowerMappingRepository $manpower,
    ) {
    }

    public function formatDivision($division): array
    {
        return [
            'id'          => $division->id,
            'name'        => $division->name,
            'departments' => $division->departments
                ->map(fn ($d) => $this->formatDepartment($d))
                ->values(),
        ];
    }

    public function formatDepartment($dept, $positions = null): array
    {
        $positions ??= $this->manpower->unfilledPositionsForDepartment($dept->id);

        $assignments = $dept->assignedEmployees ?? [];

        $leadership = [];
        $staff      = [];

        foreach ($assignments as $assignment) {
            $emp = $assignment->employee;
            if (! $emp) {
                continue;
            }

            $section   = $this->getEmployeeSection($emp->role_position);
            $formatted = $this->formatEmployee($emp, $assignment);

            if ($section === 'leadership') {
                $leadership[] = $formatted;
            } else {
                $staff[] = $formatted;
            }
        }

        usort($leadership, fn ($a, $b) => $this->getRoleOrder($a['primary_role']) <=> $this->getRoleOrder($b['primary_role']));

        return [
            'id'              => $dept->id,
            'name'            => $dept->name,
            'type'            => $dept->type,
            'division_id'     => $dept->division_id,
            'head'            => $dept->head ? [
                'id'        => $dept->head->id,
                'full_name' => $this->buildFullName($dept->head),
            ] : null,
            'leadership'      => $leadership,
            'staff'           => $staff,
            'staff_count'     => count($staff),
            'vacant_count'    => $positions->where('status', 'UNFILLED')->count(),
            'total_headcount' => count($leadership) + count($staff),
        ];
    }

    public function formatEmployee($emp, $assignment = null): array
    {
        return [
            'id'                => $emp->id,
            'full_name'         => $this->buildFullName($emp),
            'first_name'        => $emp->first_name,
            'last_name'         => $emp->last_name,
            'role_position'     => is_array($emp->role_position)
                ? $emp->role_position
                : ($emp->role_position ? [$emp->role_position] : []),
            'primary_role'      => $this->getPrimaryRole($emp->role_position),
            'employment_type'   => $emp->employment_type,
            'employment_status' => $emp->employment_status ?? 'ACTIVE',
            'avatar_url'        => $this->resolveAvatarUrl($emp->getRawOriginal('avatar_url') ?? $emp->avatar_url),
            'is_primary_head'   => $assignment?->is_primary ?? false,
        ];
    }

    public function formatVacantPositions($positions): array
    {
        return $positions
            ->where('status', 'UNFILLED')
            ->map(fn ($pos) => [
                'id'             => $pos->id,
                'item_number'    => $pos->base_item_number,
                'position_title' => $pos->position_title ?? $pos->title,
                'status'         => 'UNFILLED',
                'salary_grade'   => $pos->salaryGrade?->salary_grade,
            ])
            ->values()
            ->toArray();
    }

    public function formatDepartmentNode($dept, array $departmentsByParent = [], array $employeesByDept = [], array $vacantByDept = []): array
    {
        $assignedEmployees = collect($employeesByDept[$dept->id] ?? []);

        $staff = $assignedEmployees->map(function ($emp) {
            return [
                'id'              => $emp->id,
                'employeeId'      => $emp->id,
                'first_name'      => $emp->first_name,
                'middle_name'     => $emp->middle_name,
                'last_name'       => $emp->last_name,
                'prefix'          => $emp->prefix,
                'suffix'          => $emp->suffix,
                'title'           => is_array($emp->title) ? ($emp->title ?? null) : $emp->title,
                'name'            => $this->buildFullName($emp),
                'full_name'       => $this->buildFullName($emp),
                'role'            => $this->getPrimaryRole($emp->role_position),
                'employmentType'  => $emp->employment_type,
                'employment_type' => $emp->employment_type,
                'image'           => $this->resolveAvatarUrl($emp->getRawOriginal('avatar_url') ?? $emp->avatar_url),
                'avatar_url'      => $this->resolveAvatarUrl($emp->getRawOriginal('avatar_url') ?? $emp->avatar_url),
            ];
        })->values()->toArray();

        $vacantSlots = collect($vacantByDept[$dept->id] ?? []);

        $head = $dept->head;

        $vacantHeadPosition = ! $head
            ? $vacantSlots->first(fn ($pos) => in_array(strtoupper($pos->role), self::HEAD_ROLES))
            : null;

        $remainingSlots = $vacantHeadPosition
            ? $vacantSlots->reject(fn ($pos) => $pos->id === $vacantHeadPosition->id)->values()
            : $vacantSlots;

        foreach ($remainingSlots as $pos) {
            $roleUpper = strtoupper($pos->role);
            $isStaff   = $roleUpper === 'STAFF';

            $vacantEntry = [
                'id'              => null,
                'employeeId'      => null,
                'first_name'      => null,
                'middle_name'     => null,
                'last_name'       => null,
                'name'            => 'Vacant — ' . ($pos->position_title ?? $pos->title ?? 'Position'),
                'full_name'       => 'Vacant — ' . ($pos->position_title ?? $pos->title ?? 'Position'),
                'role'            => $roleUpper,
                'employmentType'  => null,
                'employment_type' => null,
                'image'           => null,
                'avatar_url'      => null,
                'is_vacant_slot'  => true,
                'position_id'     => $pos->id,
            ];

            if ($isStaff) {
                $staff[] = $vacantEntry;
            }
        }

        $headData = $head ? [
            'id'             => $head->id,
            'employeeId'     => $head->id,
            'first_name'     => $head->first_name,
            'middle_name'    => $head->middle_name,
            'last_name'      => $head->last_name,
            'prefix'         => $head->prefix,
            'suffix'         => $head->suffix,
            'title'          => is_array($head->title) ? ($head->title ?? null) : $head->title,
            'name'           => $this->buildFullName($head),
            'full_name'      => $this->buildFullName($head),
            'role'           => $this->getPrimaryRole($head->role_position),
            'department'     => $dept->name,
            'employmentType' => $head->employment_type,
            'image'          => $this->resolveAvatarUrl($head->getRawOriginal('avatar_url') ?? $head->avatar_url),
            'avatar_url'     => $this->resolveAvatarUrl($head->getRawOriginal('avatar_url') ?? $head->avatar_url),
            'staff'          => $staff,
        ] : ($vacantHeadPosition ? [
            'id'             => null,
            'employeeId'     => null,
            'name'           => $vacantHeadPosition->position_title ?? $vacantHeadPosition->title ?? 'Vacant',
            'full_name'      => $vacantHeadPosition->position_title ?? $vacantHeadPosition->title ?? 'Vacant',
            'role'           => strtoupper($vacantHeadPosition->role),
            'department'     => $dept->name,
            'is_vacant_head' => true,
            'position_id'    => $vacantHeadPosition->id,
            'staff'          => $staff,
        ] : [
            'id'         => null,
            'employeeId' => null,
            'name'       => 'Vacant',
            'full_name'  => 'Vacant',
            'role'       => 'HEAD',
            'department' => $dept->name,
            'staff'      => $staff,
        ]);

        $vacantChildren = $remainingSlots
            ->filter(fn ($pos) => strtoupper($pos->role) !== 'STAFF')
            ->map(fn ($pos) => [
                'key'      => 'vacant-pos-' . $pos->id,
                'type'     => 'vacant_position',
                'deptId'   => $dept->id,
                'deptName' => $dept->name,
                'deptType' => $dept->type,
                'deptCode' => $dept->code,
                'division' => $dept->division?->name,
                'data'     => [
                    'id'             => null,
                    'employeeId'     => null,
                    'name'           => 'Vacant — ' . ($pos->position_title ?? $pos->title ?? 'Position'),
                    'full_name'      => 'Vacant — ' . ($pos->position_title ?? $pos->title ?? 'Position'),
                    'role'           => strtoupper($pos->role),
                    'department'     => $dept->name,
                    'staff'          => [],
                    'is_vacant_slot' => true,
                ],
                'children' => [],
            ])
            ->values()
            ->toArray();

        $children = collect($departmentsByParent[$dept->id] ?? [])
            ->map(fn ($child) => $this->formatDepartmentNode($child, $departmentsByParent, $employeesByDept, $vacantByDept))
            ->values()
            ->toArray();

        return [
            'key'      => 'dept-' . $dept->id,
            'type'     => 'department',
            'deptId'   => $dept->id,
            'deptName' => $dept->name,
            'deptType' => $dept->type,
            'deptCode' => $dept->code,
            'division' => $dept->division?->name,
            'data'     => $headData,
            'children' => array_merge($children, $vacantChildren),
        ];
    }

    public function formatDivisionNode(
        $division,
        $allDivisions,
        array $departmentsByDivision = [],
        array $departmentsByParentDivision = [],
        array $employeesByDept = [],
        array $vacantByDept = [],
        array $vacantByDivision = [],
        array $departmentsByParent = []
    ): array {
        $head = $division->head;

        $vacantSlots = collect($vacantByDivision[$division->id] ?? []);

        $vacantHeadPosition = ! $head
            ? $vacantSlots->first(fn ($pos) => in_array(strtoupper($pos->role), self::HEAD_ROLES))
            : null;

        $remainingSlots = $vacantHeadPosition
            ? $vacantSlots->reject(fn ($pos) => $pos->id === $vacantHeadPosition->id)->values()
            : $vacantSlots;

        $staff = [];
        foreach ($remainingSlots as $pos) {
            $roleUpper = strtoupper($pos->role);
            $isStaff   = $roleUpper === 'STAFF';

            $vacantEntry = [
                'id'              => null,
                'employeeId'      => null,
                'first_name'      => null,
                'middle_name'     => null,
                'last_name'       => null,
                'name'            => 'Vacant — ' . ($pos->position_title ?? $pos->title ?? 'Position'),
                'full_name'       => 'Vacant — ' . ($pos->position_title ?? $pos->title ?? 'Position'),
                'role'            => $roleUpper,
                'employmentType'  => null,
                'employment_type' => null,
                'image'           => null,
                'avatar_url'      => null,
                'is_vacant_slot'  => true,
                'position_id'     => $pos->id,
            ];

            if ($isStaff) {
                $staff[] = $vacantEntry;
            }
        }

        $headData = $head ? [
            'id'             => $head->id,
            'employeeId'     => $head->id,
            'first_name'     => $head->first_name,
            'middle_name'    => $head->middle_name,
            'last_name'      => $head->last_name,
            'prefix'         => $head->prefix,
            'suffix'         => $head->suffix,
            'title'          => is_array($head->title) ? ($head->title ?? null) : $head->title,
            'name'           => $this->buildFullName($head),
            'full_name'      => $this->buildFullName($head),
            'role'           => $this->getPrimaryRole($head->role_position),
            'department'     => $division->name,
            'employmentType' => $head->employment_type,
            'image'          => $this->resolveAvatarUrl($head->getRawOriginal('avatar_url') ?? $head->avatar_url),
            'avatar_url'     => $this->resolveAvatarUrl($head->getRawOriginal('avatar_url') ?? $head->avatar_url),
            'staff'          => $staff,
        ] : ($vacantHeadPosition ? [
            'id'             => null,
            'employeeId'     => null,
            'name'           => $vacantHeadPosition->position_title ?? $vacantHeadPosition->title ?? 'Vacant',
            'full_name'      => $vacantHeadPosition->position_title ?? $vacantHeadPosition->title ?? 'Vacant',
            'role'           => strtoupper($vacantHeadPosition->role),
            'department'     => $division->name,
            'is_vacant_head' => true,
            'position_id'    => $vacantHeadPosition->id,
            'staff'          => $staff,
        ] : [
            'id'         => null,
            'employeeId' => null,
            'name'       => 'Vacant',
            'full_name'  => 'Vacant',
            'role'       => 'HEAD',
            'department' => $division->name,
            'staff'      => $staff,
        ]);

        $vacantChildren = $remainingSlots
            ->filter(fn ($pos) => strtoupper($pos->role) !== 'STAFF')
            ->map(fn ($pos) => [
                'key'      => 'vacant-pos-div-' . $pos->id,
                'type'     => 'vacant_position',
                'deptId'   => $division->id,
                'deptName' => $division->name,
                'deptType' => $division->type,
                'deptCode' => $division->code,
                'division' => null,
                'data'     => [
                    'id'             => null,
                    'employeeId'     => null,
                    'name'           => 'Vacant — ' . ($pos->position_title ?? $pos->title ?? 'Position'),
                    'full_name'      => 'Vacant — ' . ($pos->position_title ?? $pos->title ?? 'Position'),
                    'role'           => strtoupper($pos->role),
                    'department'     => $division->name,
                    'staff'          => [],
                    'is_vacant_slot' => true,
                ],
                'children' => [],
            ])
            ->values()
            ->toArray();

        $childDivisions = $allDivisions instanceof Collection
            ? $allDivisions->where('parent_id', $division->id)->values()
            : collect();

        $childDivisionNodes = $childDivisions
            ->map(fn ($child) => $this->formatDivisionNode(
                $child, $allDivisions,
                $departmentsByDivision, $departmentsByParentDivision,
                $employeesByDept, $vacantByDept, $vacantByDivision, $departmentsByParent
            ))
            ->toArray();

        $indirectDepts = collect($departmentsByDivision[$division->id] ?? []);
        $directDepts   = collect($departmentsByParentDivision[$division->id] ?? []);

        $depts = $indirectDepts->merge($directDepts)->unique('id')->values();

        $departmentNodes = $depts
            ->map(fn ($dept) => $this->formatDepartmentNode($dept, $departmentsByParent, $employeesByDept, $vacantByDept))
            ->values()
            ->toArray();

        return [
            'key'      => 'division-' . $division->id,
            'type'     => 'division',
            'deptId'   => $division->id,
            'deptName' => $division->name,
            'deptType' => $division->type,
            'deptCode' => $division->code,
            'division' => null,
            'data'     => $headData,
            'children' => array_merge($childDivisionNodes, $departmentNodes, $vacantChildren),
        ];
    }

    public function getEmployeeSection($rolePosition): string
    {
        $roles = is_array($rolePosition) ? $rolePosition : ($rolePosition ? [$rolePosition] : []);
        $rolesUpper = array_map(fn ($r) => strtoupper(trim((string) $r)), $roles);

        $leadershipRoles = [
            'CHIEF', 'DIRECTOR', 'ASSISTANT DIRECTOR', 'OFFICER IN CHARGE',
            'CHAIRMAN', 'HEAD', 'SUPERVISOR',
        ];

        foreach ($rolesUpper as $role) {
            if (in_array($role, $leadershipRoles)) {
                return 'leadership';
            }
        }

        return 'staff';
    }

    public function getPrimaryRole($rolePosition): string
    {
        $roles = is_array($rolePosition) ? $rolePosition : ($rolePosition ? [$rolePosition] : []);
        $rolesUpper = array_map(fn ($r) => strtoupper(trim((string) $r)), $roles);

        $hierarchy = [
            'CHIEF'              => 1,
            'DIRECTOR'           => 2,
            'ASSISTANT DIRECTOR' => 3,
            'OFFICER IN CHARGE'  => 4,
            'CHAIRMAN'           => 4,
            'HEAD'               => 5,
            'SUPERVISOR'         => 6,
        ];

        usort($rolesUpper, fn ($a, $b) => ($hierarchy[$a] ?? 99) <=> ($hierarchy[$b] ?? 99));

        return $rolesUpper[0] ?? 'STAFF';
    }

    public function getRoleOrder(string $role): int
    {
        $orders = [
            'CHIEF'              => 1,
            'DIRECTOR'           => 2,
            'ASSISTANT DIRECTOR' => 3,
            'OFFICER IN CHARGE'  => 4,
            'CHAIRMAN'           => 4,
            'HEAD'               => 5,
            'SUPERVISOR'         => 6,
        ];

        return $orders[strtoupper($role)] ?? 99;
    }

    public function buildFullName($employee): string
    {
        if (! $employee) {
            return 'Unknown';
        }

        $prefix = $employee->prefix ? rtrim($employee->prefix, '.') . '.' : '';
        $suffix = $employee->suffix ? ', ' . $employee->suffix : '';
        $middleName = ! empty($employee->middle_name) ? ' ' . $employee->middle_name : '';

        return trim("$prefix {$employee->first_name}{$middleName} {$employee->last_name}") . $suffix;
    }
}
