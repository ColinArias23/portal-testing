<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeAssignment extends Model
{
    protected $fillable = [
        'employee_id',
        'plantilla_item_id',
        'step_increment_id',
        'is_primary',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_primary' => 'boolean',
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

    public function plantillaItem()
    {
        return $this->belongsTo(PlantillaItem::class);
    }

    public function stepIncrement()
    {
        return $this->belongsTo(StepIncrement::class);
    }

    /*
    |--------------------------------------------------------------------------
    | EFFECTIVE STEP
    |--------------------------------------------------------------------------
    */

    public function getEffectiveStepAttribute()
    {
        return $this->stepIncrement ?? $this->plantillaItem?->stepIncrement;
    }

    /*
    |--------------------------------------------------------------------------
    | EFFECTIVE SALARY GRADE
    |--------------------------------------------------------------------------
    */

    public function getSalaryGradeAttribute()
    {
        return $this->plantillaItem?->salaryGrade;
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->whereNull('end_date');
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /*
    |--------------------------------------------------------------------------
    | MODEL EVENTS
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {

        /*
        |-----------------------------------------------
        | PREVENT DUPLICATE PLANTILLA OCCUPANCY
        |-----------------------------------------------
        */

        static::creating(function ($assignment) {

            $occupied = self::where('plantilla_item_id', $assignment->plantilla_item_id)
                ->whereNull('end_date')
                ->exists();

            if ($occupied) {
                throw new \Exception('Plantilla item already occupied.');
            }

        });


        /*
        |-----------------------------------------------
        | WHEN ASSIGNMENT CREATED
        |-----------------------------------------------
        */

        static::created(function ($assignment) {

            $assignment->plantillaItem()
                ->update(['status' => 'FILLED']);

        });


        /*
        |-----------------------------------------------
        | WHEN ASSIGNMENT UPDATED
        |-----------------------------------------------
        */

        static::updated(function ($assignment) {

            if (!$assignment->wasChanged('end_date')) {
                return;
            }

            $hasActive = self::where('plantilla_item_id', $assignment->plantilla_item_id)
                ->whereNull('end_date')
                ->exists();

            if (!$hasActive) {
                $assignment->plantillaItem()
                    ->update(['status' => 'VACANT']);
            }

        });


        /*
        |-----------------------------------------------
        | WHEN ASSIGNMENT DELETED
        |-----------------------------------------------
        */

        static::deleted(function ($assignment) {

            $hasActive = self::where('plantilla_item_id', $assignment->plantilla_item_id)
                ->whereNull('end_date')
                ->exists();

            if (!$hasActive) {
                $assignment->plantillaItem()
                    ->update(['status' => 'VACANT']);
            }

        });

    }
}