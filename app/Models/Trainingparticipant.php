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
    ];

    protected $casts = [
        'certificate_issued' => 'boolean',
    ];
}