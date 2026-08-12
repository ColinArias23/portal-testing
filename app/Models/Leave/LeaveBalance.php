<?php

namespace App\Models;

use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveBalance extends Model
{
    use AutoUppercase;

    protected array $uppercaseExclude = ['created_at', 'updated_at'];
    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'year',
        'opening_balance',
        'carry_forward_in',
        'earned',
        'used',
        'adjusted',
        'monetized',
    ];

    protected $guarded = ['id', 'available'];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'carry_forward_in' => 'decimal:2',
        'earned' => 'decimal:2',
        'used' => 'decimal:2',
        'adjusted' => 'decimal:2',
        'monetized' => 'decimal:2',
        'available' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LeaveBalanceTransaction::class)->orderByDesc('created_at');
    }
}
