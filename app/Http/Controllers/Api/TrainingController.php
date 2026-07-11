<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrainingRequest;
use App\Http\Requests\UpdateTrainingRequest;
use App\Models\ActivityLog;
use App\Models\Training;
use App\Models\Employee;
use App\Events\TrainingCreated;
use App\Events\TrainingDeleted;
use App\Events\TrainingParticipantUpdated;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrainingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:trainings.view')->only(['index', 'show', 'summary']);
        $this->middleware('permission:trainings.manage')->only([ 'store', 'update', 'destroy', 'assignParticipants', 'updateParticipant', 'removeParticipant', 'markAttendance']);
    }

// ─── FormatTraining ───────────────────────────────────────────────────
    private function formatTraining(Training $training, ?int $currentUserId = null): array
    {
        $resolvedStatus = $training->resolveStatus();

        $currentEmployeeId = null;
        if ($currentUserId) {
            $currentEmployeeId = DB::table('users')
                ->where('id', $currentUserId)
                ->value('employee_id');
        }

        return [
            'id'              => $training->id,
            'title'           => $training->title,
            'description'     => $training->description,
            'department'      => $training->department,
            'instructor'      => $training->instructor,
            'category'        => $training->category,
            'eventAddress'    => $training->event_address,
            'trainingMode'    => $training->training_mode,
            'startDate'       => $training->start_date?->toISOString(),
            'endDate'         => $training->end_date?->toISOString(),
            'duration'        => $training->duration,
            'status'          => $resolvedStatus,
            'progress'        => $training->progress,
            'maxParticipants' => $training->max_participants ?? 0,
            'isEnrolled'      => $currentEmployeeId
                ? $training->participants->contains('id', $currentEmployeeId)
                : false,
            'participants'    => $training->participants->map(fn($e) => [
                'id'                => $e->id,
                'name'              => trim(collect([
                                          $e->prefix,
                                          $e->first_name,
                                          $e->middle_name ? $e->middle_name[0] . '.' : null,
                                          $e->last_name,
                                          $e->suffix ? ', ' . $e->suffix : null,
                                      ])->filter()->implode(' ')),
                'employeeNumber'    => $e->employee_number,
                'department'        => $e->department,
                'avatar_url'        => $e->getRawOriginal('avatar_url')
                                        ? rtrim(config('app.url'), '/') . '/storage/' . $e->getRawOriginal('avatar_url')
                                        : null,
                'status'            => $e->pivot->status,
                'certificateIssued' => $e->pivot->certificate_issued,
                'attendedAt'        => $e->pivot->attended_at?->format('Y-m-d\TH:i:s'),
            ])->values()->toArray(),
            'createdAt' => $training->created_at?->toISOString(),
            'updatedAt' => $training->updated_at?->toISOString(),
        ];
    }
