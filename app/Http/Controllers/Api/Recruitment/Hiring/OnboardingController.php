<?php

namespace App\Http\Controllers\Api\Recruitment\Hiring;

use App\Events\Recruitment\Hiring\OnboardingUpdated;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Onboarding;
use App\Models\OnboardingTask;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnboardingController extends Controller
{
    private const DEFAULT_TASKS = [
        'Submit government IDs',
        'Sign employment contract',
        'Complete HR orientation',
        'Set up company email',
        'Meet the team',
        'Complete mandatory trainings',
    ];

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:hiring.nonplantilla.onboarding.view')->only(['index', 'show', 'summary']);
        $this->middleware('permission:hiring.nonplantilla.onboarding.manage')->only(['store', 'update', 'destroy', 'toggleTask', 'addTask', 'updateTask', 'deleteTask']);
    }
// ─── Format ─────────────────────────────────────────────────────
    private function format(Onboarding $onboarding): array
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
            'tasks'        => $tasks->map(fn($t) => [
                'id'           => $t->id,
                'title'        => $t->title,
                'completed'    => $t->completed,
                'completed_at' => $t->completed_at?->setTimezone('Asia/Manila')->toDateTimeString(),
                'sort_order'   => $t->sort_order,
            ])->values(),
            'employee'     => $onboarding->employee?->only(['id', 'full_name']),
            'applicant'    => $onboarding->applicant?->only(['id', 'full_name']),
            'created_at'   => $onboarding->created_at,
        ];
    }
// ─── Broadcast ─────────────────────────────────────────────────────
    private function broadcast(Onboarding $onboarding): void
    {
        broadcast(new OnboardingUpdated($this->format($onboarding)))->toOthers();
    }
// ─── Summary ─────────────────────────────────────────────────────
    public function summary()
    {
        return response()->json([
            'new_hires'     => Onboarding::count(),
            'in_progress'   => Onboarding::where('status', 'In Progress')->count(),
            'completed'     => Onboarding::where('status', 'Completed')->count(),
            'pending_tasks' => OnboardingTask::where('completed', false)->count(),
        ]);
    }
// ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = Onboarding::with(['tasks', 'employee', 'applicant'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name',       'like', "%{$term}%")
                  ->orWhere('position',   'like', "%{$term}%")
                  ->orWhere('department', 'like', "%{$term}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $paginated = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'data'       => $paginated->getCollection()->map(fn($o) => $this->format($o)),
            'pagination' => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }
// ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'position'     => ['nullable', 'string', 'max:100'],
            'department'   => ['nullable', 'string', 'max:100'],
            'start_date'   => ['nullable', 'date'],
            'employee_id'  => ['nullable', 'exists:employees,id'],
            'applicant_id' => ['nullable', 'exists:applicants,id'],
            'tasks'        => ['nullable', 'array'],
            'tasks.*'      => ['string'],
        ]);

        $onboarding = DB::transaction(function () use ($data) {
            $onboarding = Onboarding::create([
                'name'         => $data['name'],
                'position'     => $data['position']     ?? null,
                'department'   => $data['department']   ?? null,
                'start_date'   => $data['start_date']   ?? null,
                'employee_id'  => $data['employee_id']  ?? null,
                'applicant_id' => $data['applicant_id'] ?? null,
                'status'       => 'Pending',
            ]);

            $taskTitles = !empty($data['tasks']) ? $data['tasks'] : self::DEFAULT_TASKS;

            foreach ($taskTitles as $index => $title) {
                OnboardingTask::create([
                    'onboarding_id' => $onboarding->id,
                    'title'         => $title,
                    'completed'     => false,
                    'completed_at'  => null,
                    'sort_order'    => $index,
                ]);
            }

            return $onboarding->fresh()->load(['tasks', 'employee', 'applicant']);
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

        broadcast(new OnboardingUpdated($this->format($onboarding)))->toOthers();

        return response()->json($this->format($onboarding), 201);
    }
// ─── Show ─────────────────────────────────────────────────────
    public function show(Onboarding $onboarding)
    {
        $onboarding->load(['tasks', 'employee', 'applicant']);
        return response()->json($this->format($onboarding));
    }
// ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, Onboarding $onboarding)
    {
        $data = $request->validate([
            'name'       => ['sometimes', 'string', 'max:150'],
            'position'   => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable'],
            'status'     => ['nullable', 'in:Pending,In Progress,Completed'],
        ]);

        $before = [
            'status'     => $onboarding->status,
            'start_date' => $onboarding->start_date?->toDateTimeString(),
        ];

        $onboarding->update($data);

        if (!isset($data['status'])) {
            $computedStatus = $onboarding->fresh()->load('tasks')->computeStatus();
            $onboarding->update(['status' => $computedStatus]);
        }

        $onboarding->load(['tasks', 'employee', 'applicant']);

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

        $this->broadcast($onboarding);

        return response()->json($this->format($onboarding));
    }
// ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Request $request, Onboarding $onboarding)
    {
        $id        = $onboarding->id;
        $label     = $onboarding->name;
        $taskCount = $onboarding->tasks()->count();
        $doneCount = $onboarding->tasks()->where('completed', true)->count();

        $onboarding->delete();

        ActivityLog::record(
            action:       'onboarding.deleted',
            userId:       $request->user()?->id,
            subjectType:  Onboarding::class,
            subjectId:    $id,
            subjectLabel: $label,
            payload:      [
                'tasks_total'     => $taskCount,
                'tasks_completed' => $doneCount,
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        broadcast(new \App\Events\OnboardingDeleted($id))->toOthers();

        return response()->json(['message' => 'Onboarding record deleted.']);
    }
// ─── ToggleTask ─────────────────────────────────────────────────────
    public function toggleTask(Request $request, Onboarding $onboarding, OnboardingTask $task)
    {
        $wasCompleted = $task->completed;
        $nowCompleted = !$wasCompleted;
        $now          = Carbon::now('Asia/Manila');

        $task->update([
            'completed'    => $nowCompleted,
            'completed_at' => $nowCompleted ? $now : null,
        ]);

        $freshOnboarding = $onboarding->fresh()->load('tasks');
        $newStatus       = $freshOnboarding->computeStatus();
        $allDone         = $newStatus === 'Completed';

        $updatePayload = ['status' => $newStatus];

        if ($allDone) {
            $updatePayload['start_date'] = $now;
        } elseif ($wasCompleted && $onboarding->status === 'Completed') {
            $updatePayload['start_date'] = null;
        }

        $onboarding->update($updatePayload);

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

        $onboarding->load(['tasks', 'employee', 'applicant']);

        $this->broadcast($onboarding);

        return response()->json($this->format($onboarding));
    }
// ─── AddTask ─────────────────────────────────────────────────────
    public function addTask(Request $request, Onboarding $onboarding)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
        ]);

        $lastOrder = $onboarding->tasks()->max('sort_order') ?? -1;

        $task = OnboardingTask::create([
            'onboarding_id' => $onboarding->id,
            'title'         => $data['title'],
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

        $onboarding->load(['tasks', 'employee', 'applicant']);

        $this->broadcast($onboarding);

        return response()->json($this->format($onboarding));
    }
// ─── UpdateTask ─────────────────────────────────────────────────────
    public function updateTask(Request $request, Onboarding $onboarding, OnboardingTask $task)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
        ]);

        $oldTitle = $task->title;
        $task->update(['title' => $data['title']]);

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

        $onboarding->load(['tasks', 'employee', 'applicant']);

        $this->broadcast($onboarding);

        return response()->json($this->format($onboarding));
    }
// ─── DeleteTask ─────────────────────────────────────────────────────
    public function deleteTask(Request $request, Onboarding $onboarding, OnboardingTask $task)
    {
        $taskId    = $task->id;
        $taskTitle = $task->title;

        $task->delete();

        $newStatus = $onboarding->fresh()->load('tasks')->computeStatus();
        $onboarding->update(['status' => $newStatus]);

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

        $onboarding->load(['tasks', 'employee', 'applicant']);

        $this->broadcast($onboarding);

        return response()->json($this->format($onboarding));
    }
}