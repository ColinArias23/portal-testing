<?php

namespace App\Services\Organization;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeDepartmentAssignment;
use App\Repositories\Organization\EmployeeDepartmentAssignmentRepository;
use Illuminate\Http\Request;

class EmployeeDepartmentAssignmentService
{
    public function __construct(
        private readonly EmployeeDepartmentAssignmentRepository $assignments,
    ) {
    }

    public function listForEmployee(Employee $employee): array
    {
        return $this->assignments->forEmployee($employee)
            ->map(fn ($a) => $this->assignments->format($a))
            ->all();
    }

    public function create(Employee $employee, array $data, Request $request): array
    {
        $existing = $this->assignments->findActiveForEmployeeAndDepartment(
            $employee->id,
            $data['department_id']
        );

        if ($existing) {
            return [
                'status' => 422,
                'body' => [
                    'message'    => 'Employee is already assigned to this department.',
                    'assignment' => $this->assignments->format($existing),
                ],
            ];
        }

        if ($data['is_primary'] ?? false) {
            $this->assignments->demoteExistingPrimary($employee->id);
        }

        $assignment = $this->assignments->create([
            'employee_id'        => $employee->id,
            'department_id'      => $data['department_id'],
            'is_primary'         => $data['is_primary'] ?? true,
            'department_head_id' => $data['department_head_id'] ?? null,
            'start_date'         => $data['start_date'] ?? now()->toDateString(),
            'end_date'           => $data['end_date'] ?? null,
        ]);

        $this->logActivity(
            'employee_department_assignment.created',
            $employee,
            $assignment,
            $request,
            [
                'employee_id'   => $employee->id,
                'department_id' => $data['department_id'],
                'is_primary'    => $assignment->is_primary,
            ]
        );

        $this->assignments->loadRelations($assignment);

        return [
            'status' => 201,
            'body' => $this->assignments->format($assignment),
        ];
    }

    public function assertBelongsToEmployee(EmployeeDepartmentAssignment $assignment, Employee $employee): bool
    {
        return $assignment->employee_id === $employee->id;
    }

    public function update(
        Employee $employee,
        EmployeeDepartmentAssignment $assignment,
        array $data,
        Request $request
    ): array {
        $before = [
            'is_primary'         => $assignment->is_primary,
            'department_head_id' => $assignment->department_head_id,
            'end_date'           => $assignment->end_date,
        ];

        if (($data['is_primary'] ?? false) && ! $assignment->is_primary) {
            $this->assignments->demoteExistingPrimary($employee->id);
        }

        $assignment->update($data);

        $this->logActivity(
            'employee_department_assignment.updated',
            $employee,
            $assignment,
            $request,
            [
                'before' => $before,
                'after' => [
                    'is_primary'         => $assignment->is_primary,
                    'department_head_id' => $assignment->department_head_id,
                    'end_date'           => $assignment->end_date,
                ],
            ]
        );

        return $this->assignments->format($this->assignments->freshWithRelations($assignment));
    }

    public function end(Employee $employee, EmployeeDepartmentAssignment $assignment, Request $request): void
    {
        $assignment->update(['end_date' => now()->toDateString()]);

        $this->logActivity('employee_department_assignment.ended', $employee, $assignment, $request);
    }

    private function logActivity(
        string $action,
        Employee $employee,
        EmployeeDepartmentAssignment $assignment,
        Request $request,
        array $payload = []
    ): void {
        ActivityLog::record(
            action: $action,
            userId: $request->user()?->id,
            subjectType: EmployeeDepartmentAssignment::class,
            subjectId: $assignment->id,
            subjectLabel: "{$employee->full_name} → {$assignment->department->name}",
            payload: $payload,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}
