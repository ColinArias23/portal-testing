<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Notifications\ContractAutoRenewed;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAutoRenewals extends Command
{
    protected $signature = 'contracts:process-auto-renewals
                                {--dry-run   : Preview what would be renewed without committing}
                                {--force     : Run even outside June/December windows}
                                {--employee= : Limit to contracts for this employee_name (for testing)}';

    protected $description = 'Process automatic 6-month contract renewals (June 1 & December 1)';

    public function handle(): int
    {
        $isDryRun     = (bool) $this->option('dry-run');
        $isForced     = (bool) $this->option('force');
        $employeeName = $this->option('employee');
        $now          = Carbon::now();

        $this->info("=== Auto-Renewal Processor · {$now->toDateTimeString()} ===");

        if ($isDryRun) {
            $this->warn('DRY-RUN — no changes will be written.');
        }

        if (! $isForced && ! in_array($now->month, [6, 12], true)) {
            $this->info(
                "Current month ({$now->format('F')}) is not a renewal window. " .
                'Use --force to override.'
            );
            return self::SUCCESS;
        }

        $query = Contract::with('applicant.employee')
            ->eligibleForAutoRenewal()
            ->forCurrentRenewalWindow();

        if ($employeeName) {
            $query->where('employee_name', 'LIKE', "%{$employeeName}%");
        }

        $contracts = $query->get();

        if ($contracts->isEmpty()) {
            $this->info('No contracts eligible for auto-renewal at this time.');
            return self::SUCCESS;
        }

        $this->info("Found {$contracts->count()} contract(s) eligible for renewal.");

        $succeeded = 0;
        $failed    = 0;
        $skipped   = 0;

        foreach ($contracts as $contract) {
            $tag = "[#{$contract->id} | {$contract->employee_name}]";

            if ($isDryRun) {
                $preview = $contract->buildRenewal();
                $this->line("  WOULD RENEW {$tag}");
                $this->line("    Period : {$preview->start_date->toDateString()} → {$preview->end_date->toDateString()}");
                $this->line("    Gen    : {$preview->renewal_generation}");
                $succeeded++;
                continue;
            }

            try {
                DB::transaction(function () use ($contract, $tag, &$succeeded): void {
                    $contract->update([
                        'status'               => 'Expired',
                        'last_auto_renewed_at' => now(),
                    ]);

                    $renewal = $contract->buildRenewal();
                    $renewal->save();

                    if ($contract->applicant?->employee) {
                        $contract->applicant->employee->notify(
                            new ContractAutoRenewed($renewal)
                        );
                    }

                    Log::info("Auto-renewal OK {$tag} → #{$renewal->id}");
                    $this->info("  ✓ {$tag} → contract #{$renewal->id} ({$renewal->start_date->toDateString()} – {$renewal->end_date->toDateString()})");
                    $succeeded++;
                });

            } catch (Throwable $e) {
                $failed++;
                Log::error("Auto-renewal FAILED {$tag}: {$e->getMessage()}", [
                    'contract_id' => $contract->id,
                    'trace'       => $e->getTraceAsString(),
                ]);
                $this->error("  ✗ {$tag}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->table(
            ['Result', 'Count'],
            [
                ['✓ Succeeded', $succeeded],
                ['✗ Failed',    $failed],
                ['— Skipped',   $skipped],
                ['Total',       $contracts->count()],
            ]
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}