<?php

namespace App\Http\Controllers\Api\Dtr;

use App\Exceptions\Dtr\DtrException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dtr\CalendarRequest;
use App\Http\Requests\Dtr\SaveAttendanceRequest;
use App\Models\DtrAttendance;
use App\Services\Dtr\AttendanceService;
use App\Services\Dtr\DtrCalendarService;
use App\Services\Dtr\EmployeeResolver;
use App\Services\Dtr\ImageStore;
use App\Services\Dtr\ShiftCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDtrController extends Controller
{
    private const EMPLOYEE_COLUMNS = 'employee:id,employee_number,first_name,middle_name,last_name';

    public function __construct(
        private readonly EmployeeResolver $employees,
        private readonly DtrCalendarService $calendar,
        private readonly ImageStore $images,
        private readonly AttendanceService $attendance,
        private readonly ShiftCalculator $calculator,
    ) {
    }

    public function records(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from'     => ['nullable', 'date_format:Y-m-d'],
            'to'       => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'q'        => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:10,500'],
        ]);

        $tz   = (string) config('dtr.timezone');
        $to   = $data['to'] ?? CarbonImmutable::now($tz)->toDateString();
        $from = $data['from'] ?? CarbonImmutable::parse($to, $tz)->subDays(31)->toDateString();

        $query = DtrAttendance::query()
            ->with([self::EMPLOYEE_COLUMNS, 'shift:id,name'])
            ->whereBetween('work_date', [$from, $to])
            ->orderByDesc('work_date')->orderByDesc('time_in');

        if (! empty($data['q'])) {
            $like = '%'.addcslashes($data['q'], '%_\\').'%';
            $query->whereHas('employee', fn ($e) => $e
                ->where('employee_number', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like));
        }

        $page = $query->paginate((int) ($data['per_page'] ?? 200));

        return response()->json($page->through(fn (DtrAttendance $r) => $this->present($r)));
    }

    public function store(SaveAttendanceRequest $request): JsonResponse
    {
        $record = $this->attendance->createManual($request->validated());

        return response()->json(['data' => $this->present($record->load([self::EMPLOYEE_COLUMNS, 'shift:id,name']))], 201);
    }

    public function update(SaveAttendanceRequest $request, DtrAttendance $attendance): JsonResponse
    {
        $record = $this->attendance->updateManual($attendance, $request->validated());

        return response()->json(['data' => $this->present($record->load([self::EMPLOYEE_COLUMNS, 'shift:id,name']))]);
    }

    public function employeeLookup(Request $request): JsonResponse
    {
        $data = $request->validate(['employee_number' => ['required', 'string', 'max:50']]);

        $employee = $this->employees->byNumber($data['employee_number']);

        return response()->json(['employee' => $employee ? [
            'id'              => $employee->id,
            'employee_number' => $employee->employee_number,
            'name'            => $employee->full_name,
        ] : null]);
    }

    public function employeeDtr(CalendarRequest $request, string $employeeNumber): JsonResponse
    {
        $employee = $this->employees->byNumber($employeeNumber);

        if (! $employee) {
            return response()->json([
                'employee' => null, 'left_range' => null, 'right_range' => null,
                'left_entries' => [], 'right_entries' => [],
            ]);
        }

        return response()->json($this->calendar->cutoff($employee, $request->month(), $request->year()));
    }

    public function image(DtrAttendance $attendance, string $type)
    {
        $path = $type === 'in' ? $attendance->time_in_image_path : $attendance->time_out_image_path;

        if (! $this->images->exists($path)) {
            throw new DtrException('Image not found.', 404);
        }

        return $this->images->response($path);
    }

    private function present(DtrAttendance $r): array
    {
        return [
            'id'                 => $r->id,
            'employee_id'        => $r->employee_id,
            'name'               => $r->employee?->full_name,
            'employee_number'    => $r->employee?->employee_number,
            'date'               => $r->work_date->toDateString(),
            'time_in'            => $r->time_in,
            'time_out'           => $r->time_out,
            'time_out_date'      => $r->time_out_date?->toDateString(),
            'shift'              => $r->shift_start ? [
                'id'    => $r->shift_id,
                'name'  => $r->shift?->name,
                'start' => $r->shift_start,
                'end'   => $r->shift_end,
            ] : null,
            ...$this->calculator->minutes($r),
            'has_time_in_image'  => (bool) $r->time_in_image_path,
            'has_time_out_image' => (bool) $r->time_out_image_path,
            'photo_version'      => $r->updated_at?->getTimestamp(),
        ];
    }
}