<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SkillAssessment extends Model
{

    use AutoUppercase;
    const STATUS_PENDING = 'pending';
    const STATUS_DONE    = 'done';

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'employee_id',
        'department_id',
        'scores',
        'average_score',
        'gap_count',
        'strength_count',
        'status',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'reviewer_notes',
    ];

    protected $casts = [
        'scores'        => 'array',
        'average_score' => 'decimal:2',
        'submitted_at'  => 'datetime',
        'reviewed_at'   => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (SkillAssessment $assessment) {
            if ($assessment->getOriginal('status') === self::STATUS_DONE) {
                $allowedFields = ['reviewed_at', 'reviewed_by', 'reviewer_notes'];
                $dirty         = array_keys($assessment->getDirty());

                if (array_diff($dirty, $allowedFields)) {
                    throw new \RuntimeException('A completed assessment cannot be modified.');
                }
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function developmentPlans(): HasMany
    {
        return $this->hasMany(DevelopmentPlan::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeDone(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DONE);
    }

    public function scopeByEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeLatestSubmitted(Builder $query): Builder
    {
        return $query->orderByDesc('submitted_at');
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function submit(): bool
    {
        $this->status       = self::STATUS_DONE;
        $this->submitted_at = Carbon::now();
        return $this->save();
    }

    public function calculateMetrics(): self
    {
        if (empty($this->scores)) {
            return $this;
        }

        $scores = collect($this->scores)->filter(fn($v) => (int) $v > 0);

        if ($scores->isEmpty()) {
            return $this;
        }

        $this->average_score  = $scores->avg();
        $this->gap_count      = $scores->filter(fn($v) => (int) $v <= 3)->count();
        $this->strength_count = $scores->filter(fn($v) => (int) $v >= 4)->count();

        return $this;
    }
}