<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\EmployeeHierarchy;
use App\Models\EmployeeInfo;
use App\Models\Division;
use App\Models\PlantillaItem;
use App\Models\StepIncrement;

class Employee extends Model
{
    protected $fillable = [
        'employee_number',
        'role_position',
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
    | Avatar
    |--------------------------------------------------------------------------
    */
    // public function getAvatarUrlAttribute()
    // {
    //     return $this->avatar_url
    //         ? asset('storage/' . $this->avatar_url)
    //         : null;
    // }   
    public function getAvatarUrlAttribute($value)
    {
        if (!$value) {
            return null;
        }

        return asset('storage/' . $value);
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
    | Plantilla
    |--------------------------------------------------------------------------
    */

    public function plantillaItem()
    {
        return $this->belongsTo(PlantillaItem::class);
    }
    /*
    |--------------------------------------------------------------------------
    | Division
    |--------------------------------------------------------------------------
    */

    public function division()
    {
        return $this->belongsTo(Division::class);
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
    | Parent / Children (Many-to-Many via employee_hierarchy)
    |--------------------------------------------------------------------------
    */

    public function parents()
    {
        return $this->belongsToMany(
            Employee::class,
            'employee_hierarchy',
            'employee_id',  // current employee
            'parent_id'     // parent employee
        );
    }

    public function children()
    {
        return $this->belongsToMany(
            Employee::class,
            'employee_hierarchy',
            'parent_id',    // current employee as parent
            'employee_id'   // child employee
        );
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

    /*
    |--------------------------------------------------------------------------
    | Assignments
    |--------------------------------------------------------------------------
    */

    public function assignments()
    {
        return $this->hasMany(EmployeeAssignment::class);
    }

    public function activeAssignment()
    {
        return $this->hasOne(EmployeeAssignment::class)
                    ->whereNull('end_date')
                    ->where('is_primary', true);
    }

    public function primaryAssignment()
    {
        return $this->hasOne(EmployeeAssignment::class)
                    ->whereNull('end_date')
                    ->where('is_primary', true);
    }
}
