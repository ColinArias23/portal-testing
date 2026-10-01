<?php

namespace App\Http\Controllers\Api\Dtr;

use App\Exceptions\Dtr\DtrException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dtr\CalendarRequest;
use App\Models\Dtr\DtrAttendance;
use App\Services\Dtr\DtrCalendarService;
use App\Services\Dtr\EmployeeResolver;
use App\Services\Dtr\ImageStore;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDtrController extends Controller
{
    public function __construct(
        private readonly EmployeeResolver $employees,
        private readonly DtrCalendarService $calendar,
        private readonly ImageStore $images,
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
            ->with('employee:id,employee_number,first_name,middle_name,last_name')
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

        return response()->json($page->through(fn (DtrAttendance $r) => [
            'id'                 => $r->id,
            'name'               => $r->employee?->full_name,
            'employee_number'    => $r->employee?->employee_number,
            'date'               => $r->work_date->toDateString(),
            'time_in'            => $r->time_in,
            'time_out'           => $r->time_out,
            'time_out_date'      => $r->time_out_date?->toDateString(),
            'has_time_in_image'  => (bool) $r->time_in_image_path,
            'has_time_out_image' => (bool) $r->time_out_image_path,
        ]));
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
}
