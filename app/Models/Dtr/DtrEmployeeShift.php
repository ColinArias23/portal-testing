<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DtrEmployeeShift extends Model
{
    protected $table = 'dtr_employee_shifts';

    protected $fillable = ['employee_id', 'shift_id'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(DtrShift::class, 'shift_id');
    }
}
