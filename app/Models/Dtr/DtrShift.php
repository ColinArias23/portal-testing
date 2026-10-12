<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DtrShift extends Model
{
    protected $table = 'dtr_shifts';

    protected $fillable = ['name', 'start_time', 'end_time', 'grace_minutes', 'is_default', 'is_active'];

    protected $casts = [
        'grace_minutes' => 'integer',
        'is_default'    => 'boolean',
        'is_active'     => 'boolean',
    ];

    public function employeeShifts(): HasMany
    {
        return $this->hasMany(DtrEmployeeShift::class, 'shift_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(DtrAttendance::class, 'shift_id');
    }

    public function getCrossesMidnightAttribute(): bool
    {
        return $this->end_time <= $this->start_time;
    }
}
