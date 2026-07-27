<?php

namespace App\Repositories\Recruitment\Hiring;

use App\Models\Onboarding;
use App\Models\OnboardingTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class OnboardingRepository
{
    private const RELATIONS = ['tasks', 'employee', 'applicant'];

    public function newQuery(): Builder
    {
        return Onboarding::with(self::RELATIONS)->orderByDesc('created_at');
    }

    public function paginate(Builder $query, int $perPage): LengthAwarePaginator
    {
        return $query->paginate($perPage);
    }

    public function create(array $attributes): Onboarding
    {
        return Onboarding::create($attributes);
    }

    public function createTasks(int $onboardingId, array $titles): void
    {
        foreach ($titles as $index => $title) {
            OnboardingTask::create([
                'onboarding_id' => $onboardingId,
                'title'         => $title,
                'completed'     => false,
                'completed_at'  => null,
                'sort_order'    => $index,
            ]);
        }
    }

    public function loadRelations(Onboarding $onboarding): Onboarding
    {
        return $onboarding->load(self::RELATIONS);
    }

    public function update(Onboarding $onboarding, array $data): void
    {
        $onboarding->update($data);
    }

    public function delete(Onboarding $onboarding): void
    {
        $onboarding->delete();
    }

    public function taskCounts(Onboarding $onboarding): array
    {
        return [
            'total' => $onboarding->tasks()->count(),
            'done'  => $onboarding->tasks()->where('completed', true)->count(),
        ];
    }

    public function lastTaskSortOrder(Onboarding $onboarding): int
    {
        return $onboarding->tasks()->max('sort_order') ?? -1;
    }

    public function createTask(array $attributes): OnboardingTask
    {
        return OnboardingTask::create($attributes);
    }

    public function updateTask(OnboardingTask $task, array $data): void
    {
        $task->update($data);
    }

    public function deleteTask(OnboardingTask $task): void
    {
        $task->delete();
    }

    /**
     * Was 3 of the 4 summary() counts as separate queries
     * (`new_hires` needs its own COUNT(*), `pending_tasks` is a
     * different table so also stays separate) — `in_progress` and
     * `completed` are now one grouped query instead of two.
     *
     * @return array{new_hires:int, in_progress:int, completed:int, pending_tasks:int}
     */
    public function summaryCounts(): array
    {
        $newHires = Onboarding::count();

        $statusCounts = Onboarding::whereIn('status', ['In Progress', 'Completed'])
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $pendingTasks = OnboardingTask::where('completed', false)->count();

        return [
            'new_hires'     => $newHires,
            'in_progress'   => (int) ($statusCounts['In Progress'] ?? 0),
            'completed'     => (int) ($statusCounts['Completed'] ?? 0),
            'pending_tasks' => $pendingTasks,
        ];
    }
}
