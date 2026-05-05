<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NotificationCreated implements ShouldBroadcast
{
    public function __construct(public Notification $notification) {}

    public function broadcastOn(): Channel
    {
        return new Channel('notifications');
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'type'              => $this->notification->type,
            'visibility'        => $this->notification->visibility,
            'posted_by_user_id' => $this->notification->data['posted_by_user_id'] ?? null,
        ];
    }
}