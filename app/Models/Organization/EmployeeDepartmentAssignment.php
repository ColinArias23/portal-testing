<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Concerns\AutoUppercase;
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

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function departmentHead()
    {
        return $this->belongsTo(Employee::class, 'department_head_id');
    }

    public function getIsActiveAttribute(): bool
    {
        $isNotEnded = is_null($this->end_date);
        $hasStarted = is_null($this->start_date) || 
                      $this->start_date <= now()->toDateString();

        return $isNotEnded && $hasStarted;
    }

    public function getEffectiveHeadAttribute(): ?Employee
    {
        if ($this->relationLoaded('departmentHead') && $this->departmentHead) {
            return $this->departmentHead;
        }

        if ($this->relationLoaded('department') && $this->department) {
            return $this->department->head;
        }

        return $this->departmentHead ?? $this->department->head;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('end_date')
            ->where(function ($q) {
                $q->whereNull('start_date')
                  ->orWhere('start_date', '<=', now()->toDateString());
            });
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeForDivision(Builder $query, int $divisionId): Builder
    {
        return $query->whereHas('department', fn ($q) =>
            $q->where('division_id', $divisionId)
        );
    }

    protected static function booted(): void
    {
        static::creating(function (self $assignment) {
            if ($assignment->is_primary) {
                static::where('employee_id', $assignment->employee_id)
                    ->where('is_primary', true)
                    ->whereNull('end_date')
                    ->update(['is_primary' => false]);
            }
        });

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