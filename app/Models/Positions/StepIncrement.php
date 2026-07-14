<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Concerns\AutoUppercase;

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

    public function salaryGrade()
    {
        return $this->belongsTo(SalaryGrade::class);
    }

    public function assignments()
    {
        return $this->hasMany(EmployeeAssignment::class);
    }
}