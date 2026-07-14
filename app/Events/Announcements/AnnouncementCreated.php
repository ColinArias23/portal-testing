<?php

namespace App\Events\Announcements;

use App\Models\Announcement;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class AnnouncementCreated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public Announcement $announcement)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('announcements')];
    }

    public function broadcastAs(): string
    {
        return 'announcement.created';
    }

    public function broadcastWith(): array
    {
        $a = $this->announcement;
        $creator = $a->creator;

        return [
            'id' => $a->id,
            'title' => $a->title,
            'description' => $a->description,
            'priority' => $a->priority,
            'category' => $a->category,
            'is_pinned' => $a->is_pinned,
            'is_archived' => $a->is_archived,
            'created_at' => $a->created_at,
            'updated_at' => $a->updated_at,
            'creator' => [
                'id' => $creator?->id,
                'name' => $creator?->name,
                'avatar_url' => $creator?->employee?->avatar_url,
                'department' => $creator?->employee?->department
                    ? ['id' => $creator->employee->department->id, 'name' => $creator->employee->department->name]
                    : null,
            ],
            'attachments' => $a->attachments->map(fn ($att) => [
                'id' => $att->id,
                'original_filename' => $att->original_filename,
                'mime_type' => $att->mime_type,
                'size' => $att->size,
                'downloads_count' => $att->downloads_count,
            ])->values(),
            'plantilla_postings' => $a->plantillaPostings->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
            ])->values(),
            'views_count' => 0,
            'comments_count' => 0,
            'reactions_summary' => [],
            'my_reaction' => null,
        ];
    }
}