<?php

namespace App\Repositories\Organization;

use App\Models\Employee;
use App\Models\EmployeeDepartmentAssignment;
use Illuminate\Database\Eloquent\Collection;

class EmployeeDepartmentAssignmentRepository
{
    private const RELATIONS = ['department.division', 'departmentHead'];

    public function forEmployee(Employee $employee): Collection
    {
        return $employee->departmentAssignments()
            ->with(self::RELATIONS)
            ->orderByDesc('is_primary')
            ->orderByDesc('updated_at')
            ->get();
    }

    public function findActiveForEmployeeAndDepartment(int $employeeId, int $departmentId): ?EmployeeDepartmentAssignment
    {
        return EmployeeDepartmentAssignment::where('employee_id', $employeeId)
            ->where('department_id', $departmentId)
            ->whereNull('end_date')
            ->first();
    }

    public function demoteExistingPrimary(int $employeeId): void
    {
        EmployeeDepartmentAssignment::where('employee_id', $employeeId)
            ->where('is_primary', true)
            ->whereNull('end_date')
            ->update(['is_primary' => false]);
    }

    public function create(array $attributes): EmployeeDepartmentAssignment
    {
        return EmployeeDepartmentAssignment::create($attributes);
    }

    public function loadRelations(EmployeeDepartmentAssignment $assignment): EmployeeDepartmentAssignment
    {
        return $assignment->load(self::RELATIONS);
    }

    public function freshWithRelations(EmployeeDepartmentAssignment $assignment): EmployeeDepartmentAssignment
    {
        return $assignment->fresh(self::RELATIONS);
    }

    public function format(EmployeeDepartmentAssignment $assignment): array
    {
        return [
            'id'             => $assignment->id,
            'employee_id'    => $assignment->employee_id,
            'department_id'  => $assignment->department_id,
            'is_primary'     => $assignment->is_primary,
            'is_active'      => $assignment->is_active,
            'start_date'     => $assignment->start_date,
            'end_date'       => $assignment->end_date,

            'department' => [
                'id'       => $assignment->department->id,
                'code'     => $assignment->department->code,
                'name'     => $assignment->department->name,
                'division' => $assignment->department->division ? [
                    'id'   => $assignment->department->division->id,
                    'name' => $assignment->department->division->name,
                ] : null,
            ],

            'department_head' => $assignment->departmentHead ? [
                'id'        => $assignment->departmentHead->id,
                'full_name' => $assignment->departmentHead->full_name,
                'title'     => $assignment->departmentHead->title,
            ] : null,

            'effective_head' => $assignment->effective_head ? [
                'id'        => $assignment->effective_head->id,
                'full_name' => $assignment->effective_head->full_name,
                'title'     => $assignment->effective_head->title,
            ] : null,

            'created_at' => $assignment->created_at,
            'updated_at' => $assignment->updated_at,
        ];
    }
}
