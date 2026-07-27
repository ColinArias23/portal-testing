<?php

namespace App\Services\Announcements;

use App\Repositories\Announcements\AnnouncementRepository;
use App\Traits\Concerns\ResolvesAvatarUrl;

class AnnouncementInteractionService
{
    use ResolvesAvatarUrl;

    public function __construct(
        private readonly AnnouncementRepository $announcements,
    ) {
    }

    public function markViewed(int $announcementId, int $userId): int
    {
        $announcement = $this->announcements->findOrFail($announcementId);

        if ($announcement->created_by === $userId) {
            return $this->announcements->viewsCount($announcementId);
        }

        $this->announcements->markViewed($announcementId, $userId);

        return $this->announcements->viewsCount($announcementId);
    }

    public function react(int $announcementId, int $userId, string $type): array
    {
        $existing = $this->announcements->findReaction($announcementId, $userId);

        if ($existing && $existing->type === $type) {
            $existing->delete();
        } elseif ($existing) {
            $existing->update(['type' => $type]);
        } else {
            $this->announcements->createReaction($announcementId, $userId, $type);
        }

        return [
            'reactions_summary' => $this->announcements->reactionSummary($announcementId),
            'my_reaction'       => $this->announcements->myReaction($announcementId, $userId),
        ];
    }

    public function viewers(int $announcementId): array
    {
        $this->announcements->findOrFail($announcementId);

        $views = $this->announcements->recentViewers($announcementId);

        return [
            'viewers' => $views->map(fn ($v) => [
                'id'         => $v->user->id,
                'name'       => $v->user->name,
                'initials'   => collect(explode(' ', $v->user->name))
                    ->map(fn ($p) => mb_substr($p, 0, 1))
                    ->take(2)->implode(''),
                'avatar_url' => $this->resolveAvatarUrl(
                    $v->user->employee?->getRawOriginal('avatar_url')
                ),
            ])->all(),
            'total' => $this->announcements->viewsCount($announcementId),
        ];
    }
}
