<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoUppercase;

class StepIncrement extends Model
{

    use AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
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