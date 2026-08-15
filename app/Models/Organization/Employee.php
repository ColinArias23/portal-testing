<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Concerns\AutoUppercase;
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

    use AutoUppercase;

    protected array $uppercaseExclude = [
        'avatar_url',
        'border_color',
        'notes',
        'annual_salary',
        'monthly_salary',
        'salary_override',
        'created_at',
        'updated_at',
    ];

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
        'annual_salary',
        'monthly_salary',
        'salary_override',
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
        'annual_salary'        => 'decimal:2',
        'monthly_salary'       => 'decimal:2',
        'salary_override'      => 'boolean',
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

    public function getFormalNameAttribute(): string
    {
        $namePart = collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter()
            ->implode(' ');

        $prefixPart = $this->prefix ? rtrim($this->prefix, '.') . '.' : null;

        $nameWithPrefix = collect([$prefixPart, $namePart])->filter()->implode(' ');

        $titles = is_array($this->title) ? $this->title : array_filter([$this->title]);

        $trailing = collect([$this->suffix, ...$titles])->filter()->implode(', ');

        return $trailing !== '' ? "{$nameWithPrefix}, {$trailing}" : $nameWithPrefix;
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
        return $this->hasOne(User::class);
    }

    public function signatures()
    {
        return $this->hasMany(EmployeeSignature::class);
    }

    public function signatureLogs()
    {
        return $this->hasMany(EmployeeSignatureLog::class)->latest('created_at');
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function assignments()
    {
        return $this->hasMany(EmployeeAssignment::class);
    }
    public function primaryAssignment()
    {
        return $this->hasOne(EmployeeAssignment::class)
            ->whereNull('end_date')
            ->where('is_primary', true)
            ->latestOfMany('start_date');
    }
    public function activeAssignment()
    {
        return $this->primaryAssignment();
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

    public function cosPosition()
    {
        return $this->belongsTo(CosPosition::class, 'cos_position_id');
    }

    public function consultantPosition()
    {
        return $this->belongsTo(ConsultantPosition::class, 'consultant_position_id');
    }

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

    public function salaryHistories()
    {
        return $this->hasMany(EmployeeSalaryHistory::class);
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function getCurrentPlantillaPositionAttribute(): ?PlantillaPosition
    {
        return $this->primaryAssignment?->plantillaPosition;
    }

    public function getCurrentStepIncrementAttribute(): ?StepIncrement
    {
        return $this->primaryAssignment?->effectiveStepIncrement();
    }

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
        return $query->whereRaw('UPPER(employment_type) = ?', ['PLANTILLA']);
    }

    public function scopeCos($query)
    {
        return $query->whereRaw('UPPER(employment_type) = ?', ['CONTRACT OF SERVICE']);
    }

    public function scopeConsultant($query)
    {
        return $query->whereRaw('UPPER(employment_type) = ?', ['CONSULTANT']);
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

    public function departmentAssignments()
    {
        return $this->hasMany(EmployeeDepartmentAssignment::class);
    }
    public function primaryDepartmentAssignment()
    {
        return $this->hasOne(EmployeeDepartmentAssignment::class)
            ->where('is_primary', true)
            ->whereNull('end_date');
    }

    public function activeDepartmentAssignments()
    {
        return $this->departmentAssignments()
            ->whereNull('end_date')
            ->orderBy('is_primary', 'desc');
    }

    public function getPrimaryDepartmentAttribute(): ?Department
    {
        return $this->primaryDepartmentAssignment?->department;
    }

    public function getPrimaryDepartmentHeadAttribute(): ?Employee
    {
        return $this->primaryDepartmentAssignment?->effective_head;
    }

    public function getAssignedDepartmentsAttribute()
    {
        return $this->activeDepartmentAssignments
            ->map(fn ($a) => $a->department)
            ->unique('id')
            ->values();
    }

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

    public function hasSalaryOverride(): bool
    {
        return ! $this->isPlantilla() || $this->salary_override;
    }
}