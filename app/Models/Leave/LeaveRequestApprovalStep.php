<?php

namespace App\Models;

use App\Enums\ApprovalStepStatus;
use App\Enums\ApproverSource;
use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequestApprovalStep extends Model
{
    use AutoUppercase;

    protected array $uppercaseExclude = [
        'approver_source',
        'approver_role',
        'status',
        'label',
        'remarks',
        'created_at',
        'updated_at',
        'acted_at',
    ];
    protected $fillable = [
        'leave_request_id',
        'flow_step_id',
        'step_order',
        'approver_source',
        'label',
        'approver_id',
        'approver_role',
        'status',
        'remarks',
        'acted_at',
        'acted_by',
    ];

    protected $casts = [
        'approver_source' => ApproverSource::class,
        'status' => ApprovalStepStatus::class,
        'acted_at' => 'datetime',
    ];

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}