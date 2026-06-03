<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\EmployeeDepartmentAssignment;
use App\Models\PlantillaPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManpowerMappingController extends Controller
{
    /**
     * GET /api/manpower/departments
     * 
     * Returns all departments with employees organized by role section
     */
    public function departments(Request $request): JsonResponse
    {
        $divisionId = $request->filled('division_id') 
            ? (int)$request->division_id 
            : null;

        $divisions = Division::when($divisionId, fn($q) => $q->where('id', $divisionId))
            ->with(['departments' => function($q) {
                $q->with([
                    'head:id,first_name,last_name,prefix,suffix',
                    'assignedEmployees' => function($q2) {
                        $q2->with([
                            'employee:id,first_name,last_name,prefix,suffix,role_position,employment_type,employment_status,avatar_url',
                            'departmentHead:id,first_name,last_name'
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

    /**
     * GET /api/manpower/department/{id}
     * 
     * Returns detailed view of a single department
     */
    public function departmentDetail($departmentId): JsonResponse
    {
        $dept = Department::with([
            'division:id,name',
            'head:id,first_name,last_name,prefix,suffix,employment_status',
            'assignedEmployees' => function($q) {
                $q->with([
                    'employee:id,first_name,last_name,prefix,suffix,role_position,employment_type,employment_status,avatar_url',
                    'departmentHead:id,first_name,last_name'
                ])
                ->whereNull('end_date')
                ->orderBy('is_primary', 'desc');
            }
        ])->findOrFail($departmentId);

        // Get vacant positions for this department
        $positions = PlantillaPosition::whereHas('item', fn($q) =>
            $q->where('division_id', $dept->division_id)
              ->whereJsonContains('department_ids', $dept->id)
        )
        ->with(['item:id,title', 'salaryGrade:id,salary_grade'])
        ->whereIn('status', ['VACANT', 'UNFILLED'])
        ->get();

        return response()->json([
            'department' => $this->formatDepartment($dept, $positions),
            'vacant_positions' => $this->formatVacantPositions($positions),
        ]);
    }

    /**
     * GET /api/manpower/summary
     * 
     * Returns summary statistics
     */
    public function summary(): JsonResponse
    {
        $divisions = Division::with([
            'departments' => function($q) {
                $q->withCount([
                    'assignedEmployees as total_employees' => fn($q2) => 
                        $q2->whereNull('end_date')
                ]);
            }
        ])->get();

        return response()->json([
            'by_division' => $divisions->map(fn($div) => [
                'id' => $div->id,
                'name' => $div->name,
                'total_departments' => $div->departments->count(),
                'total_employees' => $div->departments->sum('total_employees'),
                'departments' => $div->departments->map(fn($d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'total_employees' => $d->total_employees ?? 0,
                ]),
            ]),
        ]);
    }

    /**
     * GET /api/manpower/plantilla-count
     * 
     * Returns count of active plantilla employees
     */
    public function plantillaCount(): JsonResponse
    {
        $count = Employee::where('employment_type', 'Plantilla')
            ->where('employment_status', 'ACTIVE')
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * GET /api/manpower/cos-count
     * 
     * Returns count of active Contract of Service employees
     */
    public function cosCount(): JsonResponse
    {
        $count = Employee::where('employment_type', 'Contract of Service')
            ->where('employment_status', 'ACTIVE')
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * GET /api/manpower/consultant-count
     * 
     * Returns count of active consultant employees
     */
    public function consultantCount(): JsonResponse
    {
        $count = Employee::where('employment_type', 'Consultant')
            ->where('employment_status', 'ACTIVE')
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * GET /api/manpower/vacant-count
     * 
     * Returns count of vacant plantilla positions
     */
    public function vacantCount(): JsonResponse
    {
        $count = PlantillaPosition::where('status', 'VACANT')->count();

        return response()->json(['count' => $count]);
    }

    /**
     * GET /api/manpower/tree
     * 
     * Returns organization hierarchy tree structure
     */
    public function tree(Request $request): JsonResponse
    {
        $divisionId = $request->filled('division_id') ? (int)$request->division_id : null;
        $departmentId = $request->filled('department_id') ? (int)$request->department_id : null;

        $query = Employee::with([
            'children',
            'division:id,name',
            'primaryAssignment.plantillaPosition.salaryGrade',
            'info:id,employee_id,gender'
        ]);

        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }

        if ($departmentId) {
            $query->whereRaw('JSON_CONTAINS(department_ids, ?)', [json_encode($departmentId)]);
        }

        $rootEmployees = $query
            ->whereDoesntHave('parents')
            ->orderBy('last_name')
            ->get();

        return response()->json([
            'nodes' => $rootEmployees->map(fn($emp) => $this->formatNodeTree($emp))->toArray(),
        ]);
    }

    /**
     * GET /api/manpower/report
     * 
     * Returns detailed employee report
     */
    public function report(Request $request): JsonResponse
    {
        $divisionId = $request->filled('division_id') ? $request->division_id : null;
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
            'EMPLOYEE NUMBER' => $emp->employee_number ?? '',
            'PREFIX' => $emp->prefix ?? '',
            'FIRST NAME' => $emp->first_name ?? '',
            'MIDDLE NAME' => $emp->middle_name ?? '',
            'LAST NAME' => $emp->last_name ?? '',
            'TITLE' => collect($emp->title ?? [])->implode(', '),
            'ROLE POSITION' => collect($emp->role_position ?? [])->implode(', '),
            'DEPARTMENT' => collect($emp->department_ids ?? [])->map(fn($id) => Department::find($id)?->name)->filter()->implode(', '),
            'DIVISION' => $emp->division?->name ?? '',
            'POSITION TITLE' => $emp->primaryAssignment?->plantillaPosition?->position_title ?? '',
            'SALARY GRADE' => $emp->primaryAssignment?->plantillaPosition?->salaryGrade?->salary_grade ?? '',
            'STEP' => $emp->primaryAssignment?->step_increment_id ?? '',
            'EMPLOYMENT TYPE' => $emp->employment_type ?? '',
            'EMPLOYMENT STATUS' => $emp->employment_status ?? '',
        ])->toArray();

        $title = 'Employee Manpower Mapping Report';
        if ($divisionId && $divisionId !== 'All') {
            $division = Division::find($divisionId);
            $title .= $division ? ' - ' . $division->name : '';
        }
        if ($departmentId && $departmentId !== 'All') {
            $department = Department::find($departmentId);
            $title .= $department ? ' - ' . $department->name : '';
        }

        return response()->json([
            'title' => $title,
            'report' => $report,
        ]);
    }

    /**
     * GET /api/manpower/overstaffed
     * 
     * Returns departments that are overstaffed
     */
    public function overstaffed(): JsonResponse
    {
        $departments = Department::with([
            'assignedEmployees' => function($q) {
                $q->with('employee:id,first_name,last_name')
                  ->whereNull('end_date');
            }
        ])->get();

        $overstaffed = $departments
            ->filter(function ($dept) {
                $assigned = $dept->assignedEmployees->count();
                $plantilla = PlantillaPosition::whereHas('item', fn($q) =>
                    $q->where('division_id', $dept->division_id)
                      ->whereJsonContains('department_ids', $dept->id)
                )
                ->where('status', 'FILLED')
                ->count();

                return $assigned > $plantilla;
            })
            ->map(fn($dept) => [
                'id' => $dept->id,
                'name' => $dept->name,
                'assigned_count' => $dept->assignedEmployees->count(),
                'plantilla_count' => PlantillaPosition::whereHas('item', fn($q) =>
                    $q->where('division_id', $dept->division_id)
                      ->whereJsonContains('department_ids', $dept->id)
                )->where('status', 'FILLED')->count(),
                'excess' => $dept->assignedEmployees->count() - PlantillaPosition::whereHas('item', fn($q) =>
                    $q->where('division_id', $dept->division_id)
                      ->whereJsonContains('department_ids', $dept->id)
                )->where('status', 'FILLED')->count(),
            ])
            ->values();

        return response()->json(['data' => $overstaffed]);
    }

    /**
     * GET /api/manpower/division-analytics
     * 
     * Returns analytics by division
     */
    public function divisionAnalytics(): JsonResponse
    {
        $divisions = Division::with(['departments' => function($q) {
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

            $vacantCount = PlantillaPosition::whereHas('item', fn($q) =>
                $q->where('division_id', $division->id)
            )->where('status', 'VACANT')->count();

            return [
                'id' => $division->id,
                'name' => $division->name,
                'total_employees' => $totalEmployees,
                'total_departments' => $division->departments->count(),
                'plantilla' => $plantillaCount,
                'cos' => $cosCount,
                'consultant' => $consultantCount,
                'vacant' => $vacantCount,
            ];
        });

        return response()->json(['data' => $analytics]);
    }

    // ──────────────────── PRIVATE HELPERS ────────────────────

    private function formatDivision($division)
    {
        return [
            'id' => $division->id,
            'name' => $division->name,
            'departments' => $division->departments
                ->map(fn($d) => $this->formatDepartment($d))
                ->values(),
        ];
    }

    private function formatDepartment($dept, $positions = null)
    {
        if ($positions === null) {
            $positions = PlantillaPosition::whereHas('item', fn($q) =>
                $q->where('division_id', $dept->division_id)
                  ->whereJsonContains('department_ids', $dept->id)
            )
            ->with(['item:id,title', 'salaryGrade:id,salary_grade'])
            ->whereIn('status', ['VACANT', 'UNFILLED'])
            ->get();
        }

        $assignments = $dept->assignedEmployees ?? [];
        
        // Organize by role section
        $leadership = [];
        $staff = [];

        foreach ($assignments as $assignment) {
            $emp = $assignment->employee;
            if (!$emp) continue;

            $section = $this->getEmployeeSection($emp->role_position);
            $formatted = $this->formatEmployee($emp, $assignment);

            if ($section === 'leadership') {
                $leadership[] = $formatted;
            } else {
                $staff[] = $formatted;
            }
        }

        // Sort leadership by role hierarchy
        usort($leadership, fn($a, $b) => 
            $this->getRoleOrder($a['primary_role']) <=> 
            $this->getRoleOrder($b['primary_role'])
        );

        return [
            'id' => $dept->id,
            'name' => $dept->name,
            'type' => $dept->type,
            'division_id' => $dept->division_id,
            'head' => $dept->head ? [
                'id' => $dept->head->id,
                'full_name' => $this->buildFullName($dept->head),
            ] : null,
            'leadership' => $leadership,
            'staff' => $staff,
            'staff_count' => count($staff),
            'vacant_count' => $positions->where('status', 'VACANT')->count(),
            'unfilled_count' => $positions->where('status', 'UNFILLED')->count(),
            'total_headcount' => count($leadership) + count($staff),
        ];
    }

    private function formatEmployee($emp, $assignment = null)
    {
        return [
            'id' => $emp->id,
            'full_name' => $this->buildFullName($emp),
            'first_name' => $emp->first_name,
            'last_name' => $emp->last_name,
            'role_position' => is_array($emp->role_position) 
                ? $emp->role_position 
                : ($emp->role_position ? [$emp->role_position] : []),
            'primary_role' => $this->getPrimaryRole($emp->role_position),
            'employment_type' => $emp->employment_type,
            'employment_status' => $emp->employment_status ?? 'ACTIVE',
            'avatar_url' => $emp->avatar_url,
            'is_primary_head' => $assignment?->is_primary ?? false,
        ];
    }

    private function formatVacantPositions($positions)
    {
        return $positions
            ->where('status', 'VACANT')
            ->map(fn($pos) => [
                'id' => $pos->id,
                'item_number' => $pos->item_number,
                'position_title' => $pos->position_title ?? $pos->item?->title,
                'status' => 'VACANT',
                'salary_grade' => $pos->salaryGrade?->salary_grade,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Format employee as a tree node with children
     */
    private function formatNodeTree($employee): array
    {
        return [
            'key' => 'node-' . $employee->id,
            'data' => [
                'id' => $employee->id,
                'employeeId' => $employee->id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'prefix' => $employee->prefix,
                'suffix' => $employee->suffix,
                'title' => $employee->title ? (is_array($employee->title) ? $employee->title[0] : $employee->title) : null,
                'name' => $employee->full_name,
                'full_name' => $employee->full_name,
                'role' => $this->getPrimaryRole($employee->role_position),
                'department' => $employee->department_ids ? collect($employee->department_ids)->map(fn($id) => Department::find($id)?->name)->filter()->first() : null,
                'employmentType' => $employee->employment_type,
                'employment_type' => $employee->employment_type,
                'employment_status' => $employee->employment_status,
                'image' => $employee->avatar_url,
                'avatar_url' => $employee->avatar_url,
                'gender' => $employee->info?->gender,
                'division_id' => $employee->division_id,
                'division' => $employee->division?->name,
                'staff' => $employee->children->map(fn($child) => [
                    'id' => $child->id,
                    'employeeId' => $child->id,
                    'first_name' => $child->first_name,
                    'last_name' => $child->last_name,
                    'prefix' => $child->prefix,
                    'suffix' => $child->suffix,
                    'title' => $child->title ? (is_array($child->title) ? $child->title[0] : $child->title) : null,
                    'name' => $child->full_name,
                    'full_name' => $child->full_name,
                    'role' => $this->getPrimaryRole($child->role_position),
                    'employmentType' => $child->employment_type,
                    'employment_type' => $child->employment_type,
                    'employment_status' => $child->employment_status,
                    'image' => $child->avatar_url,
                    'avatar_url' => $child->avatar_url,
                    'gender' => $child->info?->gender,
                ])->toArray(),
            ],
            'children' => $employee->children->map(fn($child) => $this->formatNodeTree($child))->toArray(),
        ];
    }

    /**
     * Determine employee display section
     */
    private function getEmployeeSection($rolePosition): string
    {
        $roles = is_array($rolePosition) 
            ? $rolePosition 
            : ($rolePosition ? [$rolePosition] : []);
        
        $roles_upper = array_map(fn($r) => strtoupper(trim((string)$r)), $roles);

        $leadership_roles = [
            'CHIEF', 'DIRECTOR', 'ASSISTANT DIRECTOR', 'OFFICER IN CHARGE',
            'CHAIRMAN', 'HEAD', 'SUPERVISOR'
        ];

        foreach ($roles_upper as $role) {
            if (in_array($role, $leadership_roles)) {
                return 'leadership';
            }
        }

        return 'staff';
    }

    /**
     * Get primary role for display
     */
    private function getPrimaryRole($rolePosition): string
    {
        $roles = is_array($rolePosition) 
            ? $rolePosition 
            : ($rolePosition ? [$rolePosition] : []);
        
        $roles_upper = array_map(fn($r) => strtoupper(trim((string)$r)), $roles);

        $hierarchy = [
            'CHIEF' => 1,
            'DIRECTOR' => 2,
            'ASSISTANT DIRECTOR' => 3,
            'OFFICER IN CHARGE' => 4,
            'CHAIRMAN' => 4,
            'HEAD' => 5,
            'SUPERVISOR' => 6,
        ];

        usort($roles_upper, fn($a, $b) => 
            ($hierarchy[$a] ?? 99) <=> ($hierarchy[$b] ?? 99)
        );

        return $roles_upper[0] ?? 'STAFF';
    }

    /**
     * Get role display order
     */
    private function getRoleOrder(string $role): int
    {
        $orders = [
            'CHIEF' => 1,
            'DIRECTOR' => 2,
            'ASSISTANT DIRECTOR' => 3,
            'OFFICER IN CHARGE' => 4,
            'CHAIRMAN' => 4,
            'HEAD' => 5,
            'SUPERVISOR' => 6,
        ];

        return $orders[strtoupper($role)] ?? 99;
    }

    /**
     * Build full name from employee
     */
    private function buildFullName($employee): string
    {
        if (!$employee) return 'Unknown';

        $prefix = $employee->prefix 
            ? trim(str_replace('.', '', $employee->prefix)) . '.' 
            : '';
        
        $suffix = $employee->suffix 
            ? ', ' . $employee->suffix 
            : '';

        return trim("$prefix {$employee->first_name} {$employee->last_name}") . $suffix;
    }
}