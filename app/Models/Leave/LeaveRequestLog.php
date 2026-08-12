<?php

namespace App\Models;

use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequestLog extends Model
{
    use AutoUppercase;

    public $timestamps = false;

    protected array $uppercaseExclude = [
        'action',
        'remarks',
        'metadata',
        'created_at',
    ];

    protected $fillable = [
        'leave_request_id',
        'action',
        'actor_id',
        'remarks',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
