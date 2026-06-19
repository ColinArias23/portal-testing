<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdsTraining extends Model
{
    protected $table = 'pds_trainings';
    protected $fillable = [
        'employee_id',
        'title', 'date_from', 'date_to', 'hours', 'type', 'sponsored_by',
        'order',
    ];
    protected $casts = [
        'date_from' => 'date:Y-m-d',
        'date_to'   => 'date:Y-m-d',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}