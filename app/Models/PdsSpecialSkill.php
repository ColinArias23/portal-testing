<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdsSpecialSkill extends Model
{
    protected $table = 'pds_special_skills';
    protected $fillable = ['employee_id', 'skill', 'order'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}