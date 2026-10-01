<?php

namespace App\Services\Dtr;

use App\Models\Dtr\DtrAttendance;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DtrCalendarService
{
    public function __construct(private readonly DtrSignatureService $signatures)
    {
    }

    public function cutoff(Employee $employee, int $month, int $year): array
    {
        $rightStart = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $rightEnd   = CarbonImmutable::create($year, $month, 25)->endOfDay();
        $leftStart  = $rightStart->subMonthNoOverflow()->day(26)->startOfDay();
        $leftEnd    = $leftStart->endOfMonth();

        $records = $this->records($employee->id, $leftStart, $rightEnd);

        $verifierUser = $this->signatures->verifierUser();

        return [
            'employee'      => $this->employeePayload($employee),
            'has_signature' => $this->signatures->hasSignature($employee),
            'verifier'      => [
                'name'          => $verifierUser?->formal_name ?? $verifierUser?->employee?->full_name,
                'has_signature' => $this->signatures->hasSignature($verifierUser?->employee),
            ],
            'left_range'    => ['start' => $leftStart->toDateString(), 'end' => $leftEnd->toDateString()],
            'right_range'   => ['start' => $rightStart->toDateString(), 'end' => $rightEnd->toDateString()],
            'left_entries'  => $this->build($records, $leftStart, $leftEnd),
            'right_entries' => $this->build($records, $rightStart, $rightEnd),
        ];
    }

    public function month(Employee $employee, int $month, int $year): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $end   = $start->endOfMonth();

        return [
            'employee' => $this->employeePayload($employee),
            'entries'  => $this->build($this->records($employee->id, $start, $end), $start, $end),
            'month'    => $month,
            'year'     => $year,
        ];
    }

    public function employeePayload(Employee $employee): array
    {
        return ['name' => $employee->full_name, 'employee_number' => $employee->employee_number];
    }

    private function records(int $employeeId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return DtrAttendance::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('work_date', [$from->subDay()->toDateString(), $to->addDay()->toDateString()])
            ->orderBy('work_date')
            ->get();
    }

    public function build(Collection $records, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $days = [];
        for ($c = $start->startOfDay(); $c->lte($end); $c = $c->addDay()) {
            $days[$c->toDateString()] = [
                'day' => $c->day, 'date' => $c->toDateString(),
                'am_arrival' => null, 'am_departure' => null,
                'pm_arrival' => null, 'pm_departure' => null,
            ];
        }

        foreach ($records as $r) {
            $inKey = $r->work_date->toDateString();
            if ($r->time_in && isset($days[$inKey])) {
                $slot = (int) substr($r->time_in, 0, 2) < 12 ? 'am_arrival' : 'pm_arrival';
                $days[$inKey][$slot] = $r->time_in;
            }

            if ($r->time_out) {
                $outKey = ($r->time_out_date ?? $r->work_date)->toDateString();
                if (isset($days[$outKey])) {
                    $slot = (int) substr($r->time_out, 0, 2) < 12 ? 'am_departure' : 'pm_departure';
                    $days[$outKey][$slot] = $r->time_out;
                }
            }
        }

        return array_values($days);
    }
}
