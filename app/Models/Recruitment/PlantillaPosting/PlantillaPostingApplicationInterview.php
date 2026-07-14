<?php

namespace App\Models;

use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantillaPostingApplicationInterview extends Model
{
    use AutoUppercase;

    public const STATUS_COMPLETED = 'COMPLETED';

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
        'plantilla_posting_application_id',
        'hr_notes',
        'head_notes',
        'final_notes',
    ];

    protected $fillable = [
        'plantilla_posting_application_id',
        'hr_status', 'head_status', 'final_status', 'overall_status',
        'hr_scheduled_at', 'head_scheduled_at', 'final_scheduled_at',
        'hr_notes', 'head_notes', 'final_notes',
    ];

    protected $casts = [
        'hr_scheduled_at'    => 'datetime',
        'head_scheduled_at'  => 'datetime',
        'final_scheduled_at' => 'datetime',
    ];

    public function plantillaPostingApplication(): BelongsTo
    {
        return $this->belongsTo(PlantillaPostingApplication::class);
    }

    public function hasCompleted(): bool
    {
        return $this->overall_status === self::STATUS_COMPLETED;
    }
}