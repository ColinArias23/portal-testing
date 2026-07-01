<?php

namespace App\Models;

use App\Traits\AutoUppercase;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes, AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
        'deleted_at',
        'last_auto_renewed_at',
        'file_path',
        'status',
        'auto_renewal_month',
    ];

    protected $fillable = [
        'applicant_id',
        'employee_id',
        'employee_name',
        'position',
        'salary',
        'start_date',
        'end_date',
        'status',
        'file_path',

        'auto_renewal_enabled',
        'auto_renewal_duration_months',
        'auto_renewal_month',
        'last_auto_renewed_at',
        'parent_contract_id',
        'renewal_generation',
    ];

    protected $casts = [
        'start_date'                   => 'datetime',
        'end_date'                     => 'date',
        'last_auto_renewed_at'         => 'datetime',
        'salary'                       => 'decimal:2',
        'auto_renewal_enabled'         => 'boolean',
        'auto_renewal_duration_months' => 'integer',
        'renewal_generation'           => 'integer',
    ];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    // ── NEW: direct link to Employee ──────────────────────────────────────
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function parentContract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'parent_contract_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(Contract::class, 'parent_contract_id');
    }

    public function scopeEligibleForAutoRenewal(Builder $query): Builder
    {
        $today = Carbon::today();

        return $query
            ->where('auto_renewal_enabled', true)
            ->where(function (Builder $q) use ($today): void {
                $q->whereBetween('end_date', [
                    $today,
                    $today->copy()->addDays(30),
                ])
                ->orWhereBetween('end_date', [
                    $today->copy()->subDays(7),
                    $today->copy()->subDay(),
                ]);
            })
            ->where(function (Builder $q) use ($today): void {
                $q->whereNull('last_auto_renewed_at')
                  ->orWhereDate('last_auto_renewed_at', '<', $today);
            });
    }

    public function scopeForCurrentRenewalWindow(Builder $query): Builder
    {
        $currentMonth = Carbon::now()->month;
        $windowName   = match ($currentMonth) {
            6       => 'JUNE',
            12      => 'DECEMBER',
            default => null,
        };

        if ($windowName === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($windowName): void {
            $q->where('auto_renewal_month', $windowName)
              ->orWhereNull('auto_renewal_month');
        });
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if (! $this->end_date) {
            return null;
        }

        return (int) Carbon::today()->diffInDays($this->end_date, false);
    }

    public function getContractStatusAttribute(): string
    {
        $days = $this->days_remaining;

        if ($days === null) {
            return 'Active';
        }

        return match (true) {
            $days < 0   => 'Expired',
            $days <= 30 => 'Expiring Soon',
            default     => 'Active',
        };
    }

    public function buildRenewal(): self
    {
        $newStart = $this->end_date
            ? $this->end_date->copy()->addDay()
            : Carbon::today();

        $newEnd = $newStart->copy()
            ->addMonths($this->auto_renewal_duration_months)
            ->subDay();

        return new self([
            'applicant_id'                 => $this->applicant_id,
            'employee_id'                  => $this->employee_id,
            'employee_name'                => $this->employee_name,
            'position'                     => $this->position,
            'salary'                       => $this->salary,
            'start_date'                   => $newStart,
            'end_date'                     => $newEnd,
            'status'                       => 'Generated',
            'file_path'                    => null,

            'auto_renewal_enabled'         => true,
            'auto_renewal_duration_months' => $this->auto_renewal_duration_months,
            'auto_renewal_month'           => $this->auto_renewal_month,
            'parent_contract_id'           => $this->id,
            'renewal_generation'           => $this->renewal_generation + 1,
        ]);
    }

    public static function generateAutoRenewalNumber(): string
    {
        $year = Carbon::now()->year;
        $seq  = static::withTrashed()
                      ->whereYear('created_at', $year)
                      ->count() + 1;

        return sprintf('CTR-%d-%04d-AR', $year, $seq);
    }
}