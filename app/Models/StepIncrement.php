<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StepIncrement extends Model
{
    protected $fillable = [
        'salary_grade_id',
        'step',
        'monthly_salary',
        'annual_salary',
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

    /**
     * Employees currently assigned at this step.
     */
    public function assignments()
    {
        return $this->hasMany(EmployeeAssignment::class);
    }
}