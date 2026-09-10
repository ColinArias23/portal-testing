<?php

namespace App\Services\Organization;

use App\DTOs\DepartmentDTO;
use App\Models\Department;
use App\Repositories\Organization\DepartmentRepository;
use App\Repositories\Shared\EmployeeRepository;
use Illuminate\Support\Collection;

class DepartmentService
{
    public function __construct(
        private readonly DepartmentRepository $departments,
        private readonly EmployeeRepository $employees,
    ) {
    }

    public function index(): Collection
    {
        $departments = $this->departments->allOrderedByName();
        $employeeIdsByDepartment = $this->buildEmployeeIdsByDepartment();

        return $departments->map(fn (Department $dept) => array_merge($dept->toArray(), [
            'employee_ids' => $employeeIdsByDepartment[$dept->id] ?? [],
        ]));
    }

    private function buildEmployeeIdsByDepartment(): array
    {
        $map = [];

        foreach ($this->employees->allWithDepartmentIds() as $employee) {
            foreach ((array) ($employee->department_ids ?? []) as $departmentId) {
                $map[(int) $departmentId][] = $employee->id;
            }
        }

        return $map;
    }

    public function options(): Collection
    {
        return $this->departments->options();
    }

    public function create(array $validated): Department
    {
        return $this->departments->create($validated);
    }

    public function show(Department $department): Department
    {
        return $this->departments->findWithBasicRelations($department);
    }

    public function update(Department $department, array $validated, bool $employeeIdsProvided): Department
    {
        $dto = DepartmentDTO::fromValidated($validated, $employeeIdsProvided);

        $updated = $this->departments->update($department, $dto->attributes);

        if ($dto->employeeIdsProvided) {
            $this->syncEmployeeAssignments((int) $department->id, $dto->employeeIds ?? []);
            $updated = $updated->fresh(['head', 'division', 'parent', 'parentDivision']);
        }

        return $updated;
    }

    private function syncEmployeeAssignments(int $departmentId, array $employeeIds): void
    {
        $newEmployeeIds = collect($employeeIds)->map(fn ($id) => (int) $id)->all();

        $this->employees->detachDepartmentFromEmployeesExcept($departmentId, $newEmployeeIds);
        $this->employees->attachDepartmentToEmployees($departmentId, $newEmployeeIds);
    }

    public function delete(Department $department): void
    {
        $this->employees->removeDepartmentFromAllEmployees((int) $department->id);
        $this->departments->delete($department);
    }
}