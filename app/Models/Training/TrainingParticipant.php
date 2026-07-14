<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use App\Traits\Concerns\AutoUppercase;

class TrainingParticipant extends Pivot
{

    use AutoUppercase;
    
    protected $table = 'training_participants';

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
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