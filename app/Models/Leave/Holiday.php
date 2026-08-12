<?php

namespace App\Models;

use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Holiday extends Model
{
    use SoftDeletes, AutoUppercase;

    protected array $uppercaseExclude = ['type', 'created_at', 'updated_at', 'deleted_at'];

    protected $fillable = ['name', 'date', 'type', 'is_recurring', 'created_by'];

    protected $casts = [
        'date' => 'date',
        'is_recurring' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('date', '>=', now()->toDateString())->orderBy('date');
    }
}
