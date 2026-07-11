<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlantillaPosting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'base_item_number',
        'title',
        'description',
        'display_department_id',
        'display_division_id',
        'section',
        'salary_grade_id',
        'step_increment_id',
        'monthly_salary',
        'employment_status',
        'vacancies',
        'immediate_supervisor',
        'qualification_education',
        'qualification_experience',
        'qualification_training',
        'qualification_eligibility',
        'qualification_competency',
        'job_description',
        'required_documents',
        'date_posted',
        'closing_date',
        'expected_appointment_date',
        'status',
        'created_by',
    ];

    protected $casts = [
        'required_documents' => 'array',
        'monthly_salary' => 'decimal:2',
        'date_posted' => 'date',
        'closing_date' => 'date',
        'expected_appointment_date' => 'date',
    ];

    protected $appends = [
        'effective_status',
        'vacant_slots',
        'remaining_vacancies',
        'applicants_count',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────

    public function department()
    {
        return $this->belongsTo(Department::class, 'display_department_id');
    }

    public function division()
    {
        return $this->belongsTo(Division::class, 'display_division_id');
    }

    public function salaryGrade()
    {
        return $this->belongsTo(SalaryGrade::class, 'salary_grade_id');
    }

    public function stepIncrement()
    {
        return $this->belongsTo(StepIncrement::class, 'step_increment_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function applications()
    {
        return $this->hasMany(PlantillaPostingApplication::class);
    }

    // ─── Scopes ─────────────────────────────────────────────────────
     
    public function scopeAdminOpen($query)
    {
        return $query->where('status', '!=', 'Closed')
            ->whereDate('closing_date', '>=', now()->toDateString());
    }

    // ─── Computed attributes ─────────────────────────────────────────────────────
    
    public function getVacantSlotsAttribute(): int
    {
        return PlantillaPosition::where('base_item_number', $this->base_item_number)
            ->where('status', 'VACANT')
            ->count();
    }

    public function getApplicantsCountAttribute(): int
    {
        if (array_key_exists('applications_count', $this->attributes)) {
            return (int) $this->attributes['applications_count'];
        }

        return $this->applications()->count();
    }

    public function getRemainingVacanciesAttribute(): int
    {
        $completedCount = $this->applications()
            ->where('status', 'Completed')
            ->count();

        $remaining = $this->vacancies - $completedCount;

        return max(0, min($remaining, $this->vacant_slots));
    }

    public function getEffectiveStatusAttribute(): string
    {
        if ($this->status === 'Closed') {
            return 'Closed';
        }

        if ($this->remaining_vacancies <= 0 || $this->vacant_slots <= 0) {
            return 'Closed';
        }

        $today = now()->startOfDay();
        $closing = $this->closing_date?->copy()->startOfDay();

        if ($closing && $today->greaterThan($closing)) {
            return 'Closed';
        }

        if ($closing && $today->diffInDays($closing, false) <= 10) {
            return 'Closing Soon';
        }

        return 'Open';
    }

    public function isAvailableForApplication(): bool
    {
        return in_array($this->effective_status, ['Open', 'Closing Soon'])
            && $this->vacant_slots > 0
            && $this->remaining_vacancies > 0;
    }

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }
}