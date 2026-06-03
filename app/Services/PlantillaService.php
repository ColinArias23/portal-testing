<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\PlantillaPosition;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PlantillaService
{
    /**
     * ─────────────────────────────────────────────────────────────────────
     * ASSIGN
     * ─────────────────────────────────────────────────────────────────────
     * 
     * Assign an employee to a VACANT position.
     * Position must be assignable (not filled, not unfilled, not requiring approval).
     */
    public function assign(
        PlantillaPosition $position,
        Employee $employee,
        ?int $stepIncrementId = null,
        ?string $startDate = null,
    ): EmployeeAssignment {

        if (!$position->isAssignable()) {
            $status = $position->computed_status;
            throw new RuntimeException(
                "Position [{$position->position_slot_name}] cannot be assigned. " .
                "Current status: {$status}. " .
                ($status === 'UNFILLED'
                    ? "Approval is required before this position can be filled again."
                    : "Only VACANT positions can be assigned.")
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

        return DB::transaction(function () use ($position, $employee, $stepIncrementId, $startDate) {
            $assignment = EmployeeAssignment::create([
                'employee_id' => $employee->id,
                'plantilla_position_id' => $position->id,
                'step_increment_id' => $stepIncrementId,
                'is_primary' => true,
                'start_date' => $startDate ?? now()->toDateString(),
                'end_date' => null,
            ]);

            $position->fillPosition();

            return $assignment;
        });
    }

    /**
     * ─────────────────────────────────────────────────────────────────────
     * VACATE
     * ─────────────────────────────────────────────────────────────────────
     * 
     * End an employee's assignment to a position.
     * Position becomes UNFILLED and requires approval before reassignment.
     */
    public function vacate(
        EmployeeAssignment $assignment,
        ?string $endDate = null,
        ?string $remarks = null,
    ): PlantillaPosition {

        if (!$assignment->isActive()) {
            throw new RuntimeException(
                "Assignment #{$assignment->id} is not currently active."
            );
        }

        return DB::transaction(function () use ($assignment, $endDate, $remarks) {
            $assignment->update([
                'end_date' => $endDate ?? now()->toDateString(),
            ]);

            $position = $assignment->plantillaPosition;
            $position->vacatePosition($remarks);

            return $position->fresh();
        });
    }

    /**
     * ─────────────────────────────────────────────────────────────────────
     * TRANSFER
     * ─────────────────────────────────────────────────────────────────────
     * 
     * Move employee from one position to another.
     * Source position → UNFILLED (requires approval before reuse)
     * Destination must be VACANT
     */
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
            $status = $toPosition->computed_status;
            throw new RuntimeException(
                "Destination position [{$toPosition->position_slot_name}] is not available. " .
                "Status: {$status}." .
                ($status === 'UNFILLED'
                    ? " Approval is required before this position can be filled."
                    : "")
            );
        }

        return DB::transaction(function () use ($fromAssignment, $toPosition, $stepIncrementId, $transferDate) {
            $date = $transferDate ?? now()->toDateString();
            $employee = $fromAssignment->employee;
            $fromPosition = $fromAssignment->plantillaPosition;

            // Close old assignment
            $fromAssignment->update(['end_date' => $date]);

            // Lock old position — needs approval before reuse
            $fromPosition->vacatePosition("Transferred to [{$toPosition->position_slot_name}]");

            // Open new assignment
            $newAssignment = EmployeeAssignment::create([
                'employee_id' => $employee->id,
                'plantilla_position_id' => $toPosition->id,
                'step_increment_id' => $stepIncrementId ?? $fromAssignment->step_increment_id,
                'is_primary' => true,
                'start_date' => $date,
                'end_date' => null,
            ]);

            $toPosition->fillPosition();

            return $newAssignment;
        });
    }

    /**
     * ─────────────────────────────────────────────────────────────────────
     * APPROVE
     * ─────────────────────────────────────────────────────────────────────
     * 
     * Approve an UNFILLED position and reopen it for assignment.
     * UNFILLED → VACANT
     */
    public function approve(PlantillaPosition $position): PlantillaPosition
    {
        if ($position->status !== 'UNFILLED') {
            throw new RuntimeException(
                "Position [{$position->position_slot_name}] is not UNFILLED — nothing to approve. " .
                "Current status: {$position->status}."
            );
        }

        if ($position->isFilled()) {
            throw new RuntimeException(
                "Position [{$position->position_slot_name}] still has an active occupant."
            );
        }

        $position->approveForRefilling();

        return $position->fresh();
    }

    /**
     * ─────────────────────────────────────────────────────────────────────
     * FORCE UNLOCK (Admin override)
     * ─────────────────────────────────────────────────────────────────────
     * 
     * Admin-only: Override approval requirement and reopen position.
     * Intended for urgent staffing situations.
     */
    public function forceUnlock(PlantillaPosition $position, string $reason = ''): PlantillaPosition
    {
        $position->approveForRefilling();

        if ($reason) {
            $position->update(['vacancy_notes' => "Force unlocked: {$reason}"]);
        }

        return $position->fresh();
    }

    /**
     * ─────────────────────────────────────────────────────────────────────
     * GET COMPATIBLE POSITIONS
     * ─────────────────────────────────────────────────────────────────────
     * 
     * Get positions that an employee can be assigned to.
     * Compatible = VACANT, assignable, and with matching salary grade if required.
     */
    public function getCompatiblePositions(
        Employee $employee,
        ?int $divisionId = null,
        ?int $salaryGradeId = null,
    ) {
        $query = PlantillaPosition::assignable();

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

    /**
     * ─────────────────────────────────────────────────────────────────────
     * GET CURRENT ASSIGNMENT
     * ─────────────────────────────────────────────────────────────────────
     * 
     * Get the current active assignment for an employee.
     */
    public function getCurrentAssignment(Employee $employee): ?EmployeeAssignment
    {
        return $employee->assignments()
            ->where('is_primary', true)
            ->whereNull('end_date')
            ->with('plantillaPosition.salaryGrade')
            ->first();
    }

    /**
     * ─────────────────────────────────────────────────────────────────────
     * GET ASSIGNMENT HISTORY
     * ─────────────────────────────────────────────────────────────────────
     * 
     * Get all past and current assignments for an employee.
     */
    public function getAssignmentHistory(Employee $employee)
    {
        return $employee->assignments()
            ->where('is_primary', true)
            ->with(['plantillaPosition', 'stepIncrement'])
            ->orderBy('start_date', 'desc')
            ->get();
    }

    /**
     * ─────────────────────────────────────────────────────────────────────
     * CHECK OVERSTAFFING
     * ─────────────────────────────────────────────────────────────────────
     * 
     * Detect positions with multiple active assignments.
     * Useful for audit and compliance checks.
     */
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

    /**
     * ─────────────────────────────────────────────────────────────────────
     * CHECK VACANT POSITIONS BY ITEM
     * ─────────────────────────────────────────────────────────────────────
     * 
     * Get summary of vacancy by plantilla item.
     */
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
