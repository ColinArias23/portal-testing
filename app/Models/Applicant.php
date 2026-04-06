<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Applicant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'age', 'phone',
        'address', 'birthdate', 'source', 'position', 'department',
        'date_applied', 'submission', 'status', 'remarks',
    ];

    protected $casts = [
        'birthdate'    => 'date',
        'date_applied' => 'date',
    ];

    protected $appends = ['full_name'];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function documents()
    {
        return $this->hasMany(ApplicantDocument::class);
    }

    public function interview()
    {
        return $this->hasOne(Interview::class);
    }

    public function contract()
    {
        return $this->hasOne(Contract::class);
    }

    public function events()
    {
        return $this->belongsToMany(HiringEvent::class, 'hiring_event_applicants');
    }

    public function onboarding()
    {
        return $this->hasOne(Onboarding::class);
    }
}