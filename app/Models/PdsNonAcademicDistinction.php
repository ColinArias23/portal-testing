<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdsNonAcademicDistinction extends Model
{
    protected $table = 'pds_non_academic_distinctions';
    protected $fillable = ['employee_id', 'distinction', 'order'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}