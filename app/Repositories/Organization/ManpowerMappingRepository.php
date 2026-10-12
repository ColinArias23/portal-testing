<?php

namespace App\Repositories\Organization;

use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\PlantillaPosition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ManpowerMappingRepository
{
    public function divisionsWithDepartmentsAndStaff(?int $divisionId): Collection
    {
        return Division::when($divisionId, fn ($q) => $q->where('id', $divisionId))
            ->with(['departments' => function ($q) {
                $q->with([
                    'head:id,first_name,last_name,prefix,suffix',
                    'assignedEmployees' => function ($q2) {
                        $q2->with([
                            'employee:id,first_name,middle_name,last_name,prefix,suffix,role_position,employment_type,employment_status,avatar_url',
                            'departmentHead:id,first_name,middle_name,last_name',
                        ])
                            ->whereNull('end_date')
                            ->orderBy('is_primary', 'desc');
                    },
                ]);
            }])
            ->get();
    }

    public function departmentWithStaff(int $departmentId): Department
    {
        return Department::with([
            'division:id,name',
            'head:id,first_name,middle_name,last_name,prefix,suffix,employment_status',
            'assignedEmployees' => function ($q) {
                $q->with([
                    'employee:id,first_name,middle_name,last_name,prefix,suffix,role_position,employment_type,employment_status,avatar_url',
                    'departmentHead:id,first_name,middle_name,last_name',
                ])
                    ->whereNull('end_date')
                    ->orderBy('is_primary', 'desc');
            },
        ])->findOrFail($departmentId);
    }

    public function unfilledPositionsForDepartment(int $departmentId): Collection
    {
        return PlantillaPosition::where('display_department_id', $departmentId)
            ->with(['displayDepartment:id,name', 'salaryGrade:id,salary_grade'])
            ->where('status', 'UNFILLED')
            ->get();
    }

    public function divisionsWithEmployeeCounts(): Collection
    {
        return Division::with([
            'departments' => function ($q) {
                $q->withCount([
                    'assignedEmployees as total_employees' => fn ($q2) => $q2->whereNull('end_date'),
                ]);
            },
        ])->get();
    }

    public function countByEmploymentType(string $type): int
    {
        return Employee::where('employment_type', $type)
            ->where('employment_status', 'ACTIVE')
            ->count();
    }

    public function countVacantPositions(): int
    {
        return PlantillaPosition::where('status', 'UNFILLED')->count();
    }

    public function allDivisionsWithHead(): Collection
    {
        return Division::with([
            'head:id,first_name,middle_name,last_name,prefix,suffix,title,role_position,employment_type,avatar_url',
        ])->orderBy('name')->get();
    }

    public function allDepartmentsWithHeadAndDivision(): Collection
    {
        return Department::with([
            'head:id,first_name,middle_name,last_name,prefix,suffix,title,role_position,employment_type,avatar_url',
            'division:id,name',
        ])->orderBy('name')->get();
    }

    public function allEmployeesWithDepartmentIds(): Collection
    {
        return Employee::select(
            'id', 'first_name', 'middle_name', 'last_name', 'prefix', 'suffix',
            'title', 'role_position', 'employment_type',
            'employment_status', 'avatar_url', 'department_ids'
        )
            ->whereNotNull('department_ids')
            ->get();
    }

    public function unfilledDisplayedPositions(): Collection
    {
        return PlantillaPosition::with(['displayDepartment:id,name', 'displayDivision:id,name'])
            ->where('status', 'UNFILLED')
            ->where(function ($q) {
                $q->whereNotNull('display_department_id')
                    ->orWhereNotNull('display_division_id');
            })
            ->whereNotNull('role')
            ->get();
    }

    public function employeesForReport(?string $divisionId, ?string $departmentId): Collection
    {
        $query = Employee::with(['division:id,name', 'info:id,employee_id']);

        if ($divisionId && $divisionId !== 'All') {
            $query->where('division_id', (int) $divisionId);
        }

        if ($departmentId && $departmentId !== 'All') {
            $query->whereRaw('JSON_CONTAINS(department_ids, ?)', [json_encode((int) $departmentId)]);
        }

        return $query->orderBy('last_name')->get();
    }

    public function findDivision(int|string $id): ?Division
    {
        return Division::find($id);
    }

    public function findDepartment(int|string $id): ?Department
    {
        return Department::find($id);
    }

    public function departmentsWithEmployeeCounts(): Collection
    {
        return Department::withCount([
            'assignedEmployees as employee_count' => fn ($q) => $q->whereNull('end_date'),
        ])->get(['id', 'name', 'division_id']);
    }

    public function filledPlantillaCountsByDepartment(): array
    {
        return PlantillaPosition::where('status', 'FILLED')
            ->whereNotNull('display_department_id')
            ->select('display_department_id', DB::raw('count(*) as cnt'))
            ->groupBy('display_department_id')
            ->pluck('cnt', 'display_department_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    public function employmentTypeCountsByDivision(): array
    {
        $rows = Employee::where('employment_status', 'ACTIVE')
            ->whereIn('employment_type', ['Plantilla', 'Contract of Service', 'Consultant'])
            ->whereNotNull('division_id')
            ->select('division_id', 'employment_type', DB::raw('count(*) as cnt'))
            ->groupBy('division_id', 'employment_type')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $key = match ($row->employment_type) {
                'Plantilla' => 'plantilla',
                'Contract of Service' => 'cos',
                'Consultant' => 'consultant',
                default => null,
            };

            if ($key === null) {
                continue;
            }

            $map[(int) $row->division_id][$key] = (int) $row->cnt;
        }

        return $map;
    }

    public function vacantCountsByDivision(): array
    {
        return PlantillaPosition::where('plantilla_positions.status', 'UNFILLED')
            ->whereNotNull('plantilla_positions.display_department_id')
            ->join('departments', 'departments.id', '=', 'plantilla_positions.display_department_id')
            ->whereNotNull('departments.division_id')
            ->select('departments.division_id', DB::raw('count(*) as cnt'))
            ->groupBy('departments.division_id')
            ->pluck('cnt', 'division_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
