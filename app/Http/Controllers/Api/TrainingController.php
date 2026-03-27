<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrainingRequest;
use App\Http\Requests\UpdateTrainingRequest;
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
    /*
    |--------------------------------------------------------------------------
    | FORMAT HELPERS
    |--------------------------------------------------------------------------
    */

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
            ])->values()->toArray(),
            'createdAt'       => $training->created_at?->toISOString(),
            'updatedAt'       => $training->updated_at?->toISOString(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX — GET /api/trainings
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | STORE — POST /api/trainings
    |--------------------------------------------------------------------------
    */

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

            // Single broadcast hits both 'notifications' and 'trainings' channels:
            //   → 'notifications' channel: NotificationBell picks up the bell payload
            //   → 'trainings' channel:     TrainingPage picks up e.training to prepend the row
            broadcast(new TrainingCreated($training, $posterId, $formatted));

            return response()->json([
                'message' => 'Training created successfully.',
                'data'    => $formatted,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Training@store: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW — GET /api/trainings/{id}
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | UPDATE — PUT /api/trainings/{id}
    |--------------------------------------------------------------------------
    */

    public function update(UpdateTrainingRequest $request, Training $training)
    {
        try {
            $training->update([
                'title'            => $request->title          ?? $training->title,
                'description'      => $request->description    ?? $training->description,
                'department'       => $request->department      ?? $training->department,
                'instructor'       => $request->instructor      ?? $training->instructor,
                'category'         => $request->category        ?? $training->category,
                'event_address'    => $request->event_address   ?? $training->event_address,
                'training_mode'    => $request->training_mode   ?? $training->training_mode,
                'start_date'       => $request->start_date      ?? $training->start_date,
                'end_date'         => $request->end_date        ?? $training->end_date,
                'duration'         => $request->duration        ?? $training->duration,
                'status'           => $request->status          ?? $training->status,
                'progress'         => $request->progress        ?? $training->progress,
                'max_participants' => $request->has('max_participants')
                                        ? (int) $request->max_participants
                                        : $training->max_participants,
            ]);

            $training->load('participants');
            $formatted = $this->formatTraining($training, $request->user()?->id);

            // Reuse participant.updated — patches the row on all clients
            broadcast(new TrainingParticipantUpdated($formatted));

            return response()->json([
                'message' => 'Training updated successfully.',
                'data'    => $formatted,
            ]);
        } catch (\Throwable $e) {
            Log::error('Training@update: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY — DELETE /api/trainings/{id}
    |--------------------------------------------------------------------------
    */

    public function destroy(Training $training)
    {
        try {
            $trainingId = $training->id;
            $training->delete();

            // Tell every client to remove this row from their list
            broadcast(new TrainingDeleted($trainingId));

            return response()->json(['message' => 'Training deleted successfully.']);
        } catch (\Throwable $e) {
            Log::error('Training@destroy: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ASSIGN PARTICIPANTS — POST /api/trainings/{id}/participants
    |--------------------------------------------------------------------------
    */

    public function assignParticipants(Request $request, Training $training)
    {
        if ($request->has('employee_ids')) {
            $ids = $request->employee_ids;
            if (!is_null($ids) && !is_array($ids)) {
                return response()->json(['message' => 'employee_ids must be an array.'], 422);
            }
        }

        try {
            DB::transaction(function () use ($request, $training) {
                $employeeIds = $request->employee_ids ?? [];

                if ($training->max_participants > 0 && count($employeeIds) > $training->max_participants) {
                    throw new \Exception(
                        "Cannot assign more than {$training->max_participants} participants."
                    );
                }

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

            return response()->json([
                'message' => 'Participants updated successfully.',
                'data'    => $formatted,
            ]);
        } catch (\Throwable $e) {
            Log::error('Training@assignParticipants: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE PARTICIPANT STATUS — PATCH /api/trainings/{id}/participants/{employeeId}
    |--------------------------------------------------------------------------
    */

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

            return response()->json([
                'message' => 'Participant updated successfully.',
                'data'    => $formatted,
            ]);
        } catch (\Throwable $e) {
            Log::error('Training@updateParticipant: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | REMOVE PARTICIPANT — DELETE /api/trainings/{id}/participants/{employeeId}
    |--------------------------------------------------------------------------
    */

    public function removeParticipant(Request $request, Training $training, Employee $employee)
    {
        try {
            $training->participants()->detach($employee->id);
            $training->load('participants');
            $formatted = $this->formatTraining($training, $request->user()?->id);
            broadcast(new TrainingParticipantUpdated($formatted));

            return response()->json([
                'message' => 'Participant removed.',
                'data'    => $formatted,
            ]);
        } catch (\Throwable $e) {
            Log::error('Training@removeParticipant: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY — GET /api/trainings/summary
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | JOIN — POST /api/trainings/{id}/join
    |--------------------------------------------------------------------------
    */

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

        return response()->json([
            'message' => 'Successfully joined the training.',
            'data'    => $formatted,
        ]);
    }
}