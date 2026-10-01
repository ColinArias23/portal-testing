<?php

namespace App\Services\Dtr;

use App\Models\Dtr\DtrAttendance;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public const TIME_IN = 'TIME_IN';
    public const TIME_OUT = 'TIME_OUT';
    public const ALREADY_COMPLETED = 'ALREADY_COMPLETED';
    public const TOO_SOON = 'TOO_SOON';

    public function __construct(private readonly ImageStore $images)
    {
    }

    public function punch(int $employeeId, ?string $imageDataUrl, string $source): array
    {
        $tz  = (string) config('dtr.timezone');
        $now = CarbonImmutable::now($tz);

        return DB::transaction(function () use ($employeeId, $imageDataUrl, $source, $tz, $now) {
            Employee::query()->whereKey($employeeId)->lockForUpdate()->firstOrFail(['id']);

            $open = DtrAttendance::query()
                ->where('employee_id', $employeeId)
                ->whereNull('time_out')
                ->where('work_date', '>=', $now->subDay()->toDateString())
                ->orderByDesc('work_date')->orderByDesc('time_in')
                ->first();

            if ($open) {
                $inAt    = CarbonImmutable::parse($open->work_date->toDateString().' '.$open->time_in, $tz);
                $elapsed = $now->getTimestamp() - $inAt->getTimestamp();

                if ($elapsed <= (int) config('dtr.punch.max_shift_hours') * 3600) {
                    if ($elapsed < (int) config('dtr.punch.min_interval_seconds')) {
                        return ['type' => self::TOO_SOON, 'message' => 'You just timed in. Please wait a moment before timing out.'];
                    }

                    return $this->clockOut($open, $now, $imageDataUrl, $source);
                }
            }

            $completedToday = DtrAttendance::query()
                ->where('employee_id', $employeeId)
                ->where('work_date', $now->toDateString())
                ->whereNotNull('time_out')
                ->exists();

            if ($completedToday) {
                return ['type' => self::ALREADY_COMPLETED, 'message' => 'Attendance already completed for today.'];
            }

            return $this->clockIn($employeeId, $now, $imageDataUrl, $source);
        });
    }

    private function clockIn(int $employeeId, CarbonImmutable $now, ?string $image, string $source): array
    {
        $row = DtrAttendance::create([
            'employee_id'        => $employeeId,
            'work_date'          => $now->toDateString(),
            'time_in'            => $now->format('H:i:s'),
            'time_in_image_path' => $image ? $this->images->putDataUrl($image, 'attendance') : null,
            'time_in_source'     => $source,
        ]);

        return [
            'type'     => self::TIME_IN,
            'time_in'  => $row->time_in,
            'time_out' => null,
            'message'  => 'Time In recorded at '.$row->time_in,
        ];
    }

    private function clockOut(DtrAttendance $open, CarbonImmutable $now, ?string $image, string $source): array
    {
        $open->time_out            = $now->format('H:i:s');
        $open->time_out_date       = $now->toDateString();
        $open->time_out_image_path = $image ? $this->images->putDataUrl($image, 'attendance') : null;
        $open->time_out_source     = $source;
        $open->save();

        return [
            'type'          => self::TIME_OUT,
            'time_in'       => $open->time_in,
            'time_out'      => $open->time_out,
            'time_out_date' => $open->time_out_date->toDateString(),
            'message'       => 'Time Out recorded at '.$open->time_out,
        ];
    }
}
