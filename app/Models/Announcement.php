<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title', 'description', 'priority', 'category', 'is_pinned', 'is_archived', 'created_by',
    ];

    protected $casts = [
        'is_pinned' => 'boolean', 'is_archived' => 'boolean',
    ];

    protected $appends = [];

    public function plantillaPostings()
    {
        return $this->belongsToMany(PlantillaPosting::class, 'announcement_plantilla_posting')
            ->withTimestamps();
    }

    public function attachments()
    {
        return $this->hasMany(AnnouncementAttachment::class);
    }

    public function comments()
    {
        return $this->hasMany(AnnouncementComment::class)->whereNull('parent_id');
    }

    public function reactions()
    {
        return $this->hasMany(AnnouncementReaction::class);
    }

    public function views()
    {
        return $this->hasMany(AnnouncementView::class);
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function getViewsCountAttribute(): int
    {
        return $this->attributes['views_count'] ?? $this->views()->count();
    }

    public function getReactionsSummaryAttribute(): array
    {
        if (isset($this->attributes['reactions_summary'])) {
            $value = $this->attributes['reactions_summary'];
            return is_string($value) ? json_decode($value, true) : $value;
        }

        return $this->reactions()
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();
    }
}