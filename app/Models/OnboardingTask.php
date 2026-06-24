<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoUppercase;

class OnboardingTask extends Model
{

    use AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
    protected $fillable = [
        'onboarding_id',
        'title',
        'completed',
        'completed_at',
        'sort_order',
    ];

    protected $casts = [
        'completed'    => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function onboarding()
    {
        return $this->belongsTo(Onboarding::class);
    }
}