<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Concerns\AutoUppercase;
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

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicantDocument::class);
    }

    public function interview(): HasOne
    {
        return $this->hasOne(Interview::class);
    }

    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }

    public function onboarding(): HasOne
    {
        return $this->hasOne(Onboarding::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function plantillaPostingApplication(): BelongsTo
    {
        return $this->belongsTo(PlantillaPostingApplication::class);
    }
 
    public function isInternalCandidate(): bool
    {
        return ! is_null($this->employee_id);
    }

    public function scopeHired($query)
    {
        return $query->where('status', 'Hired');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'Rejected');
    }

    public function scopeForInterview($query)
    {
        return $query->where('status', 'For Interview');
    }

    public function scopeInternal($query)
    {
        return $query->whereNotNull('employee_id');
    }
}
