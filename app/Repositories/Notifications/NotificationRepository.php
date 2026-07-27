<?php

namespace App\Repositories\Notifications;

use App\Models\Notification;
use App\Models\NotificationRead;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationRepository
{
    private const PAGE_SIZE = 50;

    public function recentForUser(User $user): Collection
    {
        return Notification::relevantTo($user)
            ->with(['reads' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderByDesc('created_at')
            ->limit(self::PAGE_SIZE)
            ->get();
    }

    public function isRelevantToUser(int $notificationId, User $user): bool
    {
        return Notification::relevantTo($user)->whereKey($notificationId)->exists();
    }

    public function markRead(int $notificationId, int $userId): void
    {
        NotificationRead::firstOrCreate([
            'notification_id' => $notificationId,
            'user_id'         => $userId,
        ]);
    }

    public function relevantIdsForUser(User $user): Collection
    {
        return Notification::relevantTo($user)->pluck('id');
    }

    public function markManyRead(Collection $notificationIds, int $userId): void
    {
        if ($notificationIds->isEmpty()) {
            return;
        }

        $now = now();
        $rows = $notificationIds->map(fn ($id) => [
            'notification_id' => $id,
            'user_id'         => $userId,
            'read_at'         => $now,
        ])->all();

        NotificationRead::upsert($rows, uniqueBy: ['notification_id', 'user_id'], update: []);
    }

    public function unreadCountForUser(User $user): int
    {
        return Notification::relevantTo($user)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))
            ->count();
    }
}
