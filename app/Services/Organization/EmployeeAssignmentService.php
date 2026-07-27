<?php

namespace App\Services\Organization;

use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\PlantillaPosition;
use App\Repositories\Organization\EmployeeAssignmentRepository;
use App\Services\Recruitment\PlantillaPosting\PlantillaService;
use Illuminate\Support\Collection;

class EmployeeAssignmentService
{
    public function __construct(
        private readonly EmployeeAssignmentRepository $assignments,
        private readonly PlantillaService $plantillaService,
    ) {
    }

    public function listForEmployee(Employee $employee): Collection
    {
        return $this->assignments->forEmployee($employee->id)
            ->map(fn (EmployeeAssignment $a) => $this->assignments->format($a));
    }

    public function create(array $data): array
    {
        $employee = Employee::findOrFail($data['employee_id']);
        $position = PlantillaPosition::findOrFail($data['plantilla_position_id']);

        $assignment = $this->plantillaService->assign(
            position: $position,
            employee: $employee,
            stepIncrementId: isset($data['step_increment_id']) ? (int) $data['step_increment_id'] : null,
            startDate: $data['start_date'] ?? null,
        );

        $this->assignments->loadRelations($assignment);

        return $this->assignments->format($assignment);
    }

    public function end(EmployeeAssignment $assignment, array $data): void
    {
        $this->plantillaService->vacate(
            assignment: $assignment,
            endDate: $data['end_date'] ?? null,
            remarks: $data['remarks'] ?? null,
        );
    }

    public function delete(EmployeeAssignment $assignment): void
    {
        $assignment->delete();
    }
}
