<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'division_id',
        'parent_id',
        'parent_division_id',
        'type',
        'code',
        'name',
        'description',
        'head_employee_id',
    ];

    public function setCodeAttribute($value)
    {
        $this->attributes['code'] = $value !== null ? strtoupper($value) : $value;
    }

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value !== null ? strtoupper($value) : $value;
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function head()
    {
        return $this->belongsTo(Employee::class, 'head_employee_id');
    }

    public function parent()
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    public function parentDivision()
    {
        return $this->belongsTo(Division::class, 'parent_division_id');
    }

    public function children()
    {
        return $this->hasMany(Department::class, 'parent_id');
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

    public function assignedEmployees()
    {
        return $this->hasMany(EmployeeDepartmentAssignment::class)
            ->whereNull('end_date');
    }

    public function primaryEmployees()
    {
        return $this->assignedEmployees()->where('is_primary', true);
    }
}