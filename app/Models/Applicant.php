<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoUppercase;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Applicant extends Model
{
    use SoftDeletes, AutoUppercase;

    protected $table = 'applicants';

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    
    protected $fillable = [
        'first_name',
        'last_name',
        'username',
        'email',
        'age',
        'phone',
        'address',
        'birthdate',
        'source',
        'position',
        'department',
        'date_applied',
        'submission',
        'status',
        'remarks',
    ];

    protected $casts = [
        'birthdate' => 'date',
        'date_applied' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the full name of the applicant
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Get all documents for this applicant
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ApplicantDocument::class);
    }

    /**
     * Get the interview for this applicant
     */
    public function interview(): HasOne
    {
        return $this->hasOne(Interview::class);
    }

    /**
     * Get the contract for this applicant
     */
    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }

    /**
     * Get the onboarding for this applicant
     */
    public function onboarding(): HasOne
    {
        return $this->hasOne(Onboarding::class);
    }

    /**
     * Scope: Get only hired applicants
     */
    public function scopeHired($query)
    {
        return $query->where('status', 'Hired');
    }

    /**
     * Scope: Get only rejected applicants
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'Rejected');
    }

    /**
     * Scope: Get only applicants for interview
     */
    public function scopeForInterview($query)
    {
        return $query->where('status', 'For Interview');
    }
}
