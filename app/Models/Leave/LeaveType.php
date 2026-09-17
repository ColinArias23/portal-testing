<?php

namespace App\Models;

use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use HasFactory, AutoUppercase;

    protected array $uppercaseExclude = [
        'code',
        'name',
        'description',
        'color',
        'bg_color',
        'unit',
        'eligible_employment_types',
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'code',
        'name',
        'description',
        'color',
        'bg_color',
        'unit',
        'requires_date_range',
        'is_confidential',
        'max_days',
        'is_accrual_based',
        'default_monthly_accrual',
        'requires_balance_check',
        'is_active',
        'sort_order',
        'eligible_employment_types',
    ];

    protected $casts = [
        'requires_date_range' => 'boolean',
        'is_confidential' => 'boolean',
        'is_accrual_based' => 'boolean',
        'requires_balance_check' => 'boolean',
        'is_active' => 'boolean',
        'default_monthly_accrual' => 'decimal:2',
        'eligible_employment_types' => 'array',
    ];

    public function isEligibleFor(?string $employmentType): bool
    {
        if (empty($this->eligible_employment_types)) {
            return true;
        }

        if (! $employmentType) {
            return false;
        }

        return collect($this->eligible_employment_types)
            ->contains(fn ($t) => strtolower(trim($t)) === strtolower(trim($employmentType)));
    }

    public function scopeEligibleFor($query, ?string $employmentType)
    {
        if (! $employmentType) {
            return $query->whereNull('eligible_employment_types');
        }

        return $query->where(function ($q) use ($employmentType) {
            $q->whereNull('eligible_employment_types')
                ->orWhereJsonContains('eligible_employment_types', $employmentType)
                ->orWhereJsonContains('eligible_employment_types', ucfirst(strtolower($employmentType)))
                ->orWhereJsonContains('eligible_employment_types', strtolower($employmentType));
        });
    }

    public function approvalFlowSteps(): HasMany
    {
        return $this->hasMany(LeaveApprovalFlowStep::class)->orderBy('step_order');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}