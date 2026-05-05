<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlantillaItem extends Model
{
    protected $fillable = [
        'base_item_number',
        'title',
        'description',
        'approved_slots',
        'division_id',
        'department_ids',
    ];

    protected $casts = [
        'department_ids' => 'array',
    ];

    // ─── Relationships ────────────────────────────────────────────────────

    public function positions()
    {
        return $this->hasMany(PlantillaPosition::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    // ─── Slot Counts ──────────────────────────────────────────────────────

    public function remainingSlots(): int
    {
        return max(0, $this->approved_slots - $this->positions()->count());
    }

    public function hasAvailableSlots(): bool
    {
        return $this->remainingSlots() > 0;
    }

    public function getAvailableSlotsAttribute(): int
    {
        return $this->remainingSlots();
    }

    // ─── Model Events ─────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::deleting(function (PlantillaItem $item) {
            if ($item->positions()->filled()->exists()) {
                throw new \RuntimeException(
                    "Cannot delete plantilla item [{$item->title}] — it still has filled slots."
                );
            }
        });
    }
}