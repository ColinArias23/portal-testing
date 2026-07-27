<?php

namespace App\Services\Organization;

use App\Repositories\Shared\EmployeeRepository;
use App\Repositories\Organization\ManpowerMappingRepository;
use Illuminate\Http\Request;

class ManpowerMappingService
{
    public function __construct(
        private readonly ManpowerMappingRepository $manpower,
        private readonly EmployeeRepository $employees,
        private readonly ManpowerFormatterService $formatter,
    ) {
    }

    public function departments(Request $request): array
    {
        $divisionId = $request->filled('division_id') ? (int) $request->division_id : null;

        $divisions = $this->manpower->divisionsWithDepartmentsAndStaff($divisionId);

        return $divisions->map(fn ($div) => $this->formatter->formatDivision($div))->values()->all();
    }

    public function departmentDetail($departmentId): array
    {
        $dept      = $this->manpower->departmentWithStaff((int) $departmentId);
        $positions = $this->manpower->vacantOrUnfilledPositionsForDepartment($dept->id);

        return [
            'department'       => $this->formatter->formatDepartment($dept, $positions),
            'vacant_positions' => $this->formatter->formatVacantPositions($positions),
        ];
    }

    public function summary(): array
    {
        $divisions = $this->manpower->divisionsWithEmployeeCounts();

        return [
            'by_division' => $divisions->map(fn ($div) => [
                'id'                => $div->id,
                'name'              => $div->name,
                'total_departments' => $div->departments->count(),
                'total_employees'   => $div->departments->sum('total_employees'),
                'departments'       => $div->departments->map(fn ($d) => [
                    'id'              => $d->id,
                    'name'            => $d->name,
                    'total_employees' => $d->total_employees ?? 0,
                ]),
            ]),
        ];
    }

    public function plantillaCount(): int
    {
        return $this->manpower->countByEmploymentType('Plantilla');
    }

    public function cosCount(): int
    {
        return $this->manpower->countByEmploymentType('Contract of Service');
    }

    public function consultantCount(): int
    {
        return $this->manpower->countByEmploymentType('Consultant');
    }

    public function vacantCount(): int
    {
        return $this->manpower->countVacantPositions();
    }

    public function tree(Request $request): array
    {
        $departmentId = $request->filled('department_id') && $request->department_id !== 'All'
            ? (int) $request->department_id
            : null;

        $divisionId = $request->filled('division_id') && $request->division_id !== 'All'
            ? (int) $request->division_id
            : null;

        $allDivisions   = $this->manpower->allDivisionsWithHead();
        $allDepartments = $this->manpower->allDepartmentsWithHeadAndDivision();
        $allEmployees   = $this->manpower->allEmployeesWithDepartmentIds();

        $employeesByDept = [];
        foreach ($allEmployees as $emp) {
            foreach ($emp->department_ids ?? [] as $dId) {
                $employeesByDept[(int) $dId][] = $emp;
            }
        }

        $vacantPositions = $this->manpower->vacantOrUnfilledDisplayedPositions();

        $vacantByDept     = [];
        $vacantByDivision = [];
        foreach ($vacantPositions as $pos) {
            if ($pos->display_department_id) {
                $vacantByDept[(int) $pos->display_department_id][] = $pos;
            } elseif ($pos->display_division_id) {
                $vacantByDivision[(int) $pos->display_division_id][] = $pos;
            }
        }

        $visibleDepartments = $allDepartments
            ->filter(fn ($d) => $d->parent_id !== null || $d->parent_division_id !== null)
            ->values();

        $departmentsByParent = [];
        foreach ($visibleDepartments->filter(fn ($d) => $d->parent_id !== null) as $dept) {
            $departmentsByParent[(int) $dept->parent_id][] = $dept;
        }

        $departmentsByParentDivision = [];
        foreach ($visibleDepartments->filter(fn ($d) => $d->parent_division_id !== null) as $dept) {
            $departmentsByParentDivision[(int) $dept->parent_division_id][] = $dept;
        }

        $departmentsByDivision = [];
        foreach ($visibleDepartments->filter(fn ($d) => $d->parent_id !== null) as $dept) {
            $parent          = $allDepartments->firstWhere('id', $dept->parent_id);
            $parentIsVisible = $parent && ($parent->parent_id !== null || $parent->parent_division_id !== null);
            if (! $parentIsVisible) {
                $departmentsByDivision[(int) ($dept->division_id ?? 0)][] = $dept;
            }
        }

        if ($departmentId) {
            $dept = $allDepartments->firstWhere('id', $departmentId);
            if (! $dept) {
                return ['nodes' => []];
            }

            $node = $this->formatter->formatDepartmentNode($dept, $departmentsByParent, $employeesByDept, $vacantByDept);

            $division = $dept->division_id ? $allDivisions->firstWhere('id', $dept->division_id) : null;

            while ($division) {
                $divNode = $this->formatter->formatDivisionNode(
                    $division, collect(), [], [], $employeesByDept, $vacantByDept, $vacantByDivision
                );
                $divNode['children'] = [$node];
                $node                = $divNode;
                $division            = $division->parent_id ? $allDivisions->firstWhere('id', $division->parent_id) : null;
            }

            return ['nodes' => [$node]];
        }

        if ($divisionId) {
            $division = $allDivisions->firstWhere('id', $divisionId);
            if (! $division) {
                return ['nodes' => []];
            }

            $node = $this->formatter->formatDivisionNode(
                $division, $allDivisions,
                $departmentsByDivision, $departmentsByParentDivision,
                $employeesByDept, $vacantByDept, $vacantByDivision,
                $departmentsByParent
            );

            $current = $division;
            while ($current->parent_id) {
                $parent = $allDivisions->firstWhere('id', $current->parent_id);
                if (! $parent) {
                    break;
                }

                $parentNode = $this->formatter->formatDivisionNode(
                    $parent, collect(), [], [], $employeesByDept, $vacantByDept, $vacantByDivision, $departmentsByParent
                );
                $parentNode['children'] = [$node];
                $node                   = $parentNode;
                $current                = $parent;
            }

            return ['nodes' => [$node]];
        }

        $rootDivisions = $allDivisions->filter(fn ($div) => $div->parent_id === null)->values();

        $divisionNodes = $rootDivisions
            ->map(fn ($div) => $this->formatter->formatDivisionNode(
                $div, $allDivisions,
                $departmentsByDivision, $departmentsByParentDivision,
                $employeesByDept, $vacantByDept, $vacantByDivision, $departmentsByParent
            ))
            ->toArray();

        $orphanDepartments = $allDepartments
            ->filter(fn ($d) => $d->parent_id === null && $d->parent_division_id === null)
            ->values();

        $orphanNodes = $orphanDepartments
            ->map(fn ($dept) => $this->formatter->formatDepartmentNode($dept, $departmentsByParent, $employeesByDept, $vacantByDept))
            ->values()
            ->toArray();

        return ['nodes' => array_merge($divisionNodes, $orphanNodes)];
    }

