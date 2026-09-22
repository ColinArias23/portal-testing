<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        $affectedUsers = DB::table('users')
            ->whereNotNull('employee_id')
            ->where('email', 'like', '%@local.test')
            ->get(['id', 'employee_id', 'email']);

        foreach ($affectedUsers as $user) {
            $this->cleanupOne($user);
        }
    }

    private function cleanupOne(object $user): void
    {
        $employee = DB::table('employees')->where('id', $user->employee_id)->first();

        if (! $employee) {
            DB::table('users')->where('id', $user->id)->update(['employee_id' => null]);

            return;
        }

        $looksLikeGhostRecord =
            $employee->employment_type === 'Contract of Service'
            && $employee->employment_status === 'pending'
            && empty($employee->employee_number)
            && empty($employee->division_id)
            && empty($employee->department_ids);

        if (! $looksLikeGhostRecord) {
            Log::warning(
                "[cleanup_ghost_employee_records] Skipped user #{$user->id} ({$user->email}) — " .
                "linked employee #{$employee->id} no longer matches the ghost-record pattern; " .
                'review manually.',
            );

            return;
        }

        DB::transaction(function () use ($user, $employee) {
            DB::table('users')->where('id', $user->id)->update(['employee_id' => null]);

            try {
                DB::table('employees')->where('id', $employee->id)->delete();
            } catch (QueryException $e) {
                Log::warning(
                    "[cleanup_ghost_employee_records] Unlinked user #{$user->id} ({$user->email}) " .
                    "but could not delete employee #{$employee->id} — it's referenced elsewhere. " .
                    "Error: {$e->getMessage()}",
                );
            }
        });
    }

    public function down(): void
    {
    }
};
