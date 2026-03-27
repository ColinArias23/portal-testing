<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'type',
        'title',
        'message',
        'data',
        'visibility',
        'target_user_id',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function reads()
    {
        return $this->hasMany(NotificationRead::class);
    }

    public function isReadBy(int $userId): bool
    {
        return $this->reads()->where('user_id', $userId)->exists();
    }

    public function scopeVisibleTo($query, User $user)
    {
        $isAdmin = $user->hasAnyRole(['SuperAdmin', 'HR', 'Head']);

        return $query->where(function ($q) use ($user, $isAdmin) {
            $q->where('visibility', 'all');

            if ($isAdmin) {
                $q->orWhere('visibility', 'admin');
            }

            $q->orWhere(function ($q2) use ($user) {
                $q2->where('visibility', 'user')
                   ->where('target_user_id', $user->id);
            });
        });
    }
}