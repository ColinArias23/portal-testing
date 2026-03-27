<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\EmployeeHierarchy;
use App\Models\EmployeeInfo;
use App\Models\Division;
use App\Models\PlantillaItem;
use App\Models\StepIncrement;
use App\Models\EmployeeAssignment;

class Employee extends Model
{
    protected $fillable = [
        'employee_number',
        'role_position',
        'division_id',
        'department_ids',
        'plantilla_item_id',
        'step_increment_id',
        'prefix',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'title',
        'position_designation',
        'employment_type',
        'employment_status',
        'avatar_url',
        'border_color',
        'aligned',
        'expanded',
        'notes',
    ];

    protected $casts = [
        'role_position'        => 'array',
        'position_designation' => 'array',
        'department_ids'       => 'array',
        'title'                => 'array',
        'aligned'              => 'boolean',
        'expanded'             => 'boolean',
    ];

    protected $appends = ['full_name'];

    public function getFullNameAttribute(): string
    {
        return collect([
            $this->prefix,
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix,
        ])->filter()->implode(' ');
    }

    public function getRolePositionLabelAttribute(): string
    {
        return collect($this->role_position)->filter()->implode(', ');
    }

    public function info()
    {
        return $this->hasOne(EmployeeInfo::class);
    }

    public function user()
    {
        return $this->hasOne(\App\Models\User::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function plantillaItem()
    {
        return $this->belongsTo(PlantillaItem::class, 'plantilla_item_id');
    }

    public function assignments()
    {
        return $this->hasMany(EmployeeAssignment::class);
    }

    public function primaryAssignment()
    {
        return $this->hasOne(EmployeeAssignment::class)
            ->whereNull('end_date')
            ->where('is_primary', true);
    }

    public function activeAssignment()
    {
        return $this->primaryAssignment();
    }

    public function stepIncrement()
    {
        return $this->belongsTo(StepIncrement::class);
    }

    public function hierarchy()
    {
        return $this->hasMany(EmployeeHierarchy::class, 'employee_id');
    }

    public function parentHierarchy()
    {
        return $this->hasMany(EmployeeHierarchy::class, 'parent_id');
    }

    public function parents()
    {
        return $this->belongsToMany(
            Employee::class,
            'employee_hierarchy',
            'employee_id',
            'parent_id'
        );
    }

    public function children()
    {
        return $this->belongsToMany(
            Employee::class,
            'employee_hierarchy',
            'parent_id',
            'employee_id'
        );
    }

    public function salaryHistories()
    {
        return $this->hasMany(EmployeeSalaryHistory::class);
    }

    public function scopeSearch($query, $s)
    {
        return $query->where(function ($q) use ($s) {
        });
    }
}