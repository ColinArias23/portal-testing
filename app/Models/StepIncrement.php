<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StepIncrement extends Model
{
    protected $fillable = [
        'salary_grade_id',
        'step',
        'description',
        'increment_amount',
    ];

    public function salaryGrade()
    {
        return $this->belongsTo(SalaryGrade::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
