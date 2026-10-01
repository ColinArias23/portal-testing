<?php

namespace App\Console\Commands;

use App\Services\Dtr\LegacyDtrImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyDtr extends Command
{
    protected $signature = 'dtr:import-legacy
        {--source-db=DTR : Database name of the old face-attendance app (same MySQL server)}
        {--dry-run : Count what would happen, write nothing}
        {--report= : Write the quarantine list to this CSV path}';

    protected $description = 'Import legacy faces/attendances into dtr_faces/dtr_attendances (unmatched rows are quarantined).';

    public function handle(LegacyDtrImporter $importer): int
    {
        $stats = $importer->import((string) $this->option('source-db'), (bool) $this->option('dry-run'));

        $this->table(['Metric', 'Count'], collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all());

        if ($path = $this->option('report')) {
            $rows = DB::table('dtr_legacy_quarantine')->orderBy('source_table')->orderBy('legacy_id')
                ->get(['source_table', 'legacy_id', 'employee_number', 'name', 'reason']);
            $fh = fopen($path, 'w');
            fputcsv($fh, ['source_table', 'legacy_id', 'employee_number', 'name', 'reason']);
            foreach ($rows as $r) {
                fputcsv($fh, (array) $r);
            }
            fclose($fh);
            $this->info("Quarantine report ({$rows->count()} rows): $path");
        }

        return self::SUCCESS;
    }
}
