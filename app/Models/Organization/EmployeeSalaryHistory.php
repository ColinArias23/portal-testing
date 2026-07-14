<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Concerns\AutoUppercase;

class EmployeeSalaryHistory extends Model
{

    use AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
    protected $fillable = [
        'employee_id',
        'gross_salary',
        'annual_salary',
        'salary_grade',
        'step',
        'effective_date',
        'end_date',
        'notes'
    ];

    protected $casts = [
        'effective_date' => 'date',
        'end_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}