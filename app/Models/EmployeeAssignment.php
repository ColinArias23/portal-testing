<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeAssignment extends Model
{
    protected $fillable = [
        'employee_id',
        'plantilla_position_id',   // FK → plantilla_positions (the slot)
        'step_increment_id',
        'is_primary',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The actual slot (plantilla_positions row).
     */
    public function plantillaPosition()
    {
        return $this->belongsTo(PlantillaPosition::class, 'plantilla_position_id');
    }

    /**
     * Convenience: reach the master item through the slot.
     */
    public function plantillaItem()
    {
        return $this->hasOneThrough(
            PlantillaItem::class,
            PlantillaPosition::class,
            'id',                    // plantilla_positions.id
            'id',                    // plantilla_items.id
            'plantilla_position_id', // employee_assignments.plantilla_position_id
            'plantilla_item_id'      // plantilla_positions.plantilla_item_id
        );
    }

    public function stepIncrement()
    {
        return $this->belongsTo(StepIncrement::class);
    }

    /*
    |--------------------------------------------------------------------------
    | OBSERVER — auto UNFILLED when last occupant leaves
    |
    | Fires whenever end_date is written on a primary assignment.
    | If no other active assignment covers the same slot,
    | it is immediately locked (requires_approval = true, status = UNFILLED).
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        static::updated(function (self $assignment) {
            if (!$assignment->wasChanged('end_date')) return;
            if (!$assignment->end_date)               return;
            if (!$assignment->is_primary)             return;

            $position = $assignment->plantillaPosition;
            if (!$position) return;

            $stillOccupied = static::where('plantilla_position_id', $position->id)
                ->whereNull('end_date')
                ->exists();

            if (!$stillOccupied) {
                $position->markUnfilled(
                    'Automatically locked after employee vacated the position.'
                );
            }
        });
    }
}