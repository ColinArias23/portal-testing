<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PschedSlot extends Model
{
    protected $table = 'psched_slots';

    protected $fillable = [
        'line_id',
        'item_number',
        'sort_order',
        'employee_id',
        'employee_number',
        'employee_name',
        'assigned_at',
        'assigned_by',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(PschedLine::class, 'line_id');
    }
}