// ─── Index ───────────────────────────────────────────────────
    public function index(Request $request)
    {
        try {
            $query = Training::with('participants')
                ->search($request->get('search'))
                ->byStatus($request->get('status'))
                ->byDepartment($request->get('department'))
                ->orderBy('created_at', 'desc');

            $userId = $request->user()?->id;

            if ($request->boolean('all')) {
                $trainings = $query->get()->map(fn($t) => $this->formatTraining($t, $userId));
                return response()->json(['data' => $trainings]);
            }

            $paginated = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'data'       => collect($paginated->items())->map(fn($t) => $this->formatTraining($t, $userId)),
                'pagination' => [
                    'total'        => $paginated->total(),
                    'per_page'     => $paginated->perPage(),
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Training@index: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
// ─── Store ───────────────────────────────────────────────────
    public function store(StoreTrainingRequest $request)
    {
        try {
            $training = Training::create([
                'title'            => $request->title,
                'description'      => $request->description,
                'department'       => $request->department,
                'instructor'       => $request->instructor,
                'category'         => $request->category,
                'event_address'    => $request->event_address,
                'training_mode'    => $request->training_mode,
                'start_date'       => $request->start_date,
                'end_date'         => $request->end_date,
                'duration'         => $request->duration,
                'status'           => $request->status ?? 'active',
                'progress'         => $request->progress ?? 0,
                'max_participants' => $request->max_participants ?? 0,
            ]);

            $training->load('participants');

            $posterId  = $request->user()->id;
            $formatted = $this->formatTraining($training, $posterId);

            NotificationService::trainingCreated($training, $posterId);
            broadcast(new TrainingCreated($training, $posterId, $formatted));

            // ── Audit log ─────────────────────────────────────────────────
            ActivityLog::record(
                action:       'training.created',
                userId:       $posterId,
                subjectType:  Training::class,
                subjectId:    $training->id,
                subjectLabel: $training->title,
                payload:      [
                    'department'   => $training->department,
                    'category'     => $training->category,
                    'training_mode'=> $training->training_mode,
                    'start_date'   => $training->start_date?->toISOString(),
                    'end_date'     => $training->end_date?->toISOString(),
                ],
                ip:        $request->ip(),
                userAgent: $request->userAgent(),
            );

            return response()->json([
                'message' => 'Training created successfully.',
                'data'    => $formatted,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Training@store: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
// ─── Show ───────────────────────────────────────────────────
    public function show(Request $request, Training $training)
    {
        try {
            $training->load('participants');
            return response()->json(['data' => $this->formatTraining($training, $request->user()?->id)]);
        } catch (\Throwable $e) {
            Log::error('Training@show: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
// ─── Update ───────────────────────────────────────────────────
    public function update(UpdateTrainingRequest $request, Training $training)
    {
        try {
            // Snapshot before update
            $before = [
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

            $training->update([
                'title'            => $request->title            ?? $training->title,
                'description'      => $request->description      ?? $training->description,
                'department'       => $request->department        ?? $training->department,
                'instructor'       => $request->instructor        ?? $training->instructor,
                'category'         => $request->category          ?? $training->category,
                'event_address'    => $request->event_address     ?? $training->event_address,
                'training_mode'    => $request->training_mode     ?? $training->training_mode,
                'start_date'       => $request->start_date        ?? $training->start_date,
                'end_date'         => $request->end_date          ?? $training->end_date,
                'duration'         => $request->duration          ?? $training->duration,
                'status'           => $request->status            ?? $training->status,
                'progress'         => $request->progress          ?? $training->progress,
                'max_participants' => $request->has('max_participants')
                                        ? (int) $request->max_participants
                                        : $training->max_participants,
            ]);

            $training->load('participants');
            $formatted = $this->formatTraining($training, $request->user()?->id);

            broadcast(new TrainingParticipantUpdated($formatted));

            // ── Audit log ─────────────────────────────────────────────────
            ActivityLog::record(
                action:       'training.updated',
                userId:       $request->user()?->id,
                subjectType:  Training::class,
                subjectId:    $training->id,
                subjectLabel: $training->title,
                payload:      [
                    'before' => $before,
                    'after'  => [
                        'title'         => $training->title,
                        'department'    => $training->department,
                        'category'      => $training->category,
                        'instructor'    => $training->instructor,
                        'training_mode' => $training->training_mode,
                        'start_date'    => $training->start_date?->toISOString(),
                        'end_date'      => $training->end_date?->toISOString(),
                        'status'        => $training->status,
                        'progress'      => $training->progress,
                    ],
                ],
                ip:        $request->ip(),
                userAgent: $request->userAgent(),
            );

            return response()->json([
                'message' => 'Training updated successfully.',
                'data'    => $formatted,
            ]);
        } catch (\Throwable $e) {
            Log::error('Training@update: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
// ─── Destroy ───────────────────────────────────────────────────
    public function destroy(Request $request, Training $training)
    {
        try {
            $trainingId    = $training->id;
            $trainingTitle = $training->title;

            $training->delete();

            broadcast(new TrainingDeleted($trainingId));

            // ── Audit log ─────────────────────────────────────────────────
            ActivityLog::record(
                action:       'training.deleted',
                userId:       $request->user()?->id,
                subjectType:  Training::class,
                subjectId:    $trainingId,
                subjectLabel: $trainingTitle,
                ip:           $request->ip(),
                userAgent:    $request->userAgent(),
            );

            return response()->json(['message' => 'Training deleted successfully.']);
        } catch (\Throwable $e) {
            Log::error('Training@destroy: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
// ─── AssignParticipants ───────────────────────────────────────────────────
    public function assignParticipants(Request $request, Training $training)
    {
        if ($request->has('employee_ids')) {
            $ids = $request->employee_ids;
            if (!is_null($ids) && !is_array($ids)) {
                return response()->json(['message' => 'employee_ids must be an array.'], 422);
            }
        }

        try {
            $employeeNames = [];

            DB::transaction(function () use ($request, $training, &$employeeNames) {
                $employeeIds = $request->employee_ids ?? [];

                if ($training->max_participants > 0 && count($employeeIds) > $training->max_participants) {
                    throw new \Exception(
                        "Cannot assign more than {$training->max_participants} participants."
                    );
                }

                // Collect names for the audit log
                $employeeNames = Employee::whereIn('id', $employeeIds)
                    ->get()
                    ->map(fn($e) => ['id' => $e->id, 'name' => $e->full_name])
                    ->toArray();

                $sync = collect($employeeIds)
                    ->mapWithKeys(fn($id) => [
                        (int) $id => ['status' => 'enrolled', 'certificate_issued' => false],
                    ])
                    ->toArray();

                $training->participants()->sync($sync);
            });

            $training->load('participants');
            $formatted = $this->formatTraining($training, $request->user()?->id);
            broadcast(new TrainingParticipantUpdated($formatted));

            // ── Audit log ─────────────────────────────────────────────────
            ActivityLog::record(
                action:       'training.participants_assigned',
                userId:       $request->user()?->id,
                subjectType:  Training::class,
                subjectId:    $training->id,
                subjectLabel: $training->title,
                payload:      [
                    'assigned_count' => count($employeeNames),
                    'participants'   => $employeeNames,
                ],
                ip:        $request->ip(),
                userAgent: $request->userAgent(),
            );

            return response()->json([
                'message' => 'Participants updated successfully.',
                'data'    => $formatted,
            ]);
        } catch (\Throwable $e) {
            Log::error('Training@assignParticipants: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
// ─── UpdateParticipant ───────────────────────────────────────────────────
    public function updateParticipant(Request $request, Training $training, Employee $employee)
    {
        $request->validate([
            'status'             => 'nullable|in:enrolled,completed,dropped',
            'certificate_issued' => 'nullable|boolean',
        ]);

        try {
            $training->participants()->updateExistingPivot($employee->id, array_filter([
                'status'             => $request->status,
                'certificate_issued' => $request->certificate_issued,
            ], fn($v) => !is_null($v)));

            $training->load('participants');
            $formatted = $this->formatTraining($training, $request->user()?->id);
            broadcast(new TrainingParticipantUpdated($formatted));

            // ── Audit log ─────────────────────────────────────────────────
            ActivityLog::record(
                action:       'training.participant_updated',
                userId:       $request->user()?->id,
                subjectType:  Training::class,
                subjectId:    $training->id,
                subjectLabel: $training->title,
                payload:      [
                    'employee_id'        => $employee->id,
                    'employee_name'      => $employee->full_name,
                    'status'             => $request->status,
                    'certificate_issued' => $request->certificate_issued,
                ],
                ip:        $request->ip(),
                userAgent: $request->userAgent(),
            );

            return response()->json([
                'message' => 'Participant updated successfully.',
                'data'    => $formatted,
            ]);
        } catch (\Throwable $e) {
            Log::error('Training@updateParticipant: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
// ─── RemoveParticipant ───────────────────────────────────────────────────
    public function removeParticipant(Request $request, Training $training, Employee $employee)
    {
        try {
            $employeeId   = $employee->id;
            $employeeName = $employee->full_name;

            $training->participants()->detach($employeeId);
            $training->load('participants');
            $formatted = $this->formatTraining($training, $request->user()?->id);
            broadcast(new TrainingParticipantUpdated($formatted));

            // ── Audit log ─────────────────────────────────────────────────
            ActivityLog::record(
                action:       'training.participant_removed',
                userId:       $request->user()?->id,
                subjectType:  Training::class,
                subjectId:    $training->id,
                subjectLabel: $training->title,
                payload:      [
                    'employee_id'   => $employeeId,
                    'employee_name' => $employeeName,
                ],
                ip:        $request->ip(),
                userAgent: $request->userAgent(),
            );

            return response()->json([
                'message' => 'Participant removed.',
                'data'    => $formatted,
            ]);
        } catch (\Throwable $e) {
            Log::error('Training@removeParticipant: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
// ─── MarkAttendance ───────────────────────────────────────────────────
    public function markAttendance(Request $request, Training $training, Employee $employee)
    {
        try {
            $alreadyMarked = $training->participants()
                ->where('employee_id', $employee->id)
                ->whereNotNull('training_participants.attended_at')
                ->exists();

            if ($alreadyMarked) {
                return response()->json(['message' => 'Attendance already recorded.'], 422);
            }

            $training->participants()->updateExistingPivot($employee->id, [
                'attended_at' => now(),
            ]);

            $training->load('participants');
            $formatted = $this->formatTraining($training, $request->user()?->id);
            broadcast(new TrainingParticipantUpdated($formatted));

            // ── Audit log ─────────────────────────────────────────────────
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

            return response()->json([
                'message' => 'Attendance marked successfully.',
                'data'    => $formatted,
            ]);
        } catch (\Throwable $e) {
            Log::error('Training@markAttendance: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
// ─── Summary ───────────────────────────────────────────────────
    public function summary()
    {
        try {
            $trainings = Training::with('participants')->get();

            $totalEnrolled      = $trainings->sum(fn($t) => $t->participants->count());
            $certificatesIssued = DB::table('training_participants')
                                    ->where('certificate_issued', true)
                                    ->count();
            $completed          = $trainings->filter(fn($t) => $t->progress === 100)->count();

            return response()->json([
                'activePrograms'     => $trainings->count(),
                'totalEnrolled'      => $totalEnrolled,
                'certificatesIssued' => $certificatesIssued,
                'completed'          => $completed,
            ]);
        } catch (\Throwable $e) {
            Log::error('Training@summary: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
// ─── Join ───────────────────────────────────────────────────
    public function join(Request $request, Training $training)
    {
        $employee = $request->user()->employee;

        if (!$employee) {
            return response()->json(['message' => 'No employee record linked to your account.'], 422);
        }

        $alreadyEnrolled = $training->participants()
            ->where('employee_id', $employee->id)
            ->exists();

        if ($alreadyEnrolled) {
            return response()->json(['message' => 'You are already enrolled in this training.'], 422);
        }

        if ($training->max_participants > 0) {
            $currentCount = $training->participants()->count();
            if ($currentCount >= $training->max_participants) {
                return response()->json([
                    'message' => 'This training is already full. No more slots available.',
                ], 422);
            }
        }

        $training->participants()->attach($employee->id, [
            'status'             => 'enrolled',
            'certificate_issued' => false,
        ]);

        $training->load('participants');
        $formatted = $this->formatTraining($training, $request->user()->id);
        broadcast(new TrainingParticipantUpdated($formatted));

        // ── Audit log ─────────────────────────────────────────────────────
        ActivityLog::record(
            action:       'training.joined',
            userId:       $request->user()->id,
            subjectType:  Training::class,
            subjectId:    $training->id,
            subjectLabel: $training->title,
            payload:      [
                'employee_id'   => $employee->id,
                'employee_name' => $employee->full_name,
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'message' => 'Successfully joined the training.',
            'data'    => $formatted,
        ]);
    }
}