<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoUppercase;
use Illuminate\Database\Eloquent\Builder;

class EmployeeAssignment extends Model
{

    use AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
    protected $fillable = [
        'employee_id',
        'plantilla_position_id',
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

    public function plantillaPosition()
    {
        return $this->belongsTo(PlantillaPosition::class, 'plantilla_position_id');
    }

    public function stepIncrement()
    {
        return $this->belongsTo(StepIncrement::class, 'step_increment_id');
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return is_null($this->end_date);
    }

    /*
    |--------------------------------------------------------------------------
    | EFFECTIVE STEP
    | Assignment-level step overrides the position's default step.
    |--------------------------------------------------------------------------
    */

    public function effectiveStepIncrement(): ?StepIncrement
    {
        return $this->stepIncrement
            ?? $this->plantillaPosition?->stepIncrement;
    }

    /*
    |--------------------------------------------------------------------------
    | OBSERVER
    | Auto-marks position UNFILLED when the last occupant leaves.
    |
    | NOTE: PlantillaService::vacate() already calls position->update() to
    | set UNFILLED. This observer is a safety net for any direct end_date
    | writes that bypass the service (e.g. bulk updates, seeder cleanup).
    | It is idempotent — calling markUnfilled() on an already-UNFILLED
    | position is harmless.
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        static::updated(function (self $assignment) {
            if (! $assignment->wasChanged('end_date')) return;
            if (! $assignment->end_date)               return;
            if (! $assignment->is_primary)             return;

            $position = $assignment->plantillaPosition;
            if (! $position) return;

            $stillOccupied = static::where('plantilla_position_id', $position->id)
                ->whereNull('end_date')
                ->exists();

            if (! $stillOccupied) {
                // Guard: only act if not already UNFILLED to avoid redundant writes
                if ($position->status !== 'UNFILLED') {
                    $position->markUnfilled(
                        'Automatically locked after employee vacated the position.'
                    );
                }
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('end_date');
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForPosition(Builder $query, int $positionId): Builder
    {
        return $query->where('plantilla_position_id', $positionId);
    }
}