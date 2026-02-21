<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeHierarchy extends Model
{
    protected $fillable = [
        'parent_id',
        'child_id',
        'division_id',
    ];

    public function parent()
    {
        return $this->belongsTo(Employee::class, 'parent_id');
    }

    public function child()
    {
        return $this->belongsTo(Employee::class, 'child_id');
    }
}