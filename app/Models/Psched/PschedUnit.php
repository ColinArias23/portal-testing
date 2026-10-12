<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PschedUnit extends Model
{
    protected $table = 'psched_units';

    protected $fillable = ['division_id', 'name', 'type', 'status', 'sort_order'];

    public function division(): BelongsTo
    {
        return $this->belongsTo(PschedDivision::class, 'division_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PschedLine::class, 'unit_id');
    }
}
