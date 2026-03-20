<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeHierarchy extends Model
{
    protected $table = 'employee_hierarchy';

    protected $fillable = [
        'employee_id',
        'parent_id',
    ];

    // Child employee
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    // Parent employee
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'parent_id');
    }
}