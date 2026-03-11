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
        'department_id',
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

    protected $appends = ['full_name'];

    /*
    |--------------------------------------------------------------------------
    | AUTO CREATE EMPLOYEE ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {

        /*
        |--------------------------------------------------------------------------
        | WHEN EMPLOYEE CREATED
        |--------------------------------------------------------------------------
        */

        static::created(function ($employee) {

            // Prevent 2 employees in same plantilla
            $exists = EmployeeAssignment::where('plantilla_item_id', $employee->plantilla_item_id)
                ->whereNull('end_date')
                ->exists();

            if ($exists) {
                throw new \Exception("Plantilla item already occupied.");
            }

            EmployeeAssignment::create([
                'employee_id' => $employee->id,
                'plantilla_item_id' => $employee->plantilla_item_id,
                'start_date' => now(),
                'is_primary' => true
            ]);
        });


        /*
        |--------------------------------------------------------------------------
        | WHEN PLANTILLA CHANGES
        |--------------------------------------------------------------------------
        */

        static::updated(function ($employee) {

            if (!$employee->wasChanged('plantilla_item_id')) {
                return;
            }

            $exists = EmployeeAssignment::where('plantilla_item_id', $employee->plantilla_item_id)
                ->whereNull('end_date')
                ->whereNull('end_date')
                ->exists();

            if ($exists) {
                throw new \Exception("Plantilla item already occupied.");
            }

            EmployeeAssignment::where('employee_id', $employee->id)
                ->whereNull('end_date')
                ->update([
                    'end_date' => now(),
                    'is_primary' => false
                ]);

            EmployeeAssignment::create([
                'employee_id' => $employee->id,
                'plantilla_item_id' => $employee->plantilla_item_id,
                'start_date' => now(),
                'is_primary' => true
            ]);
        });
    }

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
        return $this->hasOne(EmployeeInfo::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Department / Division
    |--------------------------------------------------------------------------
    */

    public function department()
    {
        return $this->belongsTo(\App\Models\Department::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Plantilla
    |--------------------------------------------------------------------------
    */

    public function plantillaItem()
    {
        return $this->belongsTo(PlantillaItem::class, 'plantilla_item_id');
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

    /*
    |--------------------------------------------------------------------------
    | Primary Assignment (used by EmployeeController)
    |--------------------------------------------------------------------------
    */
    public function primaryAssignment()
    {
        return $this->hasOne(EmployeeAssignment::class)
            ->whereNull('end_date')
            ->where('is_primary', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Active Assignment (optional alias)
    |--------------------------------------------------------------------------
    */
    public function activeAssignment()
    {
        return $this->primaryAssignment();
    }

    /*
    |--------------------------------------------------------------------------
    | Step Increment
    |--------------------------------------------------------------------------
    */

    public function stepIncrement()
    {
        return $this->belongsTo(StepIncrement::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Hierarchy
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */

    public function scopeSearch($query, $s)
    {
        return $query->where(function ($q) use ($s) {

            $q->where('employee_number', 'like', "%{$s}%")
              ->orWhere('first_name', 'like', "%{$s}%")
              ->orWhere('middle_name', 'like', "%{$s}%")
              ->orWhere('last_name', 'like', "%{$s}%")
              ->orWhere('role_position', 'like', "%{$s}%")
              ->orWhere('title', 'like', "%{$s}%");

        });
    }
}