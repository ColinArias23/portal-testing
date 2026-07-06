<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoUppercase;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'employee_id',
        'plantilla_posting_application_id',
    ];
    
    protected $fillable = [
        'employee_id',
        'plantilla_posting_application_id',
        'first_name',
        'last_name',
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
     * NEW: the employee this applicant was auto-provisioned from
     * (null for external/walk-in applicants).
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * NEW: the plantilla posting application that produced this applicant
     * record (null for external/walk-in applicants).
     */
    public function plantillaPostingApplication(): BelongsTo
    {
        return $this->belongsTo(PlantillaPostingApplication::class);
    }
 
    public function isInternalCandidate(): bool
    {
        return ! is_null($this->employee_id);
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

    /**
     * Scope: Get only internally-sourced applicants (from plantilla postings)
     */
    public function scopeInternal($query)
    {
        return $query->whereNotNull('employee_id');
    }
}
