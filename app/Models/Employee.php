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

    /**
     * All assignment records (history included).
     */
    public function assignments()
    {
        return $this->hasMany(EmployeeAssignment::class);
    }

    /**
     * The single active primary assignment.
     * This is the source of truth for current plantilla item + step.
     */
    public function primaryAssignment()
    {
        return $this->hasOne(EmployeeAssignment::class)
            ->whereNull('end_date')
            ->where('is_primary', true);
    }

    /**
     * Alias kept for backwards compatibility with existing code.
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
    | SKILL RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    /**
     * All skill assessments for this employee.
     */
    public function skillAssessments()
    {
        return $this->hasMany(SkillAssessment::class);
    }

    /**
     * The latest submitted skill assessment.
     */
    public function latestAssessment()
    {
        return $this->hasOne(SkillAssessment::class)
            ->where('status', '!=', 'draft')
            ->latestOfMany('submitted_at');
    }

    /**
     * All development plans for this employee.
     */
    public function developmentPlans()
    {
        return $this->hasMany(DevelopmentPlan::class);
    }

    /**
     * Active development plans (planned or in_progress).
     */
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
     * Shortcut: current plantilla item via active assignment.
     */
    public function getCurrentPlantillaItemAttribute(): ?PlantillaItem
    {
        return $this->primaryAssignment?->plantillaItem;
    }

    /**
     * Shortcut: current step increment via active assignment.
     */
    public function getCurrentStepIncrementAttribute(): ?StepIncrement
    {
        return $this->primaryAssignment?->effectiveStepIncrement();
    }

    /**
     * Shortcut: average score from the latest submitted assessment.
     */
    public function getSkillScoreAttribute(): ?float
    {
        return $this->latestAssessment?->average_score;
    }

    /**
     * Shortcut: gap count from the latest submitted assessment.
     */
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

    /**
     * Scope: employees who have at least one submitted assessment.
     */
    public function scopeWithAssessment($query)
    {
        return $query->whereHas('skillAssessments', fn ($q) =>
            $q->where('status', '!=', 'draft')
        );
    }

    /**
     * Scope: employees who have skill gaps (gap_count > 0 in latest assessment).
     */
    public function scopeWithGaps($query)
    {
        return $query->whereHas('skillAssessments', fn ($q) =>
            $q->where('status', '!=', 'draft')
              ->where('gap_count', '>', 0)
        );
    }
}
