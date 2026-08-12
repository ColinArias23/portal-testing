<?php

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LeaveAccrualService
{
    public function __construct(private readonly LeaveBalanceService $balanceService)
    {
    }

    public function runForMonth(Carbon $month): array
    {
        $config = config('leave.accrual');
        $monthlyCredit = (float) $config['monthly_credit'];
        $eligibleCodes = $config['eligible_type_codes'];

        $leaveTypes = LeaveType::query()
            ->whereIn('code', $eligibleCodes)
            ->where('is_accrual_based', true)
            ->get();

        $employees = Employee::query()
            ->plantilla()
            ->where('employment_status', 'ACTIVE') 
            ->get();

        $processed = 0;
        $skipped = 0;

        foreach ($employees as $employee) {
            if (! $this->isEligible($employee, $month)) {
                $skipped++;
                continue;
            }

            foreach ($leaveTypes as $leaveType) {
                if ($this->alreadyAccruedForMonth($employee, $leaveType, $month)) {
                    continue;
                }

                $this->balanceService->accrue(
                    $employee,
                    $leaveType,
                    $month->year,
                    $leaveType->default_monthly_accrual ?: $monthlyCredit,
                    "Monthly accrual for {$month->format('F Y')}"
                );
            }

            $processed++;
        }

        Log::info('Leave accrual run completed', [
            'month' => $month->format('Y-m'),
            'processed' => $processed,
            'skipped' => $skipped,
        ]);

        return compact('processed', 'skipped');
    }

    private function isEligible(Employee $employee, Carbon $month): bool
    {
        if ($employee->date_hired && Carbon::parse($employee->date_hired)->gt($month->copy()->endOfMonth())) {
            return false;
        }

        return true;
    }

    private function alreadyAccruedForMonth(Employee $employee, LeaveType $leaveType, Carbon $month): bool
    {
        $balance = $this->balanceService->getOrCreate($employee, $leaveType, $month->year);

        return $balance->transactions()
            ->where('type', \App\Enums\LeaveBalanceTransactionType::Accrual)
            ->whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->exists();
    }
}