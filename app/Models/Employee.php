<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\EmployeeHierarchy;
use App\Models\EmployeeInfo;
use App\Models\Division;
use App\Models\StepIncrement;
use App\Models\EmployeeAssignment;
use App\Models\SkillAssessment;
use App\Models\DevelopmentPlan;
use App\Models\CosPosition;
use App\Models\ConsultantPosition;

class Employee extends Model
{
    protected $fillable = [
        'employee_number',
        'role_position',
        'division_id',
        'department_ids',
        'prefix',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'title',
        'position_designation',
        'employment_type',
        'employment_status',
        'cos_position_id',
        'consultant_position_id',
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

    /*
    |--------------------------------------------------------------------------
    | COMPUTED ATTRIBUTES
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | ASSIGNMENT RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function assignments()
    {
        return $this->hasMany(EmployeeAssignment::class);
    }

    /**
     * The single active primary assignment.
     * Source of truth for current position slot + step.
     */
    public function primaryAssignment()
    {
        return $this->hasOne(EmployeeAssignment::class)
            ->whereNull('end_date')
            ->where('is_primary', true)
            ->latestOfMany('start_date');
    }

    /**
     * Alias for backwards compatibility.
     */
    public function activeAssignment()
    {
        return $this->primaryAssignment();
    }

    /*
    |--------------------------------------------------------------------------
    | HIERARCHY RELATIONSHIPS
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

    /*
    |--------------------------------------------------------------------------
    | EMPLOYMENT TYPE RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    /**
     * COS (Contract of Service) position relationship.
     */
    public function cosPosition()
    {
        return $this->belongsTo(CosPosition::class, 'cos_position_id');
    }

    /**
     * Consultant position relationship.
     */
    public function consultantPosition()
    {
        return $this->belongsTo(ConsultantPosition::class, 'consultant_position_id');
    }

    /*
    |--------------------------------------------------------------------------
    | SKILL RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function skillAssessments()
    {
        return $this->hasMany(SkillAssessment::class);
    }

    public function latestAssessment()
    {
        return $this->hasOne(SkillAssessment::class)
            ->where('status', '!=', 'draft')
            ->latestOfMany('submitted_at');
    }

    public function developmentPlans()
    {
        return $this->hasMany(DevelopmentPlan::class);
    }

    public function activeDevelopmentPlans()
    {
        return $this->hasMany(DevelopmentPlan::class)
            ->whereIn('status', ['planned', 'in_progress']);
    }

    /*
    |--------------------------------------------------------------------------
    | SALARY HISTORIES
    |--------------------------------------------------------------------------
    */

    public function salaryHistories()
    {
        return $this->hasMany(EmployeeSalaryHistory::class);
    }

    /*
    |--------------------------------------------------------------------------
    | CONVENIENCE ACCESSORS
    |--------------------------------------------------------------------------
    */

    /**
     * Current position slot via active assignment (Plantilla only).
     */
    public function getCurrentPlantillaPositionAttribute(): ?PlantillaPosition
    {
        return $this->primaryAssignment?->plantillaPosition;
    }

    /**
     * Current step increment via active assignment (with fallback to position default).
     * Only applies to Plantilla employees.
     */
    public function getCurrentStepIncrementAttribute(): ?StepIncrement
    {
        return $this->primaryAssignment?->effectiveStepIncrement();
    }

    /**
     * Get position title based on employment type.
     * Works for Plantilla, COS, and Consultant employees.
     */
    public function getPositionTitleAttribute(): ?string
    {
        $type = strtolower(trim($this->employment_type ?? ''));

        if ($type === 'plantilla') {
            return $this->primaryAssignment?->plantillaPosition?->item?->title
                ?? $this->primaryAssignment?->plantillaPosition?->position_title
                ?? null;
        }

        if ($type === 'contract of service' || $type === 'cos') {
            return $this->cosPosition?->title ?? null;
        }

        if ($type === 'consultant') {
            return $this->consultantPosition?->title ?? null;
        }

        return null;
    }

    public function getSkillScoreAttribute(): ?float
    {
        return $this->latestAssessment?->average_score;
    }

    public function getSkillGapCountAttribute(): ?int
    {
        return $this->latestAssessment?->gap_count;
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeSearch($query, string $term)
    {
        $term = strtolower(trim($term));

        return $query->where(function ($q) use ($term) {
            $q->whereRaw('LOWER(first_name) LIKE ?', ["%{$term}%"])
              ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$term}%"])
              ->orWhereRaw('LOWER(employee_number) LIKE ?', ["%{$term}%"]);
        });
    }

    public function scopePlantilla($query)
    {
        return $query->where('employment_type', 'Plantilla');
    }

    public function scopeCos($query)
    {
        return $query->where('employment_type', 'Contract of Service');
    }

    public function scopeConsultant($query)
    {
        return $query->where('employment_type', 'Consultant');
    }

    public function scopeWithAssessment($query)
    {
        return $query->whereHas('skillAssessments', fn ($q) =>
            $q->where('status', '!=', 'draft')
        );
    }

    public function scopeWithGaps($query)
    {
        return $query->whereHas('skillAssessments', fn ($q) =>
            $q->where('status', '!=', 'draft')
              ->where('gap_count', '>', 0)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DEPARTMENT ASSIGNMENT RELATIONSHIPS (NEW)
    |--------------------------------------------------------------------------
    */

    /**
     * All department assignments (current and historical).
     */
    public function departmentAssignments()
    {
        return $this->hasMany(EmployeeDepartmentAssignment::class);
    }

    /**
     * The primary active department assignment.
     */
    public function primaryDepartmentAssignment()
    {
        return $this->hasOne(EmployeeDepartmentAssignment::class)
            ->where('is_primary', true)
            ->whereNull('end_date');
    }

    /**
     * All active department assignments (for staff with multiple depts).
     */
    public function activeDepartmentAssignments()
    {
        return $this->departmentAssignments()
            ->whereNull('end_date')
            ->orderBy('is_primary', 'desc');
    }

    /*
    |--------------------------------------------------------------------------
    | CONVENIENCE ACCESSORS (NEW)
    |--------------------------------------------------------------------------
    */

    /**
     * Get the primary department (via assignment).
     */
    public function getPrimaryDepartmentAttribute(): ?Department
    {
        return $this->primaryDepartmentAssignment?->department;
    }

    /**
     * Get the primary department head (via assignment).
     */
    public function getPrimaryDepartmentHeadAttribute(): ?Employee
    {
        return $this->primaryDepartmentAssignment?->effective_head;
    }

    /**
     * Get all departments this employee is assigned to (active only).
     */
    public function getAssignedDepartmentsAttribute()
    {
        return $this->activeDepartmentAssignments
            ->map(fn ($a) => $a->department)
            ->unique('id')
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER
    |--------------------------------------------------------------------------
    */

    public function isPlantilla(): bool
    {
        return strtolower(trim($this->employment_type ?? '')) === 'plantilla';
    }

    public function isCos(): bool
    {
        return in_array(strtolower(trim($this->employment_type ?? '')), ['contract of service', 'cos']);
    }

    public function isConsultant(): bool
    {
        return strtolower(trim($this->employment_type ?? '')) === 'consultant';
    }
}