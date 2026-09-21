<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\Leave\LeaveBalanceService;
use Illuminate\Console\Command;

class GrantAnnualLeaveEntitlements extends Command
{
    protected $signature = 'leave:grant-annual {--year= : Year to grant for, defaults to current year}';

    protected $description = 'Grant fixed annual leave entitlements (e.g. 3-day Special Privilege Leave) for eligible employees who have not received them yet this year.';

    public function handle(LeaveBalanceService $service): int
    {
        $year = (int) ($this->option('year') ?: now()->year);

        $types = LeaveType::query()
            ->where('is_active', true)
            ->whereNotNull('annual_grant_days')
            ->where('annual_grant_days', '>', 0)
            ->get();

        if ($types->isEmpty()) {
            $this->info('No leave types are configured with an annual grant.');

            return self::SUCCESS;
        }

        $employees = Employee::query()->where('employment_status', 'ACTIVE')->get();

        $processed = 0;
        $skipped = 0;

        foreach ($types as $leaveType) {
            $this->info("Granting {$leaveType->name} ({$leaveType->annual_grant_days} day(s)) for {$year}...");

            foreach ($employees as $employee) {
                if (! $leaveType->isEligibleFor($employee->employment_type)
                    || ! $leaveType->isEligibleForGender($employee->info?->gender)) {
                    $skipped++;

                    continue;
                }
                $service->getOrCreate($employee, $leaveType, $year);
                $processed++;
            }
        }

        $this->info("Done. Processed: {$processed}, Skipped (ineligible): {$skipped}.");

        return self::SUCCESS;
    }
}