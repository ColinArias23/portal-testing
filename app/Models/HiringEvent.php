<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HiringEvent extends Model
{
    use SoftDeletes;

    protected $table = 'hiring_events';

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