<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoUppercase;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlantillaPosition extends Model
{
    use SoftDeletes, AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    
    protected $fillable = [
        'base_item_number',
        'title',
        'description',
        'slot_number',
        'position_slot_name',
        'position_title',
        'role',
        'display_department_id',
        'display_division_id',
        'salary_grade_id',
        'step_increment_id',
        'status',
        'requires_approval',
        'vacated_at',
        'vacancy_notes',
        'date_of_assumption',
    ];

    protected $casts = [
        'requires_approval'  => 'boolean',
        'vacated_at'         => 'datetime',
        'date_of_assumption' => 'date',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
        'deleted_at'         => 'datetime',
    ];

    protected $appends = [
        'computed_status',
        'has_salary_config',
        'is_assignable',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────

    public function displayDepartment()
    {
        return $this->belongsTo(Department::class, 'display_department_id');
    }

    public function displayDivision()
    {
        return $this->belongsTo(Division::class, 'display_division_id');
    }

    public function salaryGrade()
    {
        return $this->belongsTo(SalaryGrade::class);
    }

    public function stepIncrement()
    {
        return $this->belongsTo(StepIncrement::class);
    }

    public function assignments()
    {
        return $this->hasMany(EmployeeAssignment::class, 'plantilla_position_id');
    }

    public function activeAssignments()
    {
        return $this->assignments()->whereNull('end_date');
    }

    // ─── Status Checks ─────────────────────────────────────────────────────

    public function isFilled(): bool
    {
        return $this->activeAssignments()->exists();
    }

    public function isVacant(): bool
    {
        return !$this->activeAssignments()->exists() && !$this->requires_approval;
    }

    public function isUnfilled(): bool
    {
        return !$this->activeAssignments()->exists() && $this->requires_approval;
    }

    public function isOverstaffed(): bool
    {
        return $this->activeAssignments()->count() > 1;
    }

    public function isAssignable(): bool
    {
        return !$this->requires_approval && !$this->isFilled();
    }

    // ─── Computed Attributes ───────────────────────────────────────────────

    public function getComputedStatusAttribute(): string
    {
        if ($this->isOverstaffed())   return 'OVERSTAFFED';
        if ($this->isFilled())        return 'FILLED';
        if ($this->requires_approval) return 'UNFILLED';
        return 'VACANT';
    }

    public function getHasSalaryConfigAttribute(): bool
    {
        return $this->salary_grade_id !== null;
    }

    public function getIsAssignableAttribute(): bool
    {
        return $this->isAssignable();
    }

    public function getEffectiveSalaryGradeAttribute(): ?SalaryGrade
    {
        if ($this->relationLoaded('salaryGrade')) return $this->salaryGrade;
        return $this->salaryGrade()->first();
    }

    public function getEffectiveStepIncrementAttribute(): ?StepIncrement
    {
        if ($this->relationLoaded('stepIncrement')) return $this->stepIncrement;
        return $this->stepIncrement()->first();
    }

    public function getAvailableStepsAttribute()
    {
        if (!$this->salary_grade_id) return collect([]);
        if ($this->relationLoaded('salaryGrade') && $this->salaryGrade) {
            return $this->salaryGrade->stepIncrements;
        }
        return StepIncrement::where('salary_grade_id', $this->salary_grade_id)
            ->orderBy('step')->get();
    }

    public function availableSteps()
    {
        if (!$this->salary_grade_id) return collect([]);
        return StepIncrement::where('salary_grade_id', $this->salary_grade_id)
            ->orderBy('step')->get();
    }

    public function formatSalaryInfo(): array
    {
        $sg   = $this->relationLoaded('salaryGrade')   ? $this->salaryGrade   : $this->salaryGrade()->first();
        $step = $this->relationLoaded('stepIncrement') ? $this->stepIncrement : $this->stepIncrement()->first();

        return [
            'salary_grade' => $sg ? [
                'id'             => $sg->id,
                'salary_grade'   => $sg->salary_grade,
                'monthly_salary' => $sg->monthly_salary,
                'annual_salary'  => $sg->annual_salary,
            ] : null,
            'default_step' => $step ? [
                'id'             => $step->id,
                'step'           => $step->step,
                'monthly_salary' => $step->monthly_salary,
                'annual_salary'  => $step->annual_salary,
            ] : null,
            'available_steps' => $this->availableSteps()
                ->map(fn($s) => [
                    'id'             => $s->id,
                    'step'           => $s->step,
                    'monthly_salary' => $s->monthly_salary,
                    'annual_salary'  => $s->annual_salary,
                ])->values()->toArray(),
        ];
    }

    // ─── Vacancy Actions ───────────────────────────────────────────────────

    public function markUnfilled(?string $notes = null): void
    {
        $this->update([
            'status'            => 'UNFILLED',
            'requires_approval' => true,
            'vacated_at'        => now(),
            'vacancy_notes'     => $notes,
        ]);
    }

    public function approveForRefilling(): void
    {
        $this->update([
            'status'            => 'VACANT',
            'requires_approval' => false,
            'vacated_at'        => null,
            'vacancy_notes'     => null,
        ]);
    }

    public function fillPosition(): void
    {
        $this->update(['status' => 'FILLED', 'vacated_at' => null]);
    }

    public function vacatePosition(?string $notes = null): void
    {
        $this->markUnfilled($notes);
    }

    // ─── Model Events ──────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::deleting(function (PlantillaPosition $position) {
            if ($position->isFilled()) {
                throw new \RuntimeException(
                    "Cannot delete position [{$position->position_slot_name}] — it is currently FILLED."
                );
            }
            if ($position->isUnfilled()) {
                throw new \RuntimeException(
                    "Cannot delete position [{$position->position_slot_name}] — it is UNFILLED and awaiting approval."
                );
            }
        });
    }

    // ─── Scopes ────────────────────────────────────────────────────────────

    public function scopeVacant($query)
    {
        return $query->whereDoesntHave('activeAssignments')->where('requires_approval', false);
    }

    public function scopeFilled($query)
    {
        return $query->whereHas('activeAssignments');
    }

    public function scopeUnfilled($query)
    {
        return $query->whereDoesntHave('activeAssignments')->where('requires_approval', true);
    }

    public function scopeAssignable($query)
    {
        return $query->whereDoesntHave('activeAssignments')->where('requires_approval', false);
    }

    public function scopeOverstaffed($query)
    {
        return $query->withCount('activeAssignments')->having('active_assignments_count', '>', 1);
    }

    public function scopeForDepartment($query, int $departmentId)
    {
        return $query->where('display_department_id', $departmentId);
    }

    public function scopeForDivision($query, int $divisionId)
    {
        return $query->where('display_division_id', $divisionId);
    }

    public function scopeForItem($query, string $baseItemNumber)
    {
        return $query->where('base_item_number', $baseItemNumber);
    }

    public function scopeWithSalaryConfig($query)
    {
        return $query->whereNotNull('salary_grade_id');
    }
}