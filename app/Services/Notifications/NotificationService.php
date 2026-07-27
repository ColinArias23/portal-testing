<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Models\User;
use App\Repositories\Notifications\NotificationRepository;
class NotificationService
{
    public function __construct(
        private readonly NotificationRepository $notifications,
    ) {
    }

    public function index(User $user): array
    {
        $notifications = $this->notifications->recentForUser($user)
            ->map(fn ($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'title'      => $n->title,
                'message'    => $n->message,
                'data'       => $n->data,
                'read'       => $n->reads->isNotEmpty(),
                'created_at' => $n->created_at,
                'time'       => $n->created_at->diffForHumans(),
            ]);

        return [
            'notifications' => $notifications->values(),
            'unread_count'  => $notifications->where('read', false)->count(),
        ];
    }

    public function markRead(Notification $notification, User $user): void
    {
        abort_unless($this->notifications->isRelevantToUser($notification->id, $user), 403);

        $this->notifications->markRead($notification->id, $user->id);
    }

    public function markAllRead(User $user): void
    {
        $ids = $this->notifications->relevantIdsForUser($user);

        $this->notifications->markManyRead($ids, $user->id);
    }

    public function unreadCount(User $user): int
    {
        return $this->notifications->unreadCountForUser($user);
    }
}
