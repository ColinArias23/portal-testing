<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PDS relational models.
 * Each model maps to one of the pds_* tables created in the migration.
 * They all follow the same pattern: belongsTo Employee, ordered by `order`.
 */

// ── Children ─────────────────────────────────────────────────────────────────

class PdsChild extends Model
{
    protected $table = 'pds_children';

    protected $fillable = ['employee_id', 'name', 'date_of_birth', 'order'];

    protected $casts = ['date_of_birth' => 'date:Y-m-d'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

// ── Education ─────────────────────────────────────────────────────────────────

class PdsEducation extends Model
{
    protected $table = 'pds_education';

    protected $fillable = [
        'employee_id', 'level',
        'school', 'degree', 'period_from', 'period_to',
        'highest_level', 'year_graduated', 'honors',
        'order',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

// ── Eligibility ───────────────────────────────────────────────────────────────

class PdsEligibility extends Model
{
    protected $table = 'pds_eligibilities';

    protected $fillable = [
        'employee_id',
        'eligibility', 'rating', 'exam_date', 'place', 'license_no', 'valid_until',
        'order',
    ];

    protected $casts = [
        'exam_date'   => 'date:Y-m-d',
        'valid_until' => 'date:Y-m-d',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

// ── Work Experience ───────────────────────────────────────────────────────────

class PdsWorkExperience extends Model
{
    protected $table = 'pds_work_experiences';

    protected $fillable = [
        'employee_id',
        'date_from', 'date_to',
        'position', 'department',
        'monthly_salary', 'salary_grade', 'appointment_status', 'govt_service',
        'order',
    ];

    protected $casts = [
        'date_from'      => 'date:Y-m-d',
        'date_to'        => 'date:Y-m-d',
        'monthly_salary' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

// ── Voluntary Work ────────────────────────────────────────────────────────────

class PdsVoluntaryWork extends Model
{
    protected $table = 'pds_voluntary_works';

    protected $fillable = [
        'employee_id',
        'org_name_address', 'date_from', 'date_to', 'hours', 'position',
        'order',
    ];

    protected $casts = [
        'date_from' => 'date:Y-m-d',
        'date_to'   => 'date:Y-m-d',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

// ── Trainings ─────────────────────────────────────────────────────────────────

class PdsTraining extends Model
{
    protected $table = 'pds_trainings';

    protected $fillable = [
        'employee_id',
        'title', 'date_from', 'date_to', 'hours', 'type', 'sponsored_by',
        'order',
    ];

    protected $casts = [
        'date_from' => 'date:Y-m-d',
        'date_to'   => 'date:Y-m-d',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

// ── Special Skills ────────────────────────────────────────────────────────────

class PdsSpecialSkill extends Model
{
    protected $table = 'pds_special_skills';

    protected $fillable = ['employee_id', 'skill', 'order'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

// ── Non-Academic Distinctions ─────────────────────────────────────────────────

class PdsNonAcademicDistinction extends Model
{
    protected $table = 'pds_non_academic_distinctions';

    protected $fillable = ['employee_id', 'distinction', 'order'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

// ── Organization Memberships ──────────────────────────────────────────────────

class PdsOrganizationMembership extends Model
{
    protected $table = 'pds_organization_memberships';

    protected $fillable = ['employee_id', 'organization', 'order'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

// ── References ────────────────────────────────────────────────────────────────

class PdsReference extends Model
{
    protected $table = 'pds_references';

    protected $fillable = ['employee_id', 'name', 'address', 'contact', 'order'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}