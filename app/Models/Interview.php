<?php

namespace App\Models;

use App\Traits\AutoUppercase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class Interview extends Model
{
    use AutoUppercase;

    public const STATUS_COMPLETED = 'COMPLETED';

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
        'applicant_id',
    ];

    protected $fillable = [
        'applicant_id',
        'hr_status', 'head_status', 'final_status', 'overall_status',
        'hr_scheduled_at', 'head_scheduled_at', 'final_scheduled_at',
        'hr_notes', 'head_notes', 'final_notes',
    ];

    protected $casts = [
        'hr_scheduled_at'    => 'datetime',
        'head_scheduled_at'  => 'datetime',
        'final_scheduled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Interview $interview) {
            if (is_null($interview->applicant_id)) {
                throw new InvalidArgumentException(
                    'An Interview must reference an applicant_id.'
                );
            }
        });
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function hasCompleted(): bool
    {
        return $this->overall_status === self::STATUS_COMPLETED;
    }
}