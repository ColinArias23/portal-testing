<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PschedDivision extends Model
{
    protected $table = 'psched_divisions';

    protected $fillable = ['name', 'type', 'status', 'sort_order'];

    public function units(): HasMany
    {
        return $this->hasMany(PschedUnit::class, 'division_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PschedLine::class, 'division_id');
    }
}
