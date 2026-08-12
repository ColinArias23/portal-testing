<?php

namespace App\Models;

use App\Enums\ApproverSource;
use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApprovalFlowStep extends Model
{
    use AutoUppercase;

    protected array $uppercaseExclude = [
        'approver_source',
        'approver_role',
        'label',
        'created_at',
        'updated_at',
    ];
    protected $fillable = [
        'leave_type_id',
        'step_order',
        'approver_source',
        'approver_role',
        'approver_user_id',
        'is_required',
        'label',
    ];

    protected $casts = [
        'approver_source' => ApproverSource::class,
        'is_required' => 'boolean',
    ];

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function fixedApprover(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approver_user_id');
    }
}
