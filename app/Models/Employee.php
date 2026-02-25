<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\EmployeeHierarchy;
use App\Models\EmployeeInfo;

class Employee extends Model
{
    protected $fillable = [
        'employee_number',
        'role_position',
        'sg_level',
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
        'avatar',
        'border_color',
        'aligned',
        'expanded',
        'notes',
    ];

    protected $appends = ['full_name'];

    /*
    |--------------------------------------------------------------------------
    | Accessor
    |--------------------------------------------------------------------------
    */

    public function getFullNameAttribute()
    {
        return collect([
            $this->prefix,
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix
        ])->filter()->implode(' ');
    }

    /*
    |--------------------------------------------------------------------------
    | Employee Info
    |--------------------------------------------------------------------------
    */

    public function info()
    {
        return $this->hasOne(EmployeeInfo::class, 'employee_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Hierarchy (Using employee table)
    |--------------------------------------------------------------------------
    */

    // Where this employee is a child
    public function hierarchy()
    {
        return $this->hasMany(EmployeeHierarchy::class, 'employee_id');
    }

    // Where this employee is a parent
    public function parentHierarchy()
    {
        return $this->hasMany(EmployeeHierarchy::class, 'parent_id');
    }

    /*
    |--------------------------------------------------------------------------
    | StepIncrement
    |--------------------------------------------------------------------------
    */
    public function stepIncrement()
    {
        return $this->belongsTo(StepIncrement::class);
    }
}
