<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoUppercase;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Training extends Model
{
    use SoftDeletes, AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    
    protected $fillable = [
        'title',
        'description',
        'department',
        'instructor',
        'category',
        'event_address',
        'training_mode',
        'start_date',
        'end_date',
        'duration',
        'status',
        'progress',
        'max_participants',
    ];

    protected $casts = [
        'start_date'       => 'datetime',
        'end_date'         => 'datetime',
        'progress'         => 'integer',
        'max_participants' => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function participants()
    {
        return $this->belongsToMany(Employee::class, 'training_participants')
                    ->withPivot('status', 'certificate_issued')
                    ->withTimestamps();
    }

    // ── Computed status based on dates ───────────────────────────────────────

    public function resolveStatus(): string
    {
        if ($this->status === 'cancelled') return 'cancelled';
        if (!$this->start_date || !$this->end_date) return 'draft';

        $now = Carbon::now();

        if ($now->lt($this->start_date))                          return 'upcoming';
        if ($now->between($this->start_date, $this->end_date))    return 'ongoing';
        if ($now->gt($this->end_date))                            return 'finished';

        return $this->status;
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeSearch($query, ?string $keyword)
    {
        if (!$keyword) return $query;

        return $query->where(function ($q) use ($keyword) {
            $q->where('title',      'like', "%{$keyword}%")
              ->orWhere('department', 'like', "%{$keyword}%")
              ->orWhere('category',   'like', "%{$keyword}%")
              ->orWhere('instructor', 'like', "%{$keyword}%");
        });
    }

    public function scopeByStatus($query, ?string $status)
    {
        if (!$status || $status === 'all') return $query;
        return $query->where('status', $status);
    }

    public function scopeByDepartment($query, ?string $department)
    {
        if (!$department || $department === 'all') return $query;
        return $query->where('department', $department);
    }
}