<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    protected $fillable = ['department_id', 'code', 'name', 'description', 'head_employee_id'];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'division_employee')
            ->withPivot(['is_primary'])
            ->withTimestamps();
    }
}
