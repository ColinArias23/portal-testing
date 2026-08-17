<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Models\Training;
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
        $ids = $this->notifications->unreadIdsForUser($user);

        $this->notifications->markManyRead($ids, $user->id);
    }

    public function unreadCount(User $user): int
    {
        return $this->notifications->unreadCountForUser($user);
    }

    public function notifyAccountPending(User $user): Notification
    {
        return Notification::create([
            'type'       => 'account_pending',
            'title'      => 'New account pending approval',
            'message'    => "{$user->username} registered and is awaiting activation.",
            'data'       => ['user_id' => $user->id],
            'visibility' => 'admin',
        ]);
    }

    public function notifyTrainingCreated(Training $training, int $postedByUserId): Notification
    {
        return Notification::create([
            'type'       => 'training',
            'title'      => 'New Training Program',
            'message'    => "A new training program \"{$training->title}\" has been posted.",
            'data'       => [
                'training_id'        => $training->id,
                'posted_by_user_id'  => $postedByUserId,
            ],
            'visibility' => 'all',
        ]);
    }
}