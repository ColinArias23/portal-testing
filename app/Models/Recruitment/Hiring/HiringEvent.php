<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\SoftDeletes;

class HiringEvent extends Model
{
    use SoftDeletes, AutoUppercase;

    protected $table = 'hiring_events';

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    
    protected $fillable = [
        'title', 'description', 'location', 'color',
        'all_day', 'start_date', 'end_date', 'start_time',
        'interviewer_id',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date'   => 'datetime',
        'all_day'    => 'boolean',
    ];

    public function interviewer()
    {
        return $this->belongsTo(Employee::class, 'interviewer_id');
    }

    public function applicants()
    {
        return $this->belongsToMany(Applicant::class, 'hiring_event_applicants');
    }
}