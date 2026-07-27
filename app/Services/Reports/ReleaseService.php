<?php

namespace App\Services\Reports;

use App\Models\Release;
use App\Repositories\Reports\ReleaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReleaseService
{
    public function __construct(
        private readonly ReleaseRepository $releases,
    ) {
    }

    public function index(int $perPage): LengthAwarePaginator
    {
        return $this->releases->paginatePublished($perPage);
    }

    public function show(Release $release): Release
    {
        abort_unless($release->isPublished(), 404);

        return $this->releases->loadReports($release);
    }

    public function store(array $validated): array
    {
        try {
            $release = DB::transaction(function () use ($validated) {
                $release = $this->releases->create([
                    'version' => $validated['version'],
                    'title'   => $validated['title'] ?? "Version {$validated['version']}",
                    'date'    => $validated['date'] ?? today()->toDateString(),
                    'status'  => 'published',
                ]);

                $this->releases->attachReports($release, $validated['report_ids']);

                return $release;
            });
        } catch (Throwable $e) {
            Log::error('Failed to create release', [
                'error'   => $e->getMessage(),
                'version' => $validated['version'],
            ]);

            return ['success' => false, 'status' => 503, 'message' => 'Unable to publish release. Please try again.'];
        }

        return ['success' => true, 'release' => $this->releases->loadReports($release)];
    }

    public function destroy(Release $release): void
    {
        DB::transaction(fn () => $this->releases->detachReportsAndDelete($release));
    }

    public function unassignedResolved(): Collection
    {
        return $this->releases->unassignedResolvedReports();
    }
}
