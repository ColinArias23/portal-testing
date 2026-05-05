<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ConsultantPosition extends Model
{
    protected $table = 'consultant_positions';

    protected $fillable = [
        'title',
        'description',
    ];

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where('title', 'like', "%{$term}%");
    }
}