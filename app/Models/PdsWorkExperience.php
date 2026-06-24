<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoUppercase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdsWorkExperience extends Model
{

    use AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
    protected $table = 'pds_work_experiences';
    protected $fillable = [
        'employee_id',
        'date_from', 
        'date_to',
        'position', 
        'department',
        'monthly_salary', 
        'salary_grade', 
        'appointment_status', 
        'govt_service',
        'order',
    ];
    protected $casts = [
        'date_from'      => 'date:Y-m-d',
        'date_to'        => 'date:Y-m-d',
        'monthly_salary' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}