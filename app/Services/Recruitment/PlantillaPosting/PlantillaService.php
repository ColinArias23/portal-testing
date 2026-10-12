<?php

namespace App\Services\Recruitment\PlantillaPosting;

use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\PlantillaPosition;
use App\Models\PlantillaPositionHistory;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PlantillaService
{
    public function assign(
        PlantillaPosition $position,
        Employee $employee,
        ?int $stepIncrementId = null,
        ?string $startDate = null,
        string $event = PlantillaPositionHistory::ASSIGNED,
        ?PlantillaPosition $fromPosition = null,
        ?int $applicationId = null,
    ): EmployeeAssignment {

        if (!$position->isAssignable()) {
            throw new RuntimeException(
                "Position [{$position->position_slot_name}] cannot be assigned. " .
                "Current status: {$position->computed_status}. " .
                "Only UNFILLED positions can be assigned."
            );
        }

        $alreadyOccupied = EmployeeAssignment::where('plantilla_position_id', $position->id)
            ->whereNull('end_date')
            ->exists();

        if ($alreadyOccupied) {
            throw new RuntimeException(
                "Position [{$position->position_slot_name}] already has an active assignment."
            );
        }

        $existing = EmployeeAssignment::where('employee_id', $employee->id)
            ->where('is_primary', true)
            ->whereNull('end_date')
            ->first();

        if ($existing && $existing->plantilla_position_id !== $position->id) {
            throw new RuntimeException(
                "Employee [{$employee->full_name}] already has an active assignment to " .
                "[{$existing->plantillaPosition->position_slot_name}]. " .
                "Use transfer() to move them to a different position."
            );
        }

        return DB::transaction(function () use ($position, $employee, $stepIncrementId, $startDate, $event, $fromPosition, $applicationId) {
            $date = $startDate ?? now()->toDateString();

            $assignment = EmployeeAssignment::create([
                'employee_id' => $employee->id,
                'plantilla_position_id' => $position->id,
                'step_increment_id' => $stepIncrementId,
                'is_primary' => true,
                'start_date' => $date,
                'end_date' => null,
            ]);

            $position->fillPosition();

            $this->recordHistory($position, $employee, $assignment, $event, $date, $fromPosition, $applicationId);

            return $assignment;
        });
    }

    public function vacate(
        EmployeeAssignment $assignment,
        ?string $endDate = null,
        ?string $remarks = null,
        ?PlantillaPosition $relatedPosition = null,
        ?int $applicationId = null,
    ): PlantillaPosition {

        if (!$assignment->isActive()) {
            throw new RuntimeException(
                "Assignment #{$assignment->id} is not currently active."
            );
        }

        return DB::transaction(function () use ($assignment, $endDate, $remarks, $relatedPosition, $applicationId) {
            $date = $endDate ?? now()->toDateString();

            $assignment->update(['end_date' => $date]);

            $position = $assignment->plantillaPosition;
            $position->vacatePosition($remarks);

            $this->recordHistory(
                $position,
                $assignment->employee,
                $assignment,
                PlantillaPositionHistory::VACATED,
                $date,
                $relatedPosition,
                $applicationId,
                $remarks,
            );

            return $position->fresh();
        });
    }

    public function transfer(
        EmployeeAssignment $fromAssignment,
        PlantillaPosition $toPosition,
        ?int $stepIncrementId = null,
        ?string $transferDate = null,
    ): EmployeeAssignment {

        if (!$fromAssignment->isActive()) {
            throw new RuntimeException(
                "Source assignment #{$fromAssignment->id} is not currently active."
            );
        }

        if (!$toPosition->isAssignable()) {
            throw new RuntimeException(
                "Destination position [{$toPosition->position_slot_name}] is not available. " .
                "Status: {$toPosition->computed_status}."
            );
        }

        return DB::transaction(function () use ($fromAssignment, $toPosition, $stepIncrementId, $transferDate) {
            $date = $transferDate ?? now()->toDateString();
            $employee = $fromAssignment->employee;
            $fromPosition = $fromAssignment->plantillaPosition;

            $fromAssignment->update(['end_date' => $date]);

            $remarks = "Transferred to [{$toPosition->position_slot_name}]";
            $fromPosition->vacatePosition($remarks);

            $this->recordHistory(
                $fromPosition,
                $employee,
                $fromAssignment,
                PlantillaPositionHistory::VACATED,
                $date,
                $toPosition,
                null,
                $remarks,
            );

            $newAssignment = EmployeeAssignment::create([
                'employee_id' => $employee->id,
                'plantilla_position_id' => $toPosition->id,
                'step_increment_id' => $stepIncrementId ?? $fromAssignment->step_increment_id,
                'is_primary' => true,
                'start_date' => $date,
                'end_date' => null,
            ]);

            $toPosition->fillPosition();

            $this->recordHistory(
                $toPosition,
                $employee,
                $newAssignment,
                PlantillaPositionHistory::TRANSFERRED,
                $date,
                $fromPosition,
            );

            return $newAssignment;
        });
    }

    public function getCompatiblePositions(
        Employee $employee,
        ?int $divisionId = null,
        ?int $salaryGradeId = null,
    ) {
        $query = PlantillaPosition::unfilled();

        if ($divisionId) {
            $query->forDivision($divisionId);
        }

        if ($salaryGradeId) {
            $query->where('salary_grade_id', $salaryGradeId);
        }

        return $query
            ->with(['division', 'salaryGrade', 'stepIncrement'])
            ->orderBy('base_item_number')
            ->orderBy('slot_number')
            ->get();
    }

    public function getCurrentAssignment(Employee $employee): ?EmployeeAssignment
    {
        return $employee->assignments()
            ->where('is_primary', true)
            ->whereNull('end_date')
            ->with('plantillaPosition.salaryGrade')
            ->first();
    }

    public function getAssignmentHistory(Employee $employee)
    {
        return $employee->assignments()
            ->where('is_primary', true)
            ->with(['plantillaPosition', 'stepIncrement'])
            ->orderBy('start_date', 'desc')
            ->get();
    }

    private function recordHistory(
        PlantillaPosition $position,
        ?Employee $employee,
        EmployeeAssignment $assignment,
        string $event,
        string $date,
        ?PlantillaPosition $relatedPosition = null,
        ?int $applicationId = null,
        ?string $remarks = null,
    ): void {
        PlantillaPositionHistory::create([
            'plantilla_position_id' => $position->id,
            'position_label' => $position->position_slot_name,
            'employee_id' => $employee?->id,
            'employee_name' => $employee?->full_name,
            'event_type' => $event,
            'related_position_id' => $relatedPosition?->id,
            'related_position_label' => $relatedPosition?->position_slot_name,
            'plantilla_posting_application_id' => $applicationId,
            'employee_assignment_id' => $assignment->id,
            'effective_date' => $date,
            'remarks' => $remarks,
        ]);
    }

    public function getOverstaffedPositions()
    {
        return PlantillaPosition::overstaffed()
            ->with([
                'activeAssignments.employee',
                'division',
                'salaryGrade',
            ])
            ->orderBy('base_item_number')
            ->orderBy('slot_number')
            ->get();
    }

    public function getVacancySummaryByItem()
    {
        return PlantillaPosition::selectRaw(
            'base_item_number, title, ' .
            'COUNT(*) as total_slots, ' .
            'SUM(CASE WHEN status = "FILLED" THEN 1 ELSE 0 END) as filled_slots'
        )
            ->groupBy('base_item_number', 'title')
            ->orderBy('base_item_number')
            ->get()
            ->map(fn($item) => [
                'base_item_number' => $item->base_item_number,
                'title' => $item->title,
                'total_slots' => $item->total_slots,
                'filled_slots' => $item->filled_slots,
                'vacant_slots' => $item->total_slots - $item->filled_slots,
                'occupancy_rate' => number_format(($item->filled_slots / $item->total_slots) * 100, 2) . '%',
            ]);
    }
}
