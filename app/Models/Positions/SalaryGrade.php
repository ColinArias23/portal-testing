<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Concerns\AutoUppercase;

class SalaryGrade extends Model
{

    use AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
    protected $fillable = [
        'salary_grade',
        'monthly_salary',
        'annual_salary',
    ];

    public function stepIncrements()
    {
        return $this->hasMany(StepIncrement::class)->orderBy('step');
    }

    public function plantillaItems()
    {
        return $this->hasMany(PlantillaItem::class);
    }
}