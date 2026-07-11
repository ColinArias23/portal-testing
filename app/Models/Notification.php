<?php

namespace App\Models;

use App\Events\NotificationCreated;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoUppercase;

class Notification extends Model
{

    use AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
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

    protected static function booted(): void
    {
        static::created(function ($notification) {
            broadcast(new NotificationCreated($notification))->toOthers();
        });
    }

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
        $isAdmin = $user->hasAnyRole(['SuperAdmin', 'HR', 'Admin']);

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

    public function scopeRelevantTo($query, $user)
    {
        $selfExcludedTypes = ['training', 'announcement'];

        return $query->visibleTo($user)->where(function ($q) use ($user, $selfExcludedTypes) {
            $q->whereNotIn('type', $selfExcludedTypes)
                ->orWhere(function ($q2) use ($user, $selfExcludedTypes) {
                    $q2->whereIn('type', $selfExcludedTypes)
                        ->where(function ($q3) use ($user) {
                            $q3->whereNull('data->posted_by_user_id')
                                ->orWhere('data->posted_by_user_id', '!=', $user->id);
                        });
                });
        });
    }
}