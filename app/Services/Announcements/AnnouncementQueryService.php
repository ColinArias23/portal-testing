<?php

namespace App\Services\Announcements;

use App\Models\Announcement;
use App\Repositories\Announcements\AnnouncementRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AnnouncementQueryService
{
    public function __construct(
        private readonly AnnouncementRepository $announcements,
    ) {
    }

    public function list(Request $request): LengthAwarePaginator
    {
        $query = $this->announcements->newIndexQuery()
            ->where('is_archived', $request->boolean('archived'));

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $priorities = $request->has('priorities')
            ? (array) $request->get('priorities')
            : array_filter([$request->get('priority')]);

        if (! empty($priorities)) {
            $query->whereIn('priority', $priorities);
        }

        if ($request->has('departments')) {
            $this->announcements->applyDepartmentFilter($query, (array) $request->get('departments'));
        }

        if ($request->boolean('pinned_only')) {
            $query->where('is_pinned', true);
        }

        if ($request->boolean('unread_only')) {
            $userId = $request->user()->id;
            $query->whereDoesntHave('views', fn ($q) => $q->where('user_id', $userId));
        }

        $sort = $request->get('sort', 'newest');
        match ($sort) {
            'oldest' => $query->oldest(),
            'views' => $query->orderByDesc('views_count'),
            'reactions' => $query->withCount('reactions')->orderByDesc('reactions_count'),
            default => $query->latest(),
        };

        $query->orderByDesc('is_pinned');

        $perPage = min((int) $request->get('per_page', 10), 50);
        $announcements = $this->announcements->paginate($query, $perPage);

        $this->hydrateComputedFields($announcements->getCollection(), $request->user()->id);

        return $announcements;
    }

    public function show(int $id, int $userId): Announcement
    {
        $announcement = $this->announcements->findForShow($id);

        $announcement->my_reaction = $this->announcements->myReaction($announcement->id, $userId);
        $announcement->reactions_summary = $this->announcements->reactionSummary($announcement->id);
        $announcement->unread = $announcement->created_by !== $userId
            && ! $this->announcements->hasBeenViewedBy($announcement->id, $userId);

        return $announcement;
    }

    private function hydrateComputedFields(Collection $collection, int $userId): void
    {
        $ids = $collection->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $viewedIds = $this->announcements->viewedAnnouncementIds($userId, $ids);
        $reactionCounts = $this->announcements->reactionCountsFor($ids);
        $myReactions = $this->announcements->myReactionsFor($ids, $userId);

        $collection->each(function (Announcement $a) use ($userId, $viewedIds, $reactionCounts, $myReactions) {
            $a->unread = $a->created_by !== $userId && ! in_array($a->id, $viewedIds, true);
            $a->my_reaction = $myReactions[$a->id] ?? null;
            $a->reactions_summary = ($reactionCounts[$a->id] ?? collect())
                ->pluck('count', 'type')
                ->toArray();
        });
    }
}