    public function report(Request $request): array
    {
        $divisionId   = $request->filled('division_id') ? $request->division_id : null;
        $departmentId = $request->filled('department_id') ? $request->department_id : null;

        $employees   = $this->manpower->employeesForReport($divisionId, $departmentId);
        $departments = $this->employees->cachedDepartmentsById();

        $report = $employees->map(fn ($emp) => [
            'EMPLOYEE NUMBER'   => $emp->employee_number ?? '',
            'PREFIX'            => $emp->prefix ?? '',
            'FIRST NAME'        => $emp->first_name ?? '',
            'MIDDLE NAME'       => $emp->middle_name ?? '',
            'LAST NAME'         => $emp->last_name ?? '',
            'TITLE'             => collect($emp->title ?? [])->implode(', '),
            'ROLE POSITION'     => collect($emp->role_position ?? [])->implode(', '),
            'DEPARTMENT'        => collect($emp->department_ids ?? [])
                ->map(fn ($id) => $departments->get($id)?->name)
                ->filter()
                ->implode(', '),
            'DIVISION'          => $emp->division?->name ?? '',
            'POSITION TITLE'    => $emp->primaryAssignment?->plantillaPosition?->position_title ?? '',
            'SALARY GRADE'      => $emp->primaryAssignment?->plantillaPosition?->salaryGrade?->salary_grade ?? '',
            'STEP'              => $emp->primaryAssignment?->step_increment_id ?? '',
            'EMPLOYMENT TYPE'   => $emp->employment_type ?? '',
            'EMPLOYMENT STATUS' => $emp->employment_status ?? '',
        ])->toArray();

        $title = 'Employee Manpower Mapping Report';
        if ($divisionId && $divisionId !== 'All') {
            $division = $this->manpower->findDivision($divisionId);
            $title   .= $division ? ' - ' . $division->name : '';
        }
        if ($departmentId && $departmentId !== 'All') {
            $department = $this->manpower->findDepartment($departmentId);
            $title     .= $department ? ' - ' . $department->name : '';
        }

        return [
            'title'  => $title,
            'report' => $report,
        ];
    }

    public function overstaffed(): array
    {
        $departments = $this->departmentsWithAssignedEmployeesForOverstaffed();

        $filledCounts = $this->manpower->filledPlantillaCountsByDepartment();

        $overstaffed = $departments
            ->filter(function ($dept) use ($filledCounts) {
                $plantillaCount = $filledCounts[$dept->id] ?? 0;
                $dept->_plantilla_count = $plantillaCount;

                return $dept->assignedEmployees->count() > $plantillaCount;
            })
            ->map(fn ($dept) => [
                'id'              => $dept->id,
                'name'            => $dept->name,
                'assigned_count'  => $dept->assignedEmployees->count(),
                'plantilla_count' => $dept->_plantilla_count,
                'excess'          => $dept->assignedEmployees->count() - $dept->_plantilla_count,
            ])
            ->values();

        return ['data' => $overstaffed];
    }

    private function departmentsWithAssignedEmployeesForOverstaffed()
    {
        return \App\Models\Department::with([
            'assignedEmployees' => function ($q) {
                $q->with('employee:id,first_name,last_name')->whereNull('end_date');
            },
        ])->get();
    }

    public function divisionAnalytics(): array
    {
        $divisions          = $this->manpower->divisionsWithEmployeeCounts();
        $employmentCounts   = $this->manpower->employmentTypeCountsByDivision();
        $vacantByDivision   = $this->manpower->vacantCountsByDivision();

        $analytics = $divisions->map(function ($division) use ($employmentCounts, $vacantByDivision) {
            $counts = $employmentCounts[$division->id] ?? [];

            return [
                'id'                => $division->id,
                'name'              => $division->name,
                'total_employees'   => $division->departments->sum('total_employees'),
                'total_departments' => $division->departments->count(),
                'plantilla'         => $counts['plantilla'] ?? 0,
                'cos'               => $counts['cos'] ?? 0,
                'consultant'        => $counts['consultant'] ?? 0,
                'vacant'            => $vacantByDivision[$division->id] ?? 0,
            ];
        });

        return ['data' => $analytics];
    }
}
