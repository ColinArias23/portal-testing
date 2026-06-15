<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\EmployeeDepartmentAssignment;
use App\Models\PlantillaPosition;
use App\Traits\ResolvesAvatarUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManpowerMappingController extends Controller
{
    use ResolvesAvatarUrl;

    private const HEAD_ROLES = [
        'CHIEF', 'DIRECTOR', 'ASSISTANT DIRECTOR', 'OFFICER IN CHARGE',
        'CHAIRMAN', 'HEAD', 'SUPERVISOR',
    ];

    public function departments(Request $request): JsonResponse
    {
        $divisionId = $request->filled('division_id')
            ? (int)$request->division_id
            : null;

        $divisions = Division::when($divisionId, fn($q) => $q->where('id', $divisionId))
            ->with(['departments' => function ($q) {
                $q->with([
                    'head:id,first_name,last_name,prefix,suffix',
                    'assignedEmployees' => function ($q2) {
                        $q2->with([
                            'employee:id,first_name,middle_name,last_name,prefix,suffix,role_position,employment_type,employment_status,avatar_url',
                            'departmentHead:id,first_name,middle_name,last_name'
                        ])
                            ->whereNull('end_date')
                            ->orderBy('is_primary', 'desc');
                    }
                ]);
            }])
            ->get();

        return response()->json(
            $divisions->map(fn($div) => $this->formatDivision($div))->values()
        );
    }

    public function departmentDetail($departmentId): JsonResponse
    {
        $dept = Department::with([
            'division:id,name',
            'head:id,first_name,middle_name,last_name,prefix,suffix,employment_status',
            'assignedEmployees' => function ($q) {
                $q->with([
                    'employee:id,first_name,middle_name,last_name,prefix,suffix,role_position,employment_type,employment_status,avatar_url',
                    'departmentHead:id,first_name,middle_name,last_name'
                ])
                    ->whereNull('end_date')
                    ->orderBy('is_primary', 'desc');
            }
        ])->findOrFail($departmentId);

        $positions = PlantillaPosition::whereHas('item', fn($q) =>
            $q->where('division_id', $dept->division_id)
              ->whereJsonContains('department_ids', $dept->id)
        )
            ->with(['displayDepartment:id,name', 'salaryGrade:id,salary_grade'])
            ->whereIn('status', ['VACANT', 'UNFILLED'])
            ->get();

        return response()->json([
            'department'       => $this->formatDepartment($dept, $positions),
            'vacant_positions' => $this->formatVacantPositions($positions),
        ]);
    }

    public function summary(): JsonResponse
    {
        $divisions = Division::with([
            'departments' => function ($q) {
                $q->withCount([
                    'assignedEmployees as total_employees' => fn($q2) =>
                        $q2->whereNull('end_date')
                ]);
            }
        ])->get();

        return response()->json([
            'by_division' => $divisions->map(fn($div) => [
                'id'               => $div->id,
                'name'             => $div->name,
                'total_departments' => $div->departments->count(),
                'total_employees'  => $div->departments->sum('total_employees'),
                'departments'      => $div->departments->map(fn($d) => [
                    'id'              => $d->id,
                    'name'            => $d->name,
                    'total_employees' => $d->total_employees ?? 0,
                ]),
            ]),
        ]);
    }

    public function plantillaCount(): JsonResponse
    {
        $count = Employee::where('employment_type', 'Plantilla')
            ->where('employment_status', 'ACTIVE')
            ->count();

        return response()->json(['count' => $count]);
    }

    public function cosCount(): JsonResponse
    {
        $count = Employee::where('employment_type', 'Contract of Service')
            ->where('employment_status', 'ACTIVE')
            ->count();

        return response()->json(['count' => $count]);
    }

    public function consultantCount(): JsonResponse
    {
        $count = Employee::where('employment_type', 'Consultant')
            ->where('employment_status', 'ACTIVE')
            ->count();

        return response()->json(['count' => $count]);
    }

    public function vacantCount(): JsonResponse
    {
        $count = PlantillaPosition::where('status', 'VACANT')->count();

        return response()->json(['count' => $count]);
    }

    public function tree(Request $request): JsonResponse
    {
        $departmentId = $request->filled('department_id') && $request->department_id !== 'All'
            ? (int) $request->department_id
            : null;

        $divisionId = $request->filled('division_id') && $request->division_id !== 'All'
            ? (int) $request->division_id
            : null;

        $allDivisions = Division::with([
            'head:id,first_name,middle_name,last_name,prefix,suffix,title,role_position,employment_type,avatar_url',
        ])->orderBy('name')->get();

        $allDepartments = Department::with([
            'head:id,first_name,middle_name,last_name,prefix,suffix,title,role_position,employment_type,avatar_url',
            'division:id,name',
        ])
            ->orderBy('name')
            ->get();

        $allEmployees = Employee::select(
            'id', 'first_name', 'middle_name', 'last_name', 'prefix', 'suffix',
            'title', 'role_position', 'employment_type',
            'employment_status', 'avatar_url', 'department_ids'
        )
            ->whereNotNull('department_ids')
            ->get();

        $employeesByDept = [];
        foreach ($allEmployees as $emp) {
            foreach ($emp->department_ids ?? [] as $dId) {
                $employeesByDept[(int)$dId][] = $emp;
            }
        }

        $vacantPositions = PlantillaPosition::with(['displayDepartment:id,name', 'displayDivision:id,name'])
            ->whereIn('status', ['VACANT', 'UNFILLED'])
            ->where(function ($q) {
                $q->whereNotNull('display_department_id')
                  ->orWhereNotNull('display_division_id');
            })
            ->whereNotNull('role')
            ->get();

        $vacantByDept     = [];
        $vacantByDivision = [];
        foreach ($vacantPositions as $pos) {
            if ($pos->display_department_id) {
                $vacantByDept[(int)$pos->display_department_id][] = $pos;
            } elseif ($pos->display_division_id) {
                $vacantByDivision[(int)$pos->display_division_id][] = $pos;
            }
        }

        $visibleDepartments = $allDepartments
            ->filter(fn($d) => $d->parent_id !== null || $d->parent_division_id !== null)
            ->values();

        $departmentsByParent = [];
        foreach ($visibleDepartments->filter(fn($d) => $d->parent_id !== null) as $dept) {
            $departmentsByParent[(int)$dept->parent_id][] = $dept;
        }

        $departmentsByParentDivision = [];
        foreach ($visibleDepartments->filter(fn($d) => $d->parent_division_id !== null) as $dept) {
            $departmentsByParentDivision[(int)$dept->parent_division_id][] = $dept;
        }

        $departmentsByDivision = [];
        foreach ($visibleDepartments->filter(fn($d) => $d->parent_id !== null) as $dept) {
            $parent          = $allDepartments->firstWhere('id', $dept->parent_id);
            $parentIsVisible = $parent && ($parent->parent_id !== null || $parent->parent_division_id !== null);
            if (!$parentIsVisible) {
                $departmentsByDivision[(int)($dept->division_id ?? 0)][] = $dept;
            }
        }

        if ($departmentId) {
            $dept = $allDepartments->firstWhere('id', $departmentId);
            if (!$dept) {
                return response()->json(['nodes' => []]);
            }

            // FIX: $departmentsByParent was already passed correctly here — verified OK.
            $node = $this->formatDepartmentNode($dept, $departmentsByParent, $employeesByDept, $vacantByDept);

            $division = $dept->division_id
                ? $allDivisions->firstWhere('id', $dept->division_id)
                : null;

            while ($division) {
                $divNode             = $this->formatDivisionNode($division, collect(), [], [], $employeesByDept, $vacantByDept, $vacantByDivision);
                $divNode['children'] = [$node];
                $node                = $divNode;
                $division            = $division->parent_id
                    ? $allDivisions->firstWhere('id', $division->parent_id)
                    : null;
            }

            return response()->json(['nodes' => [$node]]);
        }

        if ($divisionId) {
            $division = $allDivisions->firstWhere('id', $divisionId);
            if (!$division) {
                return response()->json(['nodes' => []]);
            }

            // FIX: Pass $departmentsByParent so child departments of sub-departments
            // are resolved correctly when filtering by a single division.
            $node = $this->formatDivisionNode(
                $division, $allDivisions,
                $departmentsByDivision, $departmentsByParentDivision,
                $employeesByDept, $vacantByDept, $vacantByDivision,
                $departmentsByParent  // was missing — caused empty children on sub-departments
            );

            $current = $division;
            while ($current->parent_id) {
                $parent = $allDivisions->firstWhere('id', $current->parent_id);
                if (!$parent) break;

                // FIX: Also pass $departmentsByParent when wrapping ancestor nodes.
                $parentNode             = $this->formatDivisionNode(
                    $parent, collect(), [], [], $employeesByDept, $vacantByDept, $vacantByDivision, $departmentsByParent
                );
                $parentNode['children'] = [$node];
                $node                   = $parentNode;
                $current                = $parent;
            }

            return response()->json(['nodes' => [$node]]);
        }

        $rootDivisions = $allDivisions
            ->filter(fn($div) => $div->parent_id === null)
            ->values();

        $divisionNodes = $rootDivisions
            ->map(fn($div) => $this->formatDivisionNode(
                $div, $allDivisions,
                $departmentsByDivision, $departmentsByParentDivision,
                $employeesByDept, $vacantByDept, $vacantByDivision, $departmentsByParent
            ))
            ->toArray();

        $orphanDepartments = $allDepartments
            ->filter(fn($d) => $d->parent_id === null && $d->parent_division_id === null)
            ->values();

        $orphanNodes = $orphanDepartments
            ->map(fn($dept) => $this->formatDepartmentNode($dept, $departmentsByParent, $employeesByDept, $vacantByDept))
            ->values()
            ->toArray();

        return response()->json(['nodes' => array_merge($divisionNodes, $orphanNodes)]);
    }

    private function collectDescendantIds($allDepartments, int $parentId): array
    {
        $ids = [];
        foreach ($allDepartments->where('parent_id', $parentId) as $child) {
            $ids[] = $child->id;
            $ids   = array_merge($ids, $this->collectDescendantIds($allDepartments, $child->id));
        }
        return $ids;
    }

    private function formatDepartmentNode($dept, array $departmentsByParent = [], array $employeesByDept = [], array $vacantByDept = []): array
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

        $vacantHeadPosition = !$head
            ? $vacantSlots->first(fn($pos) => in_array(strtoupper($pos->role), self::HEAD_ROLES))
            : null;

        $remainingSlots = $vacantHeadPosition
            ? $vacantSlots->reject(fn($pos) => $pos->id === $vacantHeadPosition->id)->values()
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
            ->filter(fn($pos) => strtoupper($pos->role) !== 'STAFF')
            ->map(fn($pos) => [
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
            ->map(fn($child) => $this->formatDepartmentNode($child, $departmentsByParent, $employeesByDept, $vacantByDept))
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

    private function formatDivisionNode(
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

        $vacantHeadPosition = !$head
            ? $vacantSlots->first(fn($pos) => in_array(strtoupper($pos->role), self::HEAD_ROLES))
            : null;

        $remainingSlots = $vacantHeadPosition
            ? $vacantSlots->reject(fn($pos) => $pos->id === $vacantHeadPosition->id)->values()
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
            ->filter(fn($pos) => strtoupper($pos->role) !== 'STAFF')
            ->map(fn($pos) => [
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

        $childDivisions = $allDivisions instanceof \Illuminate\Support\Collection
            ? $allDivisions->where('parent_id', $division->id)->values()
            : collect();

        $childDivisionNodes = $childDivisions
            ->map(fn($child) => $this->formatDivisionNode(
                $child, $allDivisions,
                $departmentsByDivision, $departmentsByParentDivision,
                $employeesByDept, $vacantByDept, $vacantByDivision, $departmentsByParent
            ))
            ->toArray();

        $indirectDepts = collect($departmentsByDivision[$division->id] ?? []);
        $directDepts   = collect($departmentsByParentDivision[$division->id] ?? []);

        $depts = $indirectDepts->merge($directDepts)->unique('id')->values();

        $departmentNodes = $depts
            ->map(fn($dept) => $this->formatDepartmentNode($dept, $departmentsByParent, $employeesByDept, $vacantByDept))
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

    public function report(Request $request): JsonResponse
    {
        $divisionId   = $request->filled('division_id') ? $request->division_id : null;
        $departmentId = $request->filled('department_id') ? $request->department_id : null;

        $query = Employee::with(['division:id,name', 'info:id,employee_id']);

        if ($divisionId && $divisionId !== 'All') {
            $query->where('division_id', (int)$divisionId);
        }

        if ($departmentId && $departmentId !== 'All') {
            $query->whereRaw('JSON_CONTAINS(department_ids, ?)', [json_encode((int)$departmentId)]);
        }

        $employees = $query->orderBy('last_name')->get();

        $report = $employees->map(fn($emp) => [
            'EMPLOYEE NUMBER'   => $emp->employee_number ?? '',
            'PREFIX'            => $emp->prefix ?? '',
            'FIRST NAME'        => $emp->first_name ?? '',
            'MIDDLE NAME'       => $emp->middle_name ?? '',
            'LAST NAME'         => $emp->last_name ?? '',
            'TITLE'             => collect($emp->title ?? [])->implode(', '),
            'ROLE POSITION'     => collect($emp->role_position ?? [])->implode(', '),
            'DEPARTMENT'        => collect($emp->department_ids ?? [])->map(fn($id) => Department::find($id)?->name)->filter()->implode(', '),
            'DIVISION'          => $emp->division?->name ?? '',
            'POSITION TITLE'    => $emp->primaryAssignment?->plantillaPosition?->position_title ?? '',
            'SALARY GRADE'      => $emp->primaryAssignment?->plantillaPosition?->salaryGrade?->salary_grade ?? '',
            'STEP'              => $emp->primaryAssignment?->step_increment_id ?? '',
            'EMPLOYMENT TYPE'   => $emp->employment_type ?? '',
            'EMPLOYMENT STATUS' => $emp->employment_status ?? '',
        ])->toArray();

        $title = 'Employee Manpower Mapping Report';
        if ($divisionId && $divisionId !== 'All') {
            $division = Division::find($divisionId);
            $title   .= $division ? ' - ' . $division->name : '';
        }
        if ($departmentId && $departmentId !== 'All') {
            $department = Department::find($departmentId);
            $title     .= $department ? ' - ' . $department->name : '';
        }

        return response()->json([
            'title'  => $title,
            'report' => $report,
        ]);
    }

    public function overstaffed(): JsonResponse
    {
        $departments = Department::with([
            'assignedEmployees' => function ($q) {
                $q->with('employee:id,first_name,last_name')
                  ->whereNull('end_date');
            }
        ])->get();

        // FIX: Cache the plantilla count per department to avoid running the
        // same query 3x per department inside filter + map.
        $overstaffed = $departments
            ->filter(function ($dept) {
                $plantillaCount = PlantillaPosition::whereHas('item', fn($q) =>
                    $q->where('division_id', $dept->division_id)
                      ->whereJsonContains('department_ids', $dept->id)
                )
                    ->where('status', 'FILLED')
                    ->count();

                // Stash on the model so map() can reuse without re-querying.
                $dept->_plantilla_count = $plantillaCount;

                return $dept->assignedEmployees->count() > $plantillaCount;
            })
            ->map(fn($dept) => [
                'id'              => $dept->id,
                'name'            => $dept->name,
                'assigned_count'  => $dept->assignedEmployees->count(),
                'plantilla_count' => $dept->_plantilla_count,
                'excess'          => $dept->assignedEmployees->count() - $dept->_plantilla_count,
            ])
            ->values();

        return response()->json(['data' => $overstaffed]);
    }

    public function divisionAnalytics(): JsonResponse
    {
        $divisions = Division::with(['departments' => function ($q) {
            $q->withCount([
                'assignedEmployees as employee_count' => fn($q2) => $q2->whereNull('end_date')
            ]);
        }])->get();

        $analytics = $divisions->map(function ($division) {
            $totalEmployees = $division->departments->sum('employee_count');

            $plantillaCount = Employee::where('division_id', $division->id)
                ->where('employment_type', 'Plantilla')
                ->where('employment_status', 'ACTIVE')
                ->count();

            $cosCount = Employee::where('division_id', $division->id)
                ->where('employment_type', 'Contract of Service')
                ->where('employment_status', 'ACTIVE')
                ->count();

            $consultantCount = Employee::where('division_id', $division->id)
                ->where('employment_type', 'Consultant')
                ->where('employment_status', 'ACTIVE')
                ->count();

            $vacantCount = PlantillaPosition::where('display_department_id', '!=', null)
                ->whereHas('displayDepartment', fn($q) => $q->where('division_id', $division->id))
                ->where('status', 'VACANT')
                ->count();

            return [
                'id'                => $division->id,
                'name'              => $division->name,
                'total_employees'   => $totalEmployees,
                'total_departments' => $division->departments->count(),
                'plantilla'         => $plantillaCount,
                'cos'               => $cosCount,
                'consultant'        => $consultantCount,
                'vacant'            => $vacantCount,
            ];
        });

        return response()->json(['data' => $analytics]);
    }

    private function formatDivision($division)
    {
        return [
            'id'          => $division->id,
            'name'        => $division->name,
            'departments' => $division->departments
                ->map(fn($d) => $this->formatDepartment($d))
                ->values(),
        ];
    }

    private function formatDepartment($dept, $positions = null)
    {
        if ($positions === null) {
            $positions = PlantillaPosition::where('display_department_id', $dept->id)
                ->with(['displayDepartment:id,name', 'salaryGrade:id,salary_grade'])
                ->whereIn('status', ['VACANT', 'UNFILLED'])
                ->get();
        }

        $assignments = $dept->assignedEmployees ?? [];

        $leadership = [];
        $staff      = [];

        foreach ($assignments as $assignment) {
            $emp = $assignment->employee;
            if (!$emp) continue;

            $section   = $this->getEmployeeSection($emp->role_position);
            $formatted = $this->formatEmployee($emp, $assignment);

            if ($section === 'leadership') {
                $leadership[] = $formatted;
            } else {
                $staff[] = $formatted;
            }
        }

        usort($leadership, fn($a, $b) =>
            $this->getRoleOrder($a['primary_role']) <=>
            $this->getRoleOrder($b['primary_role'])
        );

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
            'vacant_count'    => $positions->where('status', 'VACANT')->count(),
            'unfilled_count'  => $positions->where('status', 'UNFILLED')->count(),
            'total_headcount' => count($leadership) + count($staff),
        ];
    }

    private function formatEmployee($emp, $assignment = null)
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

    private function formatVacantPositions($positions)
    {
        return $positions
            ->where('status', 'VACANT')
            ->map(fn($pos) => [
                'id'             => $pos->id,
                'item_number'    => $pos->base_item_number,
                'position_title' => $pos->position_title ?? $pos->title,
                'status'         => 'VACANT',
                'salary_grade'   => $pos->salaryGrade?->salary_grade,
            ])
            ->values()
            ->toArray();
    }

    private function formatNodeTree($employee): array
    {
        return [
            'key'  => 'node-' . $employee->id,
            'data' => [
                'id'                => $employee->id,
                'employeeId'        => $employee->id,
                'first_name'        => $employee->first_name,
                'last_name'         => $employee->last_name,
                'prefix'            => $employee->prefix,
                'suffix'            => $employee->suffix,
                'title'             => $employee->title ? (is_array($employee->title) ? $employee->title : $employee->title) : null,
                'name'              => $employee->full_name,
                'full_name'         => $employee->full_name,
                'role'              => $this->getPrimaryRole($employee->role_position),
                'department'        => $employee->department_ids
                    ? collect($employee->department_ids)->map(fn($id) => Department::find($id)?->name)->filter()->first()
                    : null,
                'employmentType'    => $employee->employment_type,
                'employment_type'   => $employee->employment_type,
                'employment_status' => $employee->employment_status,
                'image'             => $this->resolveAvatarUrl($employee->getRawOriginal('avatar_url') ?? $employee->avatar_url),
                'avatar_url'        => $this->resolveAvatarUrl($employee->getRawOriginal('avatar_url') ?? $employee->avatar_url),
                'gender'            => $employee->info?->gender,
                'division_id'       => $employee->division_id,
                'division'          => $employee->division?->name,
                'staff'             => $employee->children->map(fn($child) => [
                    'id'                => $child->id,
                    'employeeId'        => $child->id,
                    'first_name'        => $child->first_name,
                    'last_name'         => $child->last_name,
                    'prefix'            => $child->prefix,
                    'suffix'            => $child->suffix,
                    'title'             => $child->title ? (is_array($child->title) ? $child->title : $child->title) : null,
                    'name'              => $child->full_name,
                    'full_name'         => $child->full_name,
                    'role'              => $this->getPrimaryRole($child->role_position),
                    'employmentType'    => $child->employment_type,
                    'employment_type'   => $child->employment_type,
                    'employment_status' => $child->employment_status,
                    'image'             => $this->resolveAvatarUrl($child->getRawOriginal('avatar_url') ?? $child->avatar_url),
                    'avatar_url'        => $this->resolveAvatarUrl($child->getRawOriginal('avatar_url') ?? $child->avatar_url),
                    'gender'            => $child->info?->gender,
                ])->toArray(),
            ],
            'children' => $employee->children->map(fn($child) => $this->formatNodeTree($child))->toArray(),
        ];
    }

    private function getEmployeeSection($rolePosition): string
    {
        $roles = is_array($rolePosition)
            ? $rolePosition
            : ($rolePosition ? [$rolePosition] : []);

        $roles_upper = array_map(fn($r) => strtoupper(trim((string)$r)), $roles);

        $leadership_roles = [
            'CHIEF', 'DIRECTOR', 'ASSISTANT DIRECTOR', 'OFFICER IN CHARGE',
            'CHAIRMAN', 'HEAD', 'SUPERVISOR',
        ];

        foreach ($roles_upper as $role) {
            if (in_array($role, $leadership_roles)) {
                return 'leadership';
            }
        }

        return 'staff';
    }

    private function getPrimaryRole($rolePosition): string
    {
        $roles = is_array($rolePosition)
            ? $rolePosition
            : ($rolePosition ? [$rolePosition] : []);

        $roles_upper = array_map(fn($r) => strtoupper(trim((string)$r)), $roles);

        $hierarchy = [
            'CHIEF'              => 1,
            'DIRECTOR'           => 2,
            'ASSISTANT DIRECTOR' => 3,
            'OFFICER IN CHARGE'  => 4,
            'CHAIRMAN'           => 4,
            'HEAD'               => 5,
            'SUPERVISOR'         => 6,
        ];

        usort($roles_upper, fn($a, $b) =>
            ($hierarchy[$a] ?? 99) <=> ($hierarchy[$b] ?? 99)
        );

        return $roles_upper[0] ?? 'STAFF';
    }

    private function getRoleOrder(string $role): int
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

    private function buildFullName($employee): string
    {
        if (!$employee) return 'Unknown';

        $prefix = $employee->prefix
            ? rtrim($employee->prefix, '.') . '.'
            : '';

        $suffix = $employee->suffix
            ? ', ' . $employee->suffix
            : '';

        $middleName = !empty($employee->middle_name)
            ? ' ' . $employee->middle_name
            : '';

        return trim("$prefix {$employee->first_name}{$middleName} {$employee->last_name}") . $suffix;
    }
}