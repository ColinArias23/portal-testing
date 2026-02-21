<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryGrade extends Model
{
    protected $fillable = [
        'salary_grade',
        'monthly_salary',
        'annual_salary',
    ];

    public function stepIncrements()
    {
        return $this->hasMany(StepIncrement::class);
    }

    public function plantillaItems()
    {
        return $this->hasMany(PlantillaItem::class);
    }
}
