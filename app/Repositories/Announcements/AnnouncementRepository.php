<?php

namespace App\Repositories\Announcements;

use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\AnnouncementReaction;
use App\Models\AnnouncementView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;

class AnnouncementRepository
{
    private function baseWith(): array
    {
        return [
            'creator' => fn ($q) => $q->select(['id', 'username', 'employee_id']),
            'creator.employee' => fn ($q) => $q->select(['id', 'first_name', 'last_name', 'avatar_url', 'department_ids']),
            'attachments' => fn ($q) => $q->select(
                ['id', 'announcement_id', 'original_filename', 'stored_path', 'mime_type', 'size', 'downloads_count']
            ),
            'plantillaPostings:id,title',
        ];
    }

    public function newIndexQuery(): Builder
    {
        return Announcement::with($this->baseWith())
            ->withCount(['comments', 'views']);
    }

    public function paginate(Builder $query, int $perPage): LengthAwarePaginator
    {
        return $query->paginate($perPage);
    }

    public function applyDepartmentFilter(Builder $query, array $departmentNames): void
    {
        $query->whereHas(
            'creator.employee.primaryDepartmentAssignment.department',
            fn ($q) => $q->whereIn('name', $departmentNames)
        );
    }

    public function findForShow(int $id): Announcement
    {
        return Announcement::with([
            ...$this->baseWith(),
            'comments.user.employee:id,first_name,last_name,avatar_url',
            'comments.user:id,username,employee_id',
            'comments.replies.user.employee:id,first_name,last_name,avatar_url',
            'comments.replies.user:id,username,employee_id',
        ])->withCount('views')->findOrFail($id);
    }

    public function findOrFail(int $id): Announcement
    {
        return Announcement::findOrFail($id);
    }

    public function viewedAnnouncementIds(int $userId, SupportCollection $announcementIds): array
    {
        return AnnouncementView::where('user_id', $userId)
            ->whereIn('announcement_id', $announcementIds)
            ->pluck('announcement_id')
            ->all();
    }

    public function reactionCountsFor(SupportCollection $announcementIds): Collection
    {
        return AnnouncementReaction::whereIn('announcement_id', $announcementIds)
            ->selectRaw('announcement_id, type, count(*) as count')
            ->groupBy('announcement_id', 'type')
            ->get()
            ->groupBy('announcement_id');
    }

    public function myReactionsFor(SupportCollection $announcementIds, int $userId): SupportCollection
    {
        return AnnouncementReaction::whereIn('announcement_id', $announcementIds)
            ->where('user_id', $userId)
            ->pluck('type', 'announcement_id');
    }

    public function reactionSummary(int $announcementId): array
    {
        return AnnouncementReaction::where('announcement_id', $announcementId)
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();
    }

    public function myReaction(int $announcementId, int $userId): ?string
    {
        return AnnouncementReaction::where('announcement_id', $announcementId)
            ->where('user_id', $userId)
            ->value('type');
    }

    public function findReaction(int $announcementId, int $userId): ?AnnouncementReaction
    {
        return AnnouncementReaction::where('announcement_id', $announcementId)
            ->where('user_id', $userId)
            ->first();
    }

    public function createReaction(int $announcementId, int $userId, string $type): void
    {
        AnnouncementReaction::create([
            'announcement_id' => $announcementId,
            'user_id'         => $userId,
            'type'            => $type,
        ]);
    }

    public function hasBeenViewedBy(int $announcementId, int $userId): bool
    {
        return AnnouncementView::where('announcement_id', $announcementId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function markViewed(int $announcementId, int $userId): void
    {
        AnnouncementView::firstOrCreate(
            ['announcement_id' => $announcementId, 'user_id' => $userId],
            ['viewed_at' => now()]
        );
    }

    public function viewsCount(int $announcementId): int
    {
        return AnnouncementView::where('announcement_id', $announcementId)->count();
    }

    public function recentViewers(int $announcementId, int $limit = 20): Collection
    {
        return AnnouncementView::where('announcement_id', $announcementId)
            ->with([
                'user' => fn ($q) => $q->select('id', 'username', 'employee_id')
                    ->with('employee:id,first_name,last_name,avatar_url'),
            ])
            ->latest('viewed_at')
            ->limit($limit)
            ->get();
    }

    public function commentsFor(int $announcementId): Collection
    {
        return AnnouncementComment::where('announcement_id', $announcementId)
            ->whereNull('parent_id')
            ->with([
                'user.employee:id,first_name,last_name,avatar_url',
                'user:id,username,employee_id',
                'replies.user.employee:id,first_name,last_name,avatar_url',
                'replies.user:id,username,employee_id',
            ])
            ->latest()
            ->get();
    }

    public function findComment(int $commentId): AnnouncementComment
    {
        return AnnouncementComment::findOrFail($commentId);
    }

    public function createComment(array $attributes): AnnouncementComment
    {
        return AnnouncementComment::create($attributes);
    }
}