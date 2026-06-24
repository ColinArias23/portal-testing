<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoUppercase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

class Report extends Model
{

    use AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
    protected $fillable = [
        'user_id',
        'subject',
        'description',
        'type',
        'category',
        'severity',
        'status',
        'resolved_at',
    ];

    protected $casts = [
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public const TYPES = ['bug', 'improvement'];

    public const CATEGORIES = [
        'UI / UX',
        'Authentication',
        'Performance',
        'Dashboard',
        'API',
        'Mobile',
        'Notifications',
        'Billing',
        'Other',
    ];

    public const SEVERITIES = ['critical', 'high', 'medium', 'low'];

    public const STATUSES = ['open', 'in_progress', 'resolved', 'closed'];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $report) {
            if (! $report->isDirty('status')) {
                return;
            }

            if ($report->status === 'resolved') {
                $report->resolved_at ??= Carbon::now();
            } else {
                $report->resolved_at = null;
            }
        });
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class, 'release_reports')
            ->withTimestamps();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isBug(): bool
    {
        return $this->type === 'bug';
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }
}