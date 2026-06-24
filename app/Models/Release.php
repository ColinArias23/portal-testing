<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoUppercase;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Release extends Model
{
    use HasFactory, AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
    protected $fillable = [
        'version',
        'title',
        'date',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function reports(): BelongsToMany
    {
        return $this->belongsToMany(Report::class, 'release_reports')
            ->withTimestamps();
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}