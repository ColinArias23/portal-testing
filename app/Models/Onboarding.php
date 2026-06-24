<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoUppercase;
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
        'name',
        'position',
        'department',
        'start_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
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