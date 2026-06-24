<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoUppercase;
use Illuminate\Database\Eloquent\Builder;

class EmployeeDepartmentAssignment extends Model
{

    use AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
    protected $fillable = [
        'employee_id',
        'department_id',
        'is_primary',
        'department_head_id',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    protected $appends = [
        'is_active',
        'effective_head',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    /**
     * The employee assigned to this department.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The department this employee is assigned to.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The department head for this assignment (override).
     * If not set, falls back to the department's default head.
     */
    public function departmentHead()
    {
        return $this->belongsTo(Employee::class, 'department_head_id');
    }

    /*
    |--------------------------------------------------------------------------
    | COMPUTED ATTRIBUTES
    |--------------------------------------------------------------------------
    */

    /**
     * Check if this assignment is currently active.
     * 
     * @return bool
     */
    public function getIsActiveAttribute(): bool
    {
        $isNotEnded = is_null($this->end_date);
        $hasStarted = is_null($this->start_date) || 
                      $this->start_date <= now()->toDateString();

        return $isNotEnded && $hasStarted;
    }

    /**
     * Get the effective department head for this assignment.
     * Returns the override head if set, otherwise the department's default head.
     * 
     * @return Employee|null
     */
    public function getEffectiveHeadAttribute(): ?Employee
    {
        if ($this->relationLoaded('departmentHead') && $this->departmentHead) {
            return $this->departmentHead;
        }

        if ($this->relationLoaded('department') && $this->department) {
            return $this->department->head;
        }

        // Lazy load if not already loaded
        return $this->departmentHead ?? $this->department->head;
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Filter to only active assignments (not ended, has started).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('end_date')
            ->where(function ($q) {
                $q->whereNull('start_date')
                  ->orWhere('start_date', '<=', now()->toDateString());
            });
    }

    /**
     * Filter to only primary assignments.
     */
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    /**
     * Filter assignments for a specific employee.
     */
    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Filter assignments for a specific department.
     */
    public function scopeForDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Filter assignments by division through the department relationship.
     */
    public function scopeForDivision(Builder $query, int $divisionId): Builder
    {
        return $query->whereHas('department', fn ($q) =>
            $q->where('division_id', $divisionId)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | OBSERVER / MODEL EVENTS
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        /**
         * When creating a primary assignment, auto-unset other primaries.
         */
        static::creating(function (self $assignment) {
            if ($assignment->is_primary) {
                static::where('employee_id', $assignment->employee_id)
                    ->where('is_primary', true)
                    ->whereNull('end_date')
                    ->update(['is_primary' => false]);
            }
        });

        /**
         * When updating is_primary to true, auto-unset others.
         */
        static::updating(function (self $assignment) {
            if ($assignment->isDirty('is_primary') && $assignment->is_primary) {
                static::where('employee_id', $assignment->employee_id)
                    ->where('id', '!=', $assignment->id)
                    ->where('is_primary', true)
                    ->whereNull('end_date')
                    ->update(['is_primary' => false]);
            }
        });
    }
}