<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlantillaPosition extends Model
{
    protected $fillable = [
        'plantilla_item_id',
        'item_number',
        'position_title',
        'salary_grade_id',
        'step_increment_id',
        'status',
        'date_of_assumption',
        'requires_approval',
        'vacated_at',
        'vacancy_notes',
    ];

    protected $casts = [
        'requires_approval'  => 'boolean',
        'vacated_at'         => 'datetime',
        'date_of_assumption' => 'date',
    ];

    // ─── Relationships ────────────────────────────────────────────────────

    public function item()
    {
        return $this->belongsTo(PlantillaItem::class, 'plantilla_item_id');
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

    // ─── Status Checks ────────────────────────────────────────────────────

    public function isFilled(): bool
    {
        return $this->activeAssignments()->exists();
    }

    public function isVacant(): bool
    {
        return ! $this->activeAssignments()->exists() && ! $this->requires_approval;
    }

    public function isUnfilled(): bool
    {
        return ! $this->activeAssignments()->exists() && $this->requires_approval;
    }

    public function isOverstaffed(): bool
    {
        return $this->activeAssignments()->count() > 1;
    }

    public function isAssignable(): bool
    {
        return ! $this->requires_approval && ! $this->isFilled();
    }

    // ─── Computed Status ──────────────────────────────────────────────────

    public function getComputedStatusAttribute(): string
    {
        if ($this->isOverstaffed())   return 'OVERSTAFFED';
        if ($this->isFilled())        return 'FILLED';
        if ($this->requires_approval) return 'UNFILLED';
        return 'VACANT';
    }

    // ─── Vacancy Actions ──────────────────────────────────────────────────

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

    // ─── Model Events ─────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (PlantillaPosition $pos) {
            $item = PlantillaItem::find($pos->plantilla_item_id);

            if (! $item) {
                throw new \RuntimeException('Plantilla item not found.');
            }

            if (! $item->hasAvailableSlots()) {
                throw new \RuntimeException(
                    "No available slots for plantilla item [{$item->title}]. " .
                    "Approved slots: {$item->approved_slots}, " .
                    "Current: {$item->positions()->count()}."
                );
            }
        });

        static::deleted(function (PlantillaPosition $pos) {
            $item = PlantillaItem::find($pos->plantilla_item_id);

            if ($item && $item->positions()->count() === 0) {
                $item->delete();
            }
        });
    }

    // ─── Scopes ───────────────────────────────────────────────────────────

    public function scopeVacant($query)
    {
        return $query
            ->whereDoesntHave('activeAssignments')
            ->where('requires_approval', false);
    }

    public function scopeFilled($query)
    {
        return $query->whereHas('activeAssignments');
    }

    public function scopeUnfilled($query)
    {
        return $query
            ->whereDoesntHave('activeAssignments')
            ->where('requires_approval', true);
    }

    public function scopeAssignable($query)
    {
        return $query
            ->whereDoesntHave('activeAssignments')
            ->where('requires_approval', false);
    }

    public function scopeOverstaffed($query)
    {
        return $query
            ->withCount('activeAssignments')
            ->having('active_assignments_count', '>', 1);
    }

    public function scopeForDivision($query, int $divisionId)
    {
        return $query->whereHas('item', fn ($q) =>
            $q->where('division_id', $divisionId)
        );
    }

    public function scopeForDepartment($query, int $departmentId)
    {
        return $query->whereHas('item', fn ($q) =>
            $q->whereJsonContains('department_ids', $departmentId)
        );
    }
}