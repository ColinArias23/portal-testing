<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlantillaItem extends Model
{
    protected $fillable = [
        'item_number',
        'status',
        'salary_grade_id',
        'title',
        'description',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function salaryGrade()
    {
        return $this->belongsTo(SalaryGrade::class);
    }

    public function assignments()
    {
        return $this->hasMany(EmployeeAssignment::class);
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVE ASSIGNMENTS
    |--------------------------------------------------------------------------
    */

    public function activeAssignments()
    {
        return $this->assignments()->whereNull('end_date');
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK IF FILLED
    |--------------------------------------------------------------------------
    */

    public function isFilled(): bool
    {
        return $this->activeAssignments()->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK IF VACANT
    |--------------------------------------------------------------------------
    */

    public function isVacant(): bool
    {
        return !$this->activeAssignments()->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | DETECT OVERSTAFF
    |--------------------------------------------------------------------------
    */

    public function isOverstaffed(): bool
    {
        return $this->activeAssignments()->count() > 1;
    }

    /*
    |--------------------------------------------------------------------------
    | AUTO STATUS
    |--------------------------------------------------------------------------
    */

    public function getComputedStatusAttribute()
    {
        if ($this->isOverstaffed()) {
            return 'OVERSTAFFED';
        }

        if ($this->isFilled()) {
            return 'FILLED';
        }

        return 'VACANT';
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeVacant($query)
    {
        return $query->whereDoesntHave('activeAssignments');
    }

    public function scopeFilled($query)
    {
        return $query->whereHas('activeAssignments');
    }

    public function scopeOverstaffed($query)
    {
        return $query->withCount(['activeAssignments'])
            ->having('active_assignments_count', '>', 1);
    }
}