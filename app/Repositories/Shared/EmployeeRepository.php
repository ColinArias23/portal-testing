<?php

namespace App\Repositories\Shared;

use App\Models\Employee;
use App\Models\EmployeeHierarchy;
use App\Models\EmployeeInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EmployeeRepository
{
    private const DEPARTMENTS_CACHE_KEY = 'departments:all:id_name';
    private const DEPARTMENTS_CACHE_TTL = 3600;

    private const FULL_RELATIONS = [
        'info',
        'parents',
        'division',
        'user',
        'primaryAssignment.plantillaPosition.salaryGrade',
        'primaryAssignment.stepIncrement',
        'cosPosition',
        'consultantPosition',
        'latestAssessment',
        'activeDevelopmentPlans.skill',
        'salaryHistories',
    ];

    private const SEARCH_COLUMNS = [
        'id', 'prefix', 'first_name', 'middle_name', 'last_name', 'suffix', 'role_position',
    ];

    // ── Departments cache (used by EmployeeFormatterService) ────────────

    public function cachedDepartmentsById(): Collection
    {
        return Cache::remember(
            self::DEPARTMENTS_CACHE_KEY,
            self::DEPARTMENTS_CACHE_TTL,
            fn () => \App\Models\Department::query()->select(['id', 'name'])->get()->keyBy('id')
        );
    }

    public function findDepartmentsByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $all = $this->cachedDepartmentsById();

        return collect($ids)
            ->map(fn ($id) => $all->get($id))
            ->filter()
            ->map(fn ($dept) => ['id' => $dept->id, 'name' => $dept->name])
            ->values()
            ->all();
    }

    public function forgetDepartmentsCache(): void
    {
        Cache::forget(self::DEPARTMENTS_CACHE_KEY);
    }

    public function createBareEmployee(array $attributes): int
    {
        $now = now()->toDateTimeString();

        $employeeId = DB::table('employees')->insertGetId(array_merge($attributes, [
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        DB::table('employee_info')->insert([
            'employee_id' => $employeeId,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        return $employeeId;
    }

    public function updateEmploymentStatus(int $employeeId, string $status): void
    {
        DB::table('employees')->where('id', $employeeId)->update([
            'employment_status' => $status,
            'updated_at'        => now()->toDateTimeString(),
        ]);
    }

    public function updateEmploymentStatusForMany(array $employeeIds, string $status): void
    {
        if (empty($employeeIds)) {
            return;
        }

        DB::table('employees')->whereIn('id', $employeeIds)->update([
            'employment_status' => $status,
            'updated_at'        => now()->toDateTimeString(),
        ]);
    }

    // ── Department membership (department_ids JSON column) ──────────────

    public function allWithDepartmentIds(): Collection
    {
        return Employee::select('id', 'department_ids')
            ->whereNotNull('department_ids')
            ->get();
    }

    public function employeesInDepartment(int $departmentId): Collection
    {
        return Employee::whereRaw(
            'JSON_CONTAINS(department_ids, ?)',
            [json_encode($departmentId)]
        )->get();
    }

    public function find(int $employeeId): ?Employee
    {
        return Employee::find($employeeId);
    }

    public function detachDepartmentFromEmployeesExcept(int $departmentId, array $keepEmployeeIds): void
    {
        $this->employeesInDepartment($departmentId)->each(function (Employee $emp) use ($departmentId, $keepEmployeeIds) {
            if (in_array($emp->id, $keepEmployeeIds, true)) {
                return;
            }

            $emp->department_ids = array_values(array_filter(
                $emp->department_ids ?? [],
                fn ($id) => (int) $id !== $departmentId
            ));
            $emp->save();
        });
    }

    public function attachDepartmentToEmployees(int $departmentId, array $employeeIds): void
    {
        $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));

        if (empty($employeeIds)) {
            return;
        }

        Employee::select(['id', 'department_ids'])
            ->whereIn('id', $employeeIds)
            ->get()
            ->each(function (Employee $employee) use ($departmentId) {
                $ids = array_map('intval', $employee->department_ids ?? []);

                if (! in_array($departmentId, $ids, true)) {
                    $employee->department_ids = [...$ids, $departmentId];
                    $employee->save();
                }
            });
    }

    public function removeDepartmentFromAllEmployees(int $departmentId): void
    {
        $this->employeesInDepartment($departmentId)->each(function (Employee $emp) use ($departmentId) {
            $emp->department_ids = array_values(array_filter(
                $emp->department_ids ?? [],
                fn ($id) => (int) $id !== $departmentId
            ));
            $emp->save();
        });
    }

    // ── Listing / search / CRUD (EmployeeController) ─────────────────────

    public function baseQuery(?int $divisionId, ?int $departmentId, ?string $employmentType = null): Builder
    {
        $query = Employee::with(self::FULL_RELATIONS);

        if ($divisionId !== null) {
            $query->where('division_id', $divisionId);
        }

        if ($departmentId !== null) {
            $query->whereRaw('JSON_CONTAINS(department_ids, ?)', [json_encode($departmentId)]);
        }

        $this->applyEmploymentTypeFilter($query, $employmentType);

        return $query->orderBy('id');
    }

    public function searchCandidatesQuery(
        string $term,
        ?int $divisionId,
        ?int $departmentId,
        ?string $employmentType = null
    ): Builder {
        $query = Employee::select(self::SEARCH_COLUMNS);

        if ($divisionId !== null) {
            $query->where('division_id', $divisionId);
        }

        if ($departmentId !== null) {
            $query->whereRaw('JSON_CONTAINS(department_ids, ?)', [json_encode($departmentId)]);
        }

        $this->applyEmploymentTypeFilter($query, $employmentType);

        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(function (Builder $q) use ($like, $term) {
            $q->whereRaw(
                "CONCAT_WS(' ', prefix, first_name, middle_name, last_name, suffix) LIKE ?",
                [$like]
            )->orWhereRaw(
                'JSON_SEARCH(LOWER(role_position), "one", ?) IS NOT NULL',
                ['%'.strtolower(addcslashes($term, '%_\\')).'%']
            );
        });

        return $query->orderBy('last_name')->orderBy('first_name');
    }

    private function applyEmploymentTypeFilter(Builder $query, ?string $employmentType): void
    {
        if (! $employmentType) {
            return;
        }

        match ($employmentType) {
            'plantilla' => $query->plantilla(),
            'consultant' => $query->consultant(),
            'contract-of-service' => $query->cos(),
            default => null,
        };
    }

    public function findManyWithFullRelations(array $ids): Collection
    {
        $employees = Employee::with(self::FULL_RELATIONS)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return collect($ids)->map(fn ($id) => $employees->get($id))->filter()->values();
    }

    public function findWithFullRelations(Employee $employee): Employee
    {
        return $employee->load(self::FULL_RELATIONS);
    }

    public function teamQuery(): Builder
    {
        return Employee::with(self::FULL_RELATIONS)->orderBy('id');
    }

    public function nonRootEmployees(Collection $rootIds): Collection
    {
        return Employee::with(['division', 'latestAssessment'])
            ->whereNotIn('id', $rootIds)
            ->orderBy('last_name')
            ->get();
    }

    public function rootEmployeeIds(): Collection
    {
        return DB::table('employee_hierarchy')->whereNull('parent_id')->pluck('employee_id');
    }

    public function genderCounts(): array
    {
        $row = EmployeeInfo::query()
            ->selectRaw("SUM(CASE WHEN gender = 'MALE' THEN 1 ELSE 0 END) as male")
            ->selectRaw("SUM(CASE WHEN gender = 'FEMALE' THEN 1 ELSE 0 END) as female")
            ->first();

        return [
            'male'   => (int) ($row->male ?? 0),
            'female' => (int) ($row->female ?? 0),
        ];
    }

    public function employeeNumberExists(string $normalizedNumber, ?int $ignoreId): bool
    {
        return Employee::whereRaw('UPPER(TRIM(employee_number)) = ?', [$normalizedNumber])
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    public function create(array $attributes): Employee
    {
        return Employee::create($attributes);
    }

    public function syncParents(int $employeeId, array $parentIds): void
    {
        EmployeeHierarchy::where('employee_id', $employeeId)->delete();

        foreach (array_filter($parentIds) as $parentId) {
            EmployeeHierarchy::create([
                'employee_id' => $employeeId,
                'parent_id'   => $parentId,
            ]);
        }
    }

    public function deleteHierarchyFor(int $employeeId): void
    {
        EmployeeHierarchy::where('employee_id', $employeeId)
            ->orWhere('parent_id', $employeeId)
            ->delete();
    }
}