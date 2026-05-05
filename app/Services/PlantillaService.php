<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\PlantillaItem;
use App\Models\PlantillaPosition;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PlantillaService
{
    /*
    |--------------------------------------------------------------------------
    | ADD POSITION
    | Rule: plantilla_item must have available slots before a new
    | PlantillaPosition can be created under it.
    |--------------------------------------------------------------------------
    */

    public function addPosition(
        PlantillaItem $item,
        string        $itemNumber,
        ?string       $positionTitle   = null,
        ?int          $salaryGradeId   = null,
        ?int          $stepIncrementId = null,
    ): PlantillaPosition {

        if (!$item->hasAvailableSlots()) {
            throw new RuntimeException(
                "No available slots for [{$item->title}]. " .
                "All {$item->approved_slots} approved slot(s) are taken."
            );
        }

        // model creating() event also guards this — double safety
        return PlantillaPosition::create([
            'plantilla_item_id' => $item->id,
            'item_number'       => $itemNumber,
            'position_title'    => $positionTitle,
            'salary_grade_id'   => $salaryGradeId,
            'step_increment_id' => $stepIncrementId,
            'status'            => 'VACANT',
            'requires_approval' => false,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ASSIGN
    | Rule: only VACANT positions (requires_approval = false, not filled)
    | can be assigned to an employee.
    |--------------------------------------------------------------------------
    */

    public function assign(
        PlantillaPosition $position,
        Employee          $employee,
        ?int              $stepIncrementId = null,
        ?string           $startDate       = null,
    ): EmployeeAssignment {

        if (!$position->isAssignable()) {
            $status = $position->computed_status;
            throw new RuntimeException(
                "Position [{$position->item_number}] cannot be assigned. " .
                "Current status: {$status}. " .
                ($status === 'UNFILLED'
                    ? "City approval is required before this slot can be filled again."
                    : "Only VACANT slots can be assigned.")
            );
        }

        $alreadyOccupied = EmployeeAssignment::where('plantilla_position_id', $position->id)
            ->whereNull('end_date')
            ->exists();

        if ($alreadyOccupied) {
            throw new RuntimeException(
                "Position [{$position->item_number}] already has an active assignment."
            );
        }

        $existing = EmployeeAssignment::where('employee_id', $employee->id)
            ->where('is_primary', true)
            ->whereNull('end_date')
            ->first();

        if ($existing && $existing->plantilla_position_id !== $position->id) {
            throw new RuntimeException(
                "Employee [{$employee->full_name}] already has an active assignment. " .
                "Use transfer() to move them to a different position."
            );
        }

        return DB::transaction(function () use ($position, $employee, $stepIncrementId, $startDate) {
            $assignment = EmployeeAssignment::create([
                'employee_id'           => $employee->id,
                'plantilla_position_id' => $position->id,
                'step_increment_id'     => $stepIncrementId,
                'is_primary'            => true,
                'start_date'            => $startDate ?? now()->toDateString(),
                'end_date'              => null,
            ]);

            $position->update([
                'status'             => 'FILLED',
                'date_of_assumption' => $startDate ?? now()->toDateString(),
            ]);

            return $assignment;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | VACATE
    | Sets position to UNFILLED + requires_approval = true.
    | Position cannot be assigned again until approved.
    |--------------------------------------------------------------------------
    */

    public function vacate(
        EmployeeAssignment $assignment,
        ?string            $endDate = null,
        ?string            $remarks = null,
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

            $position->update([
                'status'             => 'UNFILLED',
                'requires_approval'  => true,
                'vacated_at'         => now(),
                'date_of_assumption' => null,
                'vacancy_notes'      => $remarks,
            ]);

            return $position->fresh();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | TRANSFER
    | Moves employee from one position to another.
    | Source → UNFILLED (locked, needs approval before reuse).
    | Destination must be VACANT.
    |--------------------------------------------------------------------------
    */

    public function transfer(
        EmployeeAssignment $fromAssignment,
        PlantillaPosition  $toPosition,
        ?int               $stepIncrementId = null,
        ?string            $transferDate    = null,
    ): EmployeeAssignment {

        if (!$fromAssignment->isActive()) {
            throw new RuntimeException(
                "Source assignment #{$fromAssignment->id} is not currently active."
            );
        }

        if (!$toPosition->isAssignable()) {
            $status = $toPosition->computed_status;
            throw new RuntimeException(
                "Destination position [{$toPosition->item_number}] is not available. " .
                "Status: {$status}." .
                ($status === 'UNFILLED'
                    ? " City approval is required before this slot can be filled."
                    : "")
            );
        }

        return DB::transaction(function () use ($fromAssignment, $toPosition, $stepIncrementId, $transferDate) {
            $date         = $transferDate ?? now()->toDateString();
            $employee     = $fromAssignment->employee;
            $fromPosition = $fromAssignment->plantillaPosition;

            // Close old assignment
            $fromAssignment->update(['end_date' => $date]);

            // Lock old position — needs city approval before reuse
            $fromPosition->update([
                'status'             => 'UNFILLED',
                'requires_approval'  => true,
                'vacated_at'         => now(),
                'date_of_assumption' => null,
            ]);

            // Open new assignment
            $newAssignment = EmployeeAssignment::create([
                'employee_id'           => $employee->id,
                'plantilla_position_id' => $toPosition->id,
                'step_increment_id'     => $stepIncrementId,
                'is_primary'            => true,
                'start_date'            => $date,
                'end_date'              => null,
            ]);

            $toPosition->update([
                'status'             => 'FILLED',
                'date_of_assumption' => $date,
            ]);

            return $newAssignment;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVE
    | City approval received — unlock the position for assignment.
    | UNFILLED → VACANT
    |--------------------------------------------------------------------------
    */

    public function approve(PlantillaPosition $position): PlantillaPosition
    {
        if ($position->status !== 'UNFILLED') {
            throw new RuntimeException(
                "Position [{$position->item_number}] is not UNFILLED — nothing to approve. " .
                "Current status: {$position->status}."
            );
        }

        if ($position->isFilled()) {
            throw new RuntimeException(
                "Position [{$position->item_number}] still has an active occupant."
            );
        }

        $position->approveForRefilling();

        return $position->fresh();
    }

    /*
    |--------------------------------------------------------------------------
    | FORCE UNLOCK (admin override)
    |--------------------------------------------------------------------------
    */

    public function forceUnlock(PlantillaPosition $position, string $reason = ''): PlantillaPosition
    {
        $position->approveForRefilling();

        return $position->fresh();
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE POSITION
    | Removes the position slot. If the parent item has no remaining
    | positions after deletion, the item is auto-deleted via model event.
    | Cannot delete FILLED or UNFILLED slots.
    |--------------------------------------------------------------------------
    */

    public function deletePosition(PlantillaPosition $position): void
    {
        if ($position->isFilled()) {
            throw new RuntimeException(
                "Cannot delete position [{$position->item_number}] — it is currently FILLED."
            );
        }

        if ($position->isUnfilled()) {
            throw new RuntimeException(
                "Cannot delete position [{$position->item_number}] — it is UNFILLED and awaiting approval."
            );
        }

        // model deleted() event will auto-delete parent item if no positions remain
        $position->delete();
    }
}