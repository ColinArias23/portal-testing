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

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    /**
     * Each salary grade has up to 8 step increments (steps 1–8).
     */
    public function stepIncrements()
    {
        return $this->hasMany(StepIncrement::class)->orderBy('step');
    }

    public function plantillaItems()
    {
        return $this->hasMany(PlantillaItem::class);
    }
}