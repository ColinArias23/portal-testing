<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'division_id',
        'parent_id',
        'type',
        'code',
        'name',
        'description',
        'head_employee_id',
    ];

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function head()
    {
        return $this->belongsTo(Employee::class, 'head_employee_id');
    }

    public function plantillaPositions()
    {
        return $this->hasManyThrough(
            PlantillaPosition::class,
            Division::class,
            'id',
            'division_id',
            'division_id',
            'id'
        );
    }

    /**
     * All employees assigned to this department (current).
     */
    public function assignedEmployees()
    {
        return $this->hasMany(EmployeeDepartmentAssignment::class)
            ->whereNull('end_date');
    }

    /**
     * Primary employees (should be one department head).
     */
    public function primaryEmployees()
    {
        return $this->assignedEmployees()->where('is_primary', true);
    }
}