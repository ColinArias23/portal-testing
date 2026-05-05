<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TrainingParticipant extends Pivot
{
    protected $table = 'training_participants';

    protected $fillable = [
        'training_id',
        'employee_id',
        'status',
        'certificate_issued',
        'attended_at',          
    ];

    protected $casts = [
        'certificate_issued' => 'boolean',
        'attended_at'        => 'datetime',
    ];
}