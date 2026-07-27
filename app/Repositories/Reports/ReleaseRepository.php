<?php

namespace App\Repositories\Reports;

use App\Models\Release;
use App\Models\Report;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ReleaseRepository
{
    private const REPORT_SUMMARY_COLUMNS = ['id', 'subject', 'type', 'category', 'severity'];

    public function paginatePublished(int $perPage): LengthAwarePaginator
    {
        return Release::query()
            ->with(['reports:' . implode(',', self::REPORT_SUMMARY_COLUMNS)])
            ->where('status', 'published')
            ->orderByDesc('date')
            ->paginate($perPage);
    }

    public function loadReports(Release $release): Release
    {
        return $release->load(['reports:' . implode(',', self::REPORT_SUMMARY_COLUMNS)]);
    }

    public function create(array $attributes): Release
    {
        return Release::create($attributes);
    }

    public function attachReports(Release $release, array $reportIds): void
    {
        $release->reports()->attach($reportIds);
    }

    public function detachReportsAndDelete(Release $release): void
    {
        $release->reports()->detach();
        $release->delete();
    }

    public function unassignedResolvedReports(): Collection
    {
        return Report::query()
            ->with('user:id,username')
            ->where('status', 'resolved')
            ->whereDoesntHave('releases')
            ->orderByDesc('resolved_at')
            ->get();
    }
}
