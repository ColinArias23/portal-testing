<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeInfo extends Model
{
    protected $table = 'employee_info';

    protected $fillable = [
        'employee_id',
        'email',
        'contact',
        'address',
        'birthdate',
        'gender',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
