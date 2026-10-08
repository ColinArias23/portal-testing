<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\SoftDeletes;

class Onboarding extends Model
{
    use SoftDeletes, AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    
    protected $fillable = [
        'employee_id',
        'applicant_id',
        'plantilla_posting_application_id',
        'name',
        'position',
        'department',
        'start_date',
        'status',
        'type',
        'provisioned_at',
    ];

    protected $casts = [
        'start_date'     => 'datetime',
        'provisioned_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function plantillaApplication()
    {
        return $this->belongsTo(PlantillaPostingApplication::class, 'plantilla_posting_application_id');
    }

    public function isPlantilla(): bool
    {
        return $this->type !== null;
    }

    public function isProvisioned(): bool
    {
        return $this->provisioned_at !== null;
    }

    public function scopeForPlantilla(Builder $query, bool $plantilla): Builder
    {
        return $plantilla ? $query->whereNotNull('type') : $query->whereNull('type');
    }

    public function tasks()
    {
        return $this->hasMany(OnboardingTask::class)->orderBy('sort_order');
    }

    public function computeStatus(): string
    {
        $tasks = $this->tasks;

        if ($tasks->isEmpty()) return 'Pending';
        if ($tasks->every(fn($t) => $t->completed)) return 'Completed';
        if ($tasks->some(fn($t) => $t->completed)) return 'In Progress';

        return 'Pending';
    }
}