<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class AnnouncementDeleted implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public int $announcementId)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('announcements')];
    }

    public function broadcastAs(): string
    {
        return 'announcement.deleted';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->announcementId];
    }
}