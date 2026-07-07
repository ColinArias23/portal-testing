<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class NotificationCreated implements ShouldBroadcastNow
{
    public function __construct(public Notification $notification) {}

    public function broadcastOn(): Channel
    {
        return match ($this->notification->visibility) {
            'admin'   => new PrivateChannel('admin.notifications'),
            'user'    => new PrivateChannel('App.Models.User.'.$this->notification->target_user_id),
            default   => new PrivateChannel('notifications'),
        };
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id'                => $this->notification->id,
            'type'              => $this->notification->type,
            'visibility'        => $this->notification->visibility,
            'title'             => $this->notification->title,
            'message'           => $this->notification->message,
            'posted_by_user_id' => $this->notification->data['posted_by_user_id'] ?? null,
        ];
    }
}