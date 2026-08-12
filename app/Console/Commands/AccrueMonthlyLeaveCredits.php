<?php

namespace App\Console\Commands;

use App\Services\Leave\LeaveAccrualService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AccrueMonthlyLeaveCredits extends Command
{
    protected $signature = 'leave:accrue {--month= : Y-m to process, defaults to current month}';

    protected $description = 'Accrue monthly CSC leave credits (1.25 VL + 1.25 SL) for eligible employees.';

    public function handle(LeaveAccrualService $service): int
    {
        $month = $this->option('month')
            ? Carbon::createFromFormat('Y-m', $this->option('month'))->startOfMonth()
            : now()->startOfMonth();

        $this->info("Running leave accrual for {$month->format('F Y')}...");

        $result = $service->runForMonth($month);

        $this->info("Done. Processed: {$result['processed']}, Skipped (ineligible): {$result['skipped']}.");

        return self::SUCCESS;
    }
}
