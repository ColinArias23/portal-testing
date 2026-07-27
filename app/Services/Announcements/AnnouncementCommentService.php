<?php

namespace App\Services\Announcements;

use App\Models\AnnouncementComment;
use App\Repositories\Announcements\AnnouncementRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class AnnouncementCommentService
{
    public function __construct(
        private readonly AnnouncementRepository $announcements,
    ) {
    }

    public function list(int $announcementId): Collection
    {
        $this->announcements->findOrFail($announcementId);

        return $this->announcements->commentsFor($announcementId);
    }

    public function add(int $announcementId, int $userId, string $body, ?int $parentId): AnnouncementComment
    {
        $this->announcements->findOrFail($announcementId);

        $comment = $this->announcements->createComment([
            'announcement_id' => $announcementId,
            'parent_id'       => $parentId,
            'user_id'         => $userId,
            'body'            => $body,
        ]);

        return $comment->fresh('user.employee');
    }

    public function update(int $commentId, int $userId, string $body): AnnouncementComment
    {
        $comment = $this->announcements->findComment($commentId);

        if ($comment->user_id !== $userId) {
            abort(403);
        }

        $comment->update([
            'body'      => $body,
            'edited_at' => now(),
        ]);

        return $comment->fresh('user.employee');
    }

    public function delete(int $commentId, Request $request): void
    {
        $comment = $this->announcements->findComment($commentId);

        $isOwner = $comment->user_id === $request->user()->id;
        $canManageAny = $request->user()->can('announcements.manage');

        if (! $isOwner && ! $canManageAny) {
            abort(403);
        }

        $comment->delete();
    }
}
