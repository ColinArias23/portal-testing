<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Division;
use Illuminate\Console\Command;

class AuditLeaveApprovalHierarchy extends Command
{
    protected $signature = 'leave:audit-hierarchy';

    protected $description = 'Report Departments/Divisions missing data needed for Division/Directorate leave approval steps, before marking those steps as required.';

    public function handle(): int
    {
        $departmentsWithoutDivision = Department::whereNull('division_id')->get(['id', 'name', 'code']);

        $divisionsWithoutHead = Division::whereNull('head_employee_id')->get(['id', 'name', 'code']);

        $divisionsWithUnlinkedHead = Division::whereNotNull('head_employee_id')
            ->whereDoesntHave('head.user')
            ->with('head:id,first_name,last_name')
            ->get(['id', 'name', 'code', 'head_employee_id']);

        $rootDivisionsWithoutHead = Division::whereNull('parent_id')
            ->whereNull('head_employee_id')
            ->get(['id', 'name', 'code']);

        $this->line('');
        $this->components->twoColumnDetail('<fg=yellow>Departments with no division_id</>', (string) $departmentsWithoutDivision->count());
        foreach ($departmentsWithoutDivision as $dept) {
            $this->line("  - [{$dept->code}] {$dept->name}");
        }

        $this->line('');
        $this->components->twoColumnDetail('<fg=yellow>Divisions with no head_employee_id</>', (string) $divisionsWithoutHead->count());
        foreach ($divisionsWithoutHead as $division) {
            $this->line("  - [{$division->code}] {$division->name}");
        }

        $this->line('');
        $this->components->twoColumnDetail('<fg=yellow>Divisions with a head but no linked user account</>', (string) $divisionsWithUnlinkedHead->count());
        foreach ($divisionsWithUnlinkedHead as $division) {
            $headName = $division->head ? "{$division->head->first_name} {$division->head->last_name}" : 'unknown';
            $this->line("  - [{$division->code}] {$division->name} (head: {$headName})");
        }

        $this->line('');
        $this->components->twoColumnDetail('<fg=yellow>Top-level (directorate) divisions with no head</>', (string) $rootDivisionsWithoutHead->count());
        foreach ($rootDivisionsWithoutHead as $division) {
            $this->line("  - [{$division->code}] {$division->name}");
        }

        $blocking = $departmentsWithoutDivision->count()
            + $divisionsWithoutHead->count()
            + $divisionsWithUnlinkedHead->count();

        $this->line('');
        if ($blocking > 0) {
            $this->warn("Found {$blocking} data gap(s). Marking Division/Directorate approval steps as required now will cause leave submission to fail (RuntimeException) for affected employees. Fix the data above first, or leave those steps as is_required=false.");
        } else {
            $this->info('No gaps found. Division/Directorate approval steps should be safe to mark is_required = true in LeaveApprovalFlowSeeder.');
        }

        return self::SUCCESS;
    }
}