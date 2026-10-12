<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PschedLine extends Model
{
    protected $table = 'psched_lines';

    protected $fillable = ['division_id', 'unit_id', 'title', 'salary_grade', 'sort_order'];

    public function slots(): HasMany
    {
        return $this->hasMany(PschedSlot::class, 'line_id')->orderBy('sort_order')->orderBy('id');
    }
}
