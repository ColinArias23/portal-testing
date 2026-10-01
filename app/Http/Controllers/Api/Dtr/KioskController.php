<?php

namespace App\Http\Controllers\Api\Dtr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dtr\PunchRequest;
use App\Http\Requests\Dtr\RecognizeRequest;
use App\Models\Dtr\DtrFace;
use App\Models\Employee;
use App\Services\Dtr\AttendanceService;
use App\Services\Dtr\FaceEngineClient;
use App\Services\Dtr\PunchTicketService;
use Illuminate\Http\JsonResponse;

class KioskController extends Controller
{
    public function __construct(
        private readonly FaceEngineClient $engine,
        private readonly PunchTicketService $tickets,
        private readonly AttendanceService $attendance,
    ) {
    }

    public function recognize(RecognizeRequest $request): JsonResponse
    {
        $known = DtrFace::query()->get(['employee_id', 'encodings'])
            ->map(fn (DtrFace $f) => ['name' => (string) $f->employee_id, 'encodings' => $f->encodings])
            ->all();

        $result = $this->engine->recognize($request->validated('images'), $known);

        if (! $result['match']) {
            return response()->json(['match' => false]);
        }

        $employee = Employee::query()->find((int) $result['name']);
        if (! $employee) {
            return response()->json(['match' => false]);
        }

        return response()->json([
            'match'      => true,
            'name'       => $employee->full_name,      
            'confidence' => $result['confidence'],
            'ticket'     => $this->tickets->issue($employee->id, PunchTicketService::KIOSK),
        ]);
    }

    public function punch(PunchRequest $request): JsonResponse
    {
        $employeeId = $this->tickets->consume($request->validated('ticket'), PunchTicketService::KIOSK);

        return response()->json(
            $this->attendance->punch($employeeId, $request->validated('image'), 'kiosk')
        );
    }
}
