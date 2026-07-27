<?php

namespace App\Http\Controllers\Api\Training;

use App\Http\Controllers\Controller;
use App\Http\Requests\Training\StoreTrainingRequest;
use App\Http\Requests\Training\UpdateTrainingRequest;
use App\Models\Employee;
use App\Models\Training;
use App\Services\Training\TrainingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TrainingController extends Controller
{
    public function __construct(
        private readonly TrainingService $trainings,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:trainings.view')->only(['index', 'show', 'summary']);
        $this->middleware('permission:trainings.manage')->only(['store', 'update', 'destroy', 'assignParticipants', 'updateParticipant', 'removeParticipant', 'markAttendance']);
    }

    // ─── Index ───────────────────────────────────────────────────
    public function index(Request $request)
    {
        try {
            return response()->json($this->trainings->index($request));
        } catch (\Throwable $e) {
            Log::error('Training@index: ' . $e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── Store ───────────────────────────────────────────────────
    public function store(StoreTrainingRequest $request)
    {
        try {
            $data = [
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
            ];

            return response()->json([
                'message' => 'Training created successfully.',
                'data'    => $this->trainings->store($request, $data),
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
            return response()->json(['data' => $this->trainings->show($training, $request->user()?->id)]);
        } catch (\Throwable $e) {
            Log::error('Training@show: ' . $e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── Update ───────────────────────────────────────────────────
    public function update(UpdateTrainingRequest $request, Training $training)
    {
        try {
            $data = [
                'title'            => $request->title            ?? $training->title,
                'description'      => $request->description      ?? $training->description,
                'department'       => $request->department       ?? $training->department,
                'instructor'       => $request->instructor       ?? $training->instructor,
                'category'         => $request->category         ?? $training->category,
                'event_address'    => $request->event_address    ?? $training->event_address,
                'training_mode'    => $request->training_mode    ?? $training->training_mode,
                'start_date'       => $request->start_date       ?? $training->start_date,
                'end_date'         => $request->end_date         ?? $training->end_date,
                'duration'         => $request->duration         ?? $training->duration,
                'status'           => $request->status           ?? $training->status,
                'progress'         => $request->progress         ?? $training->progress,
                'max_participants' => $request->has('max_participants')
                    ? (int) $request->max_participants
                    : $training->max_participants,
            ];

            return response()->json([
                'message' => 'Training updated successfully.',
                'data'    => $this->trainings->update($request, $training, $data),
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
            $this->trainings->destroy($request, $training);

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
            if (! is_null($ids) && ! is_array($ids)) {
                return response()->json(['message' => 'employee_ids must be an array.'], 422);
            }
        }

        try {
            return response()->json([
                'message' => 'Participants updated successfully.',
                'data'    => $this->trainings->assignParticipants($request, $training, $request->employee_ids ?? []),
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
            return response()->json([
                'message' => 'Participant updated successfully.',
                'data'    => $this->trainings->updateParticipant($request, $training, $employee, [
                    'status'             => $request->status,
                    'certificate_issued' => $request->certificate_issued,
                ]),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Training@updateParticipant: ' . $e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── RemoveParticipant ───────────────────────────────────────────────────
    public function removeParticipant(Request $request, Training $training, Employee $employee)
    {
        try {
            return response()->json([
                'message' => 'Participant removed.',
                'data'    => $this->trainings->removeParticipant($request, $training, $employee),
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
            $result = $this->trainings->markAttendance($request, $training, $employee);

            return response()->json($result['body'], $result['status']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Training@markAttendance: ' . $e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── Summary ───────────────────────────────────────────────────
    public function summary()
    {
        try {
            return response()->json($this->trainings->summary());
        } catch (\Throwable $e) {
            Log::error('Training@summary: ' . $e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── Join ───────────────────────────────────────────────────
    public function join(Request $request, Training $training)
    {
        $result = $this->trainings->join($request, $training);

        return response()->json($result['body'], $result['status']);
    }
}
