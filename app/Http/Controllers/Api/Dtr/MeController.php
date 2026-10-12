<?php

namespace App\Http\Controllers\Api\Dtr;

use App\Exceptions\Dtr\DtrException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dtr\CalendarRequest;
use App\Http\Requests\Dtr\PunchRequest;
use App\Http\Requests\Dtr\RecognizeRequest;
use App\Models\DtrFace;
use App\Models\Employee;
use App\Services\Dtr\AttendanceService;
use App\Services\Dtr\DtrCalendarService;
use App\Services\Dtr\EmployeeResolver;
use App\Services\Dtr\FaceEngineClient;
use App\Services\Dtr\PunchTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __construct(
        private readonly EmployeeResolver $employees,
        private readonly FaceEngineClient $engine,
        private readonly PunchTicketService $tickets,
        private readonly AttendanceService $attendance,
        private readonly DtrCalendarService $calendar,
    ) {
    }

    public function status(Request $request): JsonResponse
    {
        $employee = $this->employee($request);

        return response()->json([
            'employee'        => $this->calendar->employeePayload($employee),
            'has_face'        => DtrFace::where('employee_id', $employee->id)->exists(),
        ]);
    }

    public function verify(RecognizeRequest $request): JsonResponse
    {
        $employee = $this->employee($request);
        $face     = DtrFace::where('employee_id', $employee->id)->first();

        if (! $face) {
            throw new DtrException('Your face is not registered yet. Please contact HR.', 422);
        }

        $result = $this->engine->recognize(
            $request->validated('images'),
            [['name' => (string) $employee->id, 'encodings' => $face->encodings]],
        );

        if (! $result['match']) {
            return response()->json(['match' => false]);
        }

        return response()->json([
            'match'      => true,
            'name'       => $employee->full_name,
            'confidence' => $result['confidence'],
            'ticket'     => $this->tickets->issue($employee->id, PunchTicketService::PERSONAL, $request->user()->id),
        ]);
    }

    public function punch(PunchRequest $request): JsonResponse
    {
        $employeeId = $this->tickets->consume(
            $request->validated('ticket'),
            PunchTicketService::PERSONAL,
            $request->user()->id,
        );

        return response()->json(
            $this->attendance->punch($employeeId, $request->validated('image'), 'personal')
        );
    }

    public function dtr(CalendarRequest $request): JsonResponse
    {
        return response()->json(
            $this->calendar->cutoff($this->employee($request), $request->month(), $request->year())
        );
    }

    private function employee(Request $request): Employee
    {
        return $this->employees->forUser($request->user())
            ?? throw new DtrException('Your account is not linked to an employee record.', 422);
    }
}
