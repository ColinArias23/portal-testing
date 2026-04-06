<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingTask extends Model
{
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