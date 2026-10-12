<?php

namespace App\Models;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DtrAttendance extends Model
{
    protected $table = 'dtr_attendances';

    protected $fillable = [
        'employee_id', 'work_date',
        'shift_id', 'shift_start', 'shift_end', 'shift_grace_minutes',
        'time_in', 'time_in_image_path', 'time_in_source',
        'time_out', 'time_out_date', 'time_out_image_path', 'time_out_source',
        'legacy_id',
    ];

    protected $casts = [
        'work_date'     => 'date:Y-m-d',
        'time_out_date' => 'date:Y-m-d',
        'shift_grace_minutes' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(DtrShift::class, 'shift_id');
    }
}
