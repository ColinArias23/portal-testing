<?php

namespace App\Services\Dtr;

use App\Models\DtrAttendance;
use Carbon\CarbonImmutable;

class ShiftCalculator
{
    public function minutes(DtrAttendance $record): array
    {
        $empty = ['late_minutes' => null, 'undertime_minutes' => null, 'overtime_minutes' => null];

        if (! $record->shift_start || ! $record->shift_end || ! $record->time_in) {
            return $empty;
        }

        $tz       = (string) config('dtr.timezone');
        $workDate = CarbonImmutable::parse($record->work_date->toDateString(), $tz);

        $timeIn = CarbonImmutable::parse($workDate->toDateString().' '.$record->time_in, $tz);
        [$start, $end] = $this->window($workDate, $record->shift_start, $record->shift_end, $record->time_in);

        $lateSeconds = $timeIn->getTimestamp() - $start->getTimestamp();
        $graceSeconds = (int) $record->shift_grace_minutes * 60;

        $result = [
            'late_minutes'      => $lateSeconds > $graceSeconds ? intdiv($lateSeconds, 60) : 0,
            'undertime_minutes' => null,
            'overtime_minutes'  => null,
        ];

        if (! $record->time_out) {
            return $result;
        }

        $outDate = $record->time_out_date ?? $record->work_date;
        $timeOut = CarbonImmutable::parse($outDate->toDateString().' '.$record->time_out, $tz);

        $result['undertime_minutes'] = $this->positiveMinutes($end->getTimestamp() - $timeOut->getTimestamp());
        $result['overtime_minutes']  = $this->positiveMinutes($timeOut->getTimestamp() - $end->getTimestamp());

        return $result;
    }

    public function totals(array $entries): array
    {
        $totals = ['late_minutes' => 0, 'undertime_minutes' => 0, 'overtime_minutes' => 0];

        foreach ($entries as $entry) {
            foreach ($totals as $key => $value) {
                $totals[$key] = $value + (int) ($entry[$key] ?? 0);
            }
        }

        return $totals;
    }

    private function window(CarbonImmutable $workDate, string $shiftStart, string $shiftEnd, string $timeIn): array
    {
        $crossesMidnight = $shiftEnd <= $shiftStart;

        $startsPreviousDay = $crossesMidnight && $timeIn < $shiftEnd;

        $startDate = $startsPreviousDay ? $workDate->subDay() : $workDate;
        $endDate   = $crossesMidnight ? $startDate->addDay() : $startDate;

        $tz = (string) config('dtr.timezone');

        return [
            CarbonImmutable::parse($startDate->toDateString().' '.$shiftStart, $tz),
            CarbonImmutable::parse($endDate->toDateString().' '.$shiftEnd, $tz),
        ];
    }

    private function positiveMinutes(int $seconds): int
    {
        return $seconds > 0 ? intdiv($seconds, 60) : 0;
    }
}
