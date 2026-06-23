<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Report extends Model
{
    protected $fillable = [
        'user_id',
        'subject',
        'description',
        'type',
        'category',
        'severity',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    public function isBug(): bool
    {
        return $this->type === 'bug';
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function releases(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Release::class, 'release_reports')
            ->withTimestamps();
    }
}