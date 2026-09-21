<?php

namespace App\Models;

use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use HasFactory, AutoUppercase;

    public const CATEGORY_CREDIT_BASED = 'credit_based';

    public const CATEGORY_ENTITLEMENT_BASED = 'entitlement_based';

    protected array $uppercaseExclude = [
        'code',
        'name',
        'description',
        'color',
        'bg_color',
        'unit',
        'eligible_employment_types',
        'eligible_genders',
        'filing_guidelines',
        'required_documents',
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
        'enforce_annual_cap',
        'is_accrual_based',
        'default_monthly_accrual',
        'annual_grant_days',
        'requires_balance_check',
        'is_active',
        'sort_order',
        'eligible_employment_types',
        'eligible_genders',
        'filing_guidelines',
        'required_documents',
    ];

    protected $casts = [
        'requires_date_range' => 'boolean',
        'is_confidential' => 'boolean',
        'is_accrual_based' => 'boolean',
        'enforce_annual_cap' => 'boolean',
        'requires_balance_check' => 'boolean',
        'is_active' => 'boolean',
        'default_monthly_accrual' => 'decimal:2',
        'annual_grant_days' => 'decimal:2',
        'eligible_employment_types' => 'array',
        'eligible_genders' => 'array',
        'filing_guidelines' => 'array',
        'required_documents' => 'array',
    ];

    public function getCategoryAttribute(): string
    {
        return $this->requires_balance_check
            ? self::CATEGORY_CREDIT_BASED
            : self::CATEGORY_ENTITLEMENT_BASED;
    }

    public function getCategoryLabelAttribute(): string
    {
        return $this->requires_balance_check
            ? 'Credit-Based Leave'
            : 'Entitlement-Based Leave';
    }

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

    public function isEligibleForGender(?string $gender): bool
    {
        if (empty($this->eligible_genders)) {
            return true;
        }

        if (! $gender) {
            return false;
        }

        return collect($this->eligible_genders)
            ->contains(fn ($g) => strtolower(trim($g)) === strtolower(trim($gender)));
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

    public function scopeEligibleForGender($query, ?string $gender)
    {
        if (! $gender) {
            return $query->whereNull('eligible_genders');
        }

        return $query->where(function ($q) use ($gender) {
            $q->whereNull('eligible_genders')
                ->orWhereJsonContains('eligible_genders', $gender)
                ->orWhereJsonContains('eligible_genders', strtoupper(trim($gender)))
                ->orWhereJsonContains('eligible_genders', strtolower(trim($gender)));
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