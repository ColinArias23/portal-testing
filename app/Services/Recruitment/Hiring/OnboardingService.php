<?php

namespace App\Services\Recruitment\Hiring;

use App\Events\Recruitment\Hiring\OnboardingUpdated;
use App\Models\ActivityLog;
use App\Models\Onboarding;
use App\Models\OnboardingTask;
use App\Repositories\Recruitment\Hiring\OnboardingRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OnboardingService
{
    private const DEFAULT_TASKS = [
        'Submit government IDs',
        'Sign employment contract',
        'Complete HR orientation',
        'Set up company email',
        'Meet the team',
        'Complete mandatory trainings',
    ];

    public function __construct(
        private readonly OnboardingRepository $onboardings,
    ) {
    }

    public function format(Onboarding $onboarding): array
    {
        $tasks     = $onboarding->tasks;
        $total     = $tasks->count();
        $completed = $tasks->where('completed', true)->count();

        return [
            'id'           => $onboarding->id,
            'name'         => $onboarding->name,
            'position'     => $onboarding->position,
            'department'   => $onboarding->department,
            'start_date'   => $onboarding->start_date?->setTimezone('Asia/Manila')->toDateTimeString(),
            'applicant_id' => $onboarding->applicant_id,
            'employee_id'  => $onboarding->employee_id,
            'status'       => $onboarding->status,
            'progress'     => $total > 0 ? round(($completed / $total) * 100) : 0,
            'tasks'        => $tasks->map(fn ($t) => [
                'id'           => $t->id,
                'title'        => $t->title,
                'completed'    => $t->completed,
                'completed_at' => $t->completed_at?->setTimezone('Asia/Manila')->toDateTimeString(),
                'sort_order'   => $t->sort_order,
            ])->values(),
            'employee'   => $onboarding->employee?->only(['id', 'full_name']),
            'applicant'  => $onboarding->applicant?->only(['id', 'full_name']),
            'created_at' => $onboarding->created_at,
        ];
    }

    private function broadcastUpdate(Onboarding $onboarding): void
    {
        broadcast(new OnboardingUpdated($this->format($onboarding)))->toOthers();
    }

    public function assertTaskBelongsToOnboarding(OnboardingTask $task, Onboarding $onboarding): bool
    {
        return $task->onboarding_id === $onboarding->id;
    }

    public function summary(): array
    {
        return $this->onboardings->summaryCounts();
    }

    public function index(Request $request): LengthAwarePaginator
    {
        $query = $this->onboardings->newQuery();

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('position', 'like', "%{$term}%")
                    ->orWhere('department', 'like', "%{$term}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->onboardings->paginate($query, (int) ($request->per_page ?? 15));
    }

    public function store(Request $request, array $data): Onboarding
    {
        $onboarding = DB::transaction(function () use ($data) {
            $onboarding = $this->onboardings->create([
                'name'         => $data['name'],
                'position'     => $data['position']     ?? null,
                'department'   => $data['department']   ?? null,
                'start_date'   => $data['start_date']   ?? null,
                'employee_id'  => $data['employee_id']  ?? null,
                'applicant_id' => $data['applicant_id'] ?? null,
                'status'       => 'Pending',
            ]);

            $taskTitles = ! empty($data['tasks']) ? $data['tasks'] : self::DEFAULT_TASKS;
            $this->onboardings->createTasks($onboarding->id, $taskTitles);

            return $this->onboardings->loadRelations($onboarding->fresh());
        });

        ActivityLog::record(
            action:       'onboarding.created',
            userId:       $request->user()?->id,
            subjectType:  Onboarding::class,
            subjectId:    $onboarding->id,
            subjectLabel: $onboarding->name,
            payload:      [
                'position'   => $onboarding->position,
                'department' => $onboarding->department,
                'start_date' => $onboarding->start_date?->toDateTimeString(),
                'task_count' => $onboarding->tasks->count(),
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        $this->broadcastUpdate($onboarding);

        return $onboarding;
    }

    public function show(Onboarding $onboarding): Onboarding
    {
        return $this->onboardings->loadRelations($onboarding);
    }

    public function update(Request $request, Onboarding $onboarding, array $data): Onboarding
    {
        $before = [
            'status'     => $onboarding->status,
            'start_date' => $onboarding->start_date?->toDateTimeString(),
        ];

        $this->onboardings->update($onboarding, $data);

        if (! isset($data['status'])) {
            $computedStatus = $onboarding->fresh()->load('tasks')->computeStatus();
            $this->onboardings->update($onboarding, ['status' => $computedStatus]);
        }

        $this->onboardings->loadRelations($onboarding);

        ActivityLog::record(
            action:       'onboarding.updated',
            userId:       $request->user()?->id,
            subjectType:  Onboarding::class,
            subjectId:    $onboarding->id,
            subjectLabel: $onboarding->name,
            payload:      [
                'before' => $before,
                'after'  => [
                    'status'     => $onboarding->status,
                    'start_date' => $onboarding->start_date?->toDateTimeString(),
                ],
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        $this->broadcastUpdate($onboarding);

        return $onboarding;
    }

    public function destroy(Request $request, Onboarding $onboarding): void
    {
        $id      = $onboarding->id;
        $label   = $onboarding->name;
        $counts  = $this->onboardings->taskCounts($onboarding);

        $this->onboardings->delete($onboarding);

        ActivityLog::record(
            action:       'onboarding.deleted',
            userId:       $request->user()?->id,
            subjectType:  Onboarding::class,
            subjectId:    $id,
            subjectLabel: $label,
            payload:      [
                'tasks_total'     => $counts['total'],
                'tasks_completed' => $counts['done'],
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        broadcast(new \App\Events\OnboardingDeleted($id))->toOthers();
    }

    public function toggleTask(Request $request, Onboarding $onboarding, OnboardingTask $task): Onboarding
    {
        $wasCompleted = $task->completed;
        $nowCompleted = ! $wasCompleted;
        $now          = Carbon::now('Asia/Manila');

        $this->onboardings->updateTask($task, [
            'completed'    => $nowCompleted,
            'completed_at' => $nowCompleted ? $now : null,
        ]);

        $freshOnboarding = $onboarding->fresh()->load('tasks');
        $newStatus        = $freshOnboarding->computeStatus();
        $allDone           = $newStatus === 'Completed';

        $updatePayload = ['status' => $newStatus];

        if ($allDone) {
            $updatePayload['start_date'] = $now;
        } elseif ($wasCompleted && $onboarding->status === 'Completed') {
            $updatePayload['start_date'] = null;
        }

        $this->onboardings->update($onboarding, $updatePayload);

        ActivityLog::record(
            action:       'onboarding.task_toggled',
            userId:       $request->user()?->id,
            subjectType:  Onboarding::class,
            subjectId:    $onboarding->id,
            subjectLabel: $onboarding->name,
            payload:      [
                'task_id'               => $task->id,
                'task_title'            => $task->title,
                'from'                  => $wasCompleted ? 'completed' : 'pending',
                'to'                    => $nowCompleted ? 'completed' : 'pending',
                'new_onboarding_status' => $newStatus,
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        $this->onboardings->loadRelations($onboarding);
        $this->broadcastUpdate($onboarding);

        return $onboarding;
    }

    public function addTask(Request $request, Onboarding $onboarding, string $title): Onboarding
    {
        $lastOrder = $this->onboardings->lastTaskSortOrder($onboarding);

        $task = $this->onboardings->createTask([
            'onboarding_id' => $onboarding->id,
            'title'         => $title,
            'completed'     => false,
            'completed_at'  => null,
            'sort_order'    => $lastOrder + 1,
        ]);

        ActivityLog::record(
            action:       'onboarding.task_added',
            userId:       $request->user()?->id,
            subjectType:  Onboarding::class,
            subjectId:    $onboarding->id,
            subjectLabel: $onboarding->name,
            payload:      [
                'task_id'    => $task->id,
                'task_title' => $task->title,
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        $this->onboardings->loadRelations($onboarding);
        $this->broadcastUpdate($onboarding);

        return $onboarding;
    }

    public function updateTask(Request $request, Onboarding $onboarding, OnboardingTask $task, string $title): Onboarding
    {
        $oldTitle = $task->title;
        $this->onboardings->updateTask($task, ['title' => $title]);

        ActivityLog::record(
            action:       'onboarding.task_updated',
            userId:       $request->user()?->id,
            subjectType:  Onboarding::class,
            subjectId:    $onboarding->id,
            subjectLabel: $onboarding->name,
            payload:      [
                'task_id'   => $task->id,
                'old_title' => $oldTitle,
                'new_title' => $task->title,
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        $this->onboardings->loadRelations($onboarding);
        $this->broadcastUpdate($onboarding);

        return $onboarding;
    }

    public function deleteTask(Request $request, Onboarding $onboarding, OnboardingTask $task): Onboarding
    {
        $taskId    = $task->id;
        $taskTitle = $task->title;

        $this->onboardings->deleteTask($task);

        $newStatus = $onboarding->fresh()->load('tasks')->computeStatus();
        $this->onboardings->update($onboarding, ['status' => $newStatus]);

        ActivityLog::record(
            action:       'onboarding.task_deleted',
            userId:       $request->user()?->id,
            subjectType:  Onboarding::class,
            subjectId:    $onboarding->id,
            subjectLabel: $onboarding->name,
            payload:      [
                'task_id'    => $taskId,
                'task_title' => $taskTitle,
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        $this->onboardings->loadRelations($onboarding);
        $this->broadcastUpdate($onboarding);

        return $onboarding;
    }
}
