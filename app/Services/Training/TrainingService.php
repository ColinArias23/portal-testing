<?php

namespace App\Services\Training;

use App\Events\Training\TrainingCreated;
use App\Events\Training\TrainingDeleted;
use App\Events\Training\TrainingParticipantUpdated;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\Training;
use App\Repositories\Training\TrainingRepository;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrainingService
{
    public function __construct(
        private readonly TrainingRepository $trainings,
        private readonly TrainingFormatterService $formatter,
    ) {
    }

    public function index(Request $request): array
    {
        $query = $this->trainings->newQuery(
            $request->get('search'),
            $request->get('status'),
            $request->get('department')
        );

        $employeeId = $this->trainings->employeeIdForUser($request->user()?->id);

        if ($request->boolean('all')) {
            $trainings = $query->get()->map(fn ($t) => $this->formatter->format($t, $employeeId));

            return ['data' => $trainings];
        }

        $paginated = $this->trainings->paginate($query, (int) $request->get('per_page', 15));

        return [
            'data'       => collect($paginated->items())->map(fn ($t) => $this->formatter->format($t, $employeeId)),
            'pagination' => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
        ];
    }

    public function store(Request $request, array $data): array
    {
        $training = $this->trainings->create($data);

        $posterId  = $request->user()->id;
        $employeeId = $this->trainings->employeeIdForUser($posterId);
        $formatted = $this->formatter->format($training, $employeeId);

        NotificationService::trainingCreated($training, $posterId);
        broadcast(new TrainingCreated($training, $posterId, $formatted));

        ActivityLog::record(
            action:       'training.created',
            userId:       $posterId,
            subjectType:  Training::class,
            subjectId:    $training->id,
            subjectLabel: $training->title,
            payload:      [
                'department'    => $training->department,
                'category'      => $training->category,
                'training_mode' => $training->training_mode,
                'start_date'    => $training->start_date?->toISOString(),
                'end_date'      => $training->end_date?->toISOString(),
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        return $formatted;
    }

    public function show(Training $training, ?int $userId): array
    {
        $this->trainings->loadParticipants($training);

        return $this->formatter->format($training, $this->trainings->employeeIdForUser($userId));
    }

    public function update(Request $request, Training $training, array $data): array
    {
        $before = $this->snapshot($training);

        $this->trainings->update($training, $data);

        $employeeId = $this->trainings->employeeIdForUser($request->user()?->id);
        $formatted  = $this->formatter->format($training, $employeeId);

        broadcast(new TrainingParticipantUpdated($formatted));

        ActivityLog::record(
            action:       'training.updated',
            userId:       $request->user()?->id,
            subjectType:  Training::class,
            subjectId:    $training->id,
            subjectLabel: $training->title,
            payload:      ['before' => $before, 'after' => $this->snapshot($training)],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        return $formatted;
    }

    private function snapshot(Training $training): array
    {
        return [
            'title'         => $training->title,
            'department'    => $training->department,
            'category'      => $training->category,
            'instructor'    => $training->instructor,
            'training_mode' => $training->training_mode,
            'start_date'    => $training->start_date?->toISOString(),
            'end_date'      => $training->end_date?->toISOString(),
            'status'        => $training->status,
            'progress'      => $training->progress,
        ];
    }

    public function destroy(Request $request, Training $training): void
    {
        $id    = $training->id;
        $title = $training->title;

        $this->trainings->delete($training);

        broadcast(new TrainingDeleted($id));

        ActivityLog::record(
            action:       'training.deleted',
            userId:       $request->user()?->id,
            subjectType:  Training::class,
            subjectId:    $id,
            subjectLabel: $title,
            ip:           $request->ip(),
            userAgent:    $request->userAgent(),
        );
    }

    public function assignParticipants(Request $request, Training $training, array $employeeIds): array
    {
        $employeeNames = [];

        DB::transaction(function () use ($training, $employeeIds, &$employeeNames) {
            if ($training->max_participants > 0 && count($employeeIds) > $training->max_participants) {
                throw new \Exception("Cannot assign more than {$training->max_participants} participants.");
            }

            $employeeNames = $this->trainings->employeesByIds($employeeIds)
                ->map(fn ($e) => ['id' => $e->id, 'name' => $e->full_name])
                ->toArray();

            $sync = collect($employeeIds)
                ->mapWithKeys(fn ($id) => [(int) $id => ['status' => 'enrolled', 'certificate_issued' => false]])
                ->toArray();

            $this->trainings->syncParticipants($training, $sync);
        });

        $this->trainings->loadParticipants($training);
        $employeeId = $this->trainings->employeeIdForUser($request->user()?->id);
        $formatted  = $this->formatter->format($training, $employeeId);

        broadcast(new TrainingParticipantUpdated($formatted));

        ActivityLog::record(
            action:       'training.participants_assigned',
            userId:       $request->user()?->id,
            subjectType:  Training::class,
            subjectId:    $training->id,
            subjectLabel: $training->title,
            payload:      ['assigned_count' => count($employeeNames), 'participants' => $employeeNames],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        return $formatted;
    }

    public function updateParticipant(Request $request, Training $training, Employee $employee, array $validated): array
    {
        if (! $this->trainings->isParticipant($training, $employee->id)) {
            throw ValidationException::withMessages([
                'employee' => 'This employee is not a participant of this training.',
            ]);
        }

        $this->trainings->updateParticipantPivot($training, $employee->id, array_filter([
            'status'             => $validated['status'] ?? null,
            'certificate_issued' => $validated['certificate_issued'] ?? null,
        ], fn ($v) => ! is_null($v)));

        $this->trainings->loadParticipants($training);
        $employeeId = $this->trainings->employeeIdForUser($request->user()?->id);
        $formatted  = $this->formatter->format($training, $employeeId);

        broadcast(new TrainingParticipantUpdated($formatted));

        ActivityLog::record(
            action:       'training.participant_updated',
            userId:       $request->user()?->id,
            subjectType:  Training::class,
            subjectId:    $training->id,
            subjectLabel: $training->title,
            payload:      [
                'employee_id'        => $employee->id,
                'employee_name'      => $employee->full_name,
                'status'             => $validated['status'] ?? null,
                'certificate_issued' => $validated['certificate_issued'] ?? null,
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        return $formatted;
    }

    public function removeParticipant(Request $request, Training $training, Employee $employee): array
    {
        $this->trainings->detachParticipant($training, $employee->id);
        $this->trainings->loadParticipants($training);

        $employeeId = $this->trainings->employeeIdForUser($request->user()?->id);
        $formatted  = $this->formatter->format($training, $employeeId);

        broadcast(new TrainingParticipantUpdated($formatted));

        ActivityLog::record(
            action:       'training.participant_removed',
            userId:       $request->user()?->id,
            subjectType:  Training::class,
            subjectId:    $training->id,
            subjectLabel: $training->title,
            payload:      ['employee_id' => $employee->id, 'employee_name' => $employee->full_name],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        return $formatted;
    }

    public function markAttendance(Request $request, Training $training, Employee $employee): array
    {
        if (! $this->trainings->isParticipant($training, $employee->id)) {
            throw ValidationException::withMessages([
                'employee' => 'This employee is not a participant of this training.',
            ]);
        }

        if ($this->trainings->hasAttended($training, $employee->id)) {
            return ['status' => 422, 'body' => ['message' => 'Attendance already recorded.']];
        }

        $this->trainings->updateParticipantPivot($training, $employee->id, ['attended_at' => now()]);
        $this->trainings->loadParticipants($training);

        $employeeId = $this->trainings->employeeIdForUser($request->user()?->id);
        $formatted  = $this->formatter->format($training, $employeeId);

        broadcast(new TrainingParticipantUpdated($formatted));

        ActivityLog::record(
            action:       'training.attendance_marked',
            userId:       $request->user()?->id,
            subjectType:  Training::class,
            subjectId:    $training->id,
            subjectLabel: $training->title,
            payload:      [
                'employee_id'   => $employee->id,
                'employee_name' => $employee->full_name,
                'attended_at'   => now()->toISOString(),
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        return ['status' => 200, 'body' => ['message' => 'Attendance marked successfully.', 'data' => $formatted]];
    }

    public function summary(): array
    {
        $trainings = $this->trainings->allWithParticipants();

        return [
            'activePrograms'     => $trainings->count(),
            'totalEnrolled'      => $trainings->sum(fn ($t) => $t->participants->count()),
            'certificatesIssued' => $this->trainings->certificatesIssuedCount(),
            'completed'          => $trainings->filter(fn ($t) => $t->progress === 100)->count(),
        ];
    }

    public function join(Request $request, Training $training): array
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return ['status' => 422, 'body' => ['message' => 'No employee record linked to your account.']];
        }

        if ($this->trainings->isParticipant($training, $employee->id)) {
            return ['status' => 422, 'body' => ['message' => 'You are already enrolled in this training.']];
        }

        if ($training->max_participants > 0 && $this->trainings->participantCount($training) >= $training->max_participants) {
            return ['status' => 422, 'body' => ['message' => 'This training is already full. No more slots available.']];
        }

        $this->trainings->attachParticipant($training, $employee->id, [
            'status'             => 'enrolled',
            'certificate_issued' => false,
        ]);
        $this->trainings->loadParticipants($training);

        $formatted = $this->formatter->format($training, $employee->id);

        broadcast(new TrainingParticipantUpdated($formatted));

        ActivityLog::record(
            action:       'training.joined',
            userId:       $request->user()->id,
            subjectType:  Training::class,
            subjectId:    $training->id,
            subjectLabel: $training->title,
            payload:      ['employee_id' => $employee->id, 'employee_name' => $employee->full_name],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        return ['status' => 200, 'body' => ['message' => 'Successfully joined the training.', 'data' => $formatted]];
    }
}
