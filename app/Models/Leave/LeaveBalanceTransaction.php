<?php

namespace App\Models;

use App\Enums\LeaveBalanceTransactionType;
use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalanceTransaction extends Model
{
    use AutoUppercase;

    public $timestamps = false;

    protected array $uppercaseExclude = ['type', 'remarks', 'created_at'];

    protected $fillable = [
        'leave_balance_id',
        'leave_request_id',
        'type',
        'amount',
        'balance_after',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'type' => LeaveBalanceTransactionType::class,
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function leaveBalance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class);
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
