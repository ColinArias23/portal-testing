<?php

namespace App\Services\Dtr;

use App\Exceptions\Dtr\DtrException;
use App\Models\DtrAttendance;
use App\Models\DtrShift;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class AttendanceService
{
    public const TIME_IN = 'TIME_IN';
    public const TIME_OUT = 'TIME_OUT';
    public const ALREADY_COMPLETED = 'ALREADY_COMPLETED';
    public const TOO_SOON = 'TOO_SOON';

    public function __construct(
        private readonly ImageStore $images,
        private readonly ShiftService $shifts,
        private readonly ShiftCalculator $calculator,
    ) {
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

    public function createManual(array $data): DtrAttendance
    {
        $stored = [];

        try {
            return DB::transaction(function () use ($data, &$stored) {
                Employee::query()->whereKey($data['employee_id'])->lockForUpdate()->firstOrFail(['id']);

                $this->assertNoRecordOnDate((int) $data['employee_id'], $data['work_date']);

                $attributes = $this->manualAttributes($data)
                    + $this->shifts->snapshot($this->manualShift((int) $data['employee_id'], $data['shift_id'] ?? null));

                if (! empty($data['time_in_image'])) {
                    $attributes['time_in_image_path'] = $stored[] = $this->images->putUploadedFile($data['time_in_image'], 'attendance');
                }
                if (! empty($data['time_out_image'])) {
                    $attributes['time_out_image_path'] = $stored[] = $this->images->putUploadedFile($data['time_out_image'], 'attendance');
                }

                return DtrAttendance::create($attributes + [
                    'employee_id'     => (int) $data['employee_id'],
                    'time_in_source'  => 'manual',
                    'time_out_source' => ! empty($data['time_out']) ? 'manual' : null,
                ]);
            });
        } catch (Throwable $e) {
            foreach ($stored as $path) {
                $this->images->delete($path);
            }

            throw $e;
        }
    }

    public function updateManual(DtrAttendance $attendance, array $data): DtrAttendance
    {
        $stored   = [];
        $replaced = [];

        try {
            $record = DB::transaction(function () use ($attendance, $data, &$stored, &$replaced) {
                $row = DtrAttendance::query()->whereKey($attendance->getKey())->lockForUpdate()->firstOrFail();

                if ($row->work_date->toDateString() !== $data['work_date']) {
                    $this->assertNoRecordOnDate($row->employee_id, $data['work_date'], $row->id);
                }

                $timeInChanged  = substr((string) $row->time_in, 0, 5) !== $data['time_in'] || $row->work_date->toDateString() !== $data['work_date'];
                $timeOutChanged = ! empty($data['time_out']) && substr((string) $row->time_out, 0, 5) !== $data['time_out'];

                $attributes = $this->manualAttributes($data);

                if (! empty($data['shift_id']) && (int) $data['shift_id'] !== $row->shift_id) {
                    $attributes += $this->shifts->snapshot(DtrShift::query()->findOrFail((int) $data['shift_id']));
                }

                if (! empty($data['time_in_image'])) {
                    $replaced[] = $row->time_in_image_path;
                    $attributes['time_in_image_path'] = $stored[] = $this->images->putUploadedFile($data['time_in_image'], 'attendance');
                    $timeInChanged = true;
                }
                if (! empty($data['time_out_image'])) {
                    $replaced[] = $row->time_out_image_path;
                    $attributes['time_out_image_path'] = $stored[] = $this->images->putUploadedFile($data['time_out_image'], 'attendance');
                    $timeOutChanged = true;
                }

                if ($timeInChanged) {
                    $attributes['time_in_source'] = 'manual';
                }
                if ($timeOutChanged) {
                    $attributes['time_out_source'] = 'manual';
                }

                $row->update($attributes);

                return $row;
            });
        } catch (Throwable $e) {
            foreach ($stored as $path) {
                $this->images->delete($path);
            }

            throw $e;
        }

        foreach (array_filter($replaced) as $path) {
            $this->images->delete($path);
        }

        return $record;
    }

    private function manualShift(int $employeeId, mixed $shiftId): ?DtrShift
    {
        return $shiftId
            ? DtrShift::query()->findOrFail((int) $shiftId)
            : $this->shifts->forEmployee($employeeId);
    }

    private function manualAttributes(array $data): array
    {
        $hasTimeOut = ! empty($data['time_out']);

        return [
            'work_date'     => $data['work_date'],
            'time_in'       => $data['time_in'].':00',
            'time_out'      => $hasTimeOut ? $data['time_out'].':00' : null,
            'time_out_date' => $hasTimeOut ? ($data['time_out_date'] ?? $data['work_date']) : null,
        ];
    }

    private function assertNoRecordOnDate(int $employeeId, string $workDate, ?int $ignoreId = null): void
    {
        $exists = DtrAttendance::query()
            ->where('employee_id', $employeeId)
            ->where('work_date', $workDate)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw new DtrException('This employee already has an attendance record on that date.', 409);
        }
    }

    private function clockIn(int $employeeId, CarbonImmutable $now, ?string $image, string $source): array
    {
        $shift = $this->shifts->forEmployee($employeeId);

        $row = DtrAttendance::create([
            'employee_id'        => $employeeId,
            'work_date'          => $now->toDateString(),
            'time_in'            => $now->format('H:i:s'),
            'time_in_image_path' => $image ? $this->images->putDataUrl($image, 'attendance') : null,
            'time_in_source'     => $source,
        ] + $this->shifts->snapshot($shift));

        return [
            'type'         => self::TIME_IN,
            'time_in'      => $row->time_in,
            'time_out'     => null,
            'shift'        => $this->shifts->label($shift),
            'late_minutes' => $this->calculator->minutes($row)['late_minutes'],
            'message'      => 'Time In recorded at '.$now->format('h:i A'),
        ];
    }

    private function clockOut(DtrAttendance $open, CarbonImmutable $now, ?string $image, string $source): array
    {
        $open->time_out            = $now->format('H:i:s');
        $open->time_out_date       = $now->toDateString();
        $open->time_out_image_path = $image ? $this->images->putDataUrl($image, 'attendance') : null;
        $open->time_out_source     = $source;
        $open->save();

        $minutes = $this->calculator->minutes($open);

        return [
            'type'              => self::TIME_OUT,
            'time_in'           => $open->time_in,
            'time_out'          => $open->time_out,
            'time_out_date'     => $open->time_out_date->toDateString(),
            'late_minutes'      => $minutes['late_minutes'],
            'undertime_minutes' => $minutes['undertime_minutes'],
            'overtime_minutes'  => $minutes['overtime_minutes'],
            'message'           => 'Time Out recorded at '.$now->format('h:i A'),
        ];
    }
}