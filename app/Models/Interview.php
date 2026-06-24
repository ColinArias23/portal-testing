<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoUppercase;

class Interview extends Model
{

    use AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
    protected $fillable = [
        'applicant_id',
        'hr_status', 'head_status', 'final_status', 'overall_status',
        'hr_scheduled_at', 'head_scheduled_at', 'final_scheduled_at',
        'hr_notes', 'head_notes', 'final_notes',
    ];

    protected $casts = [
        'hr_scheduled_at'    => 'datetime',
        'head_scheduled_at'  => 'datetime',
        'final_scheduled_at' => 'datetime',
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }
}