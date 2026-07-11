<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE plantilla_posting_applications MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'Pending'"
        );

        DB::table('plantilla_posting_applications')
            ->where('status', 'Under Review')
            ->update(['status' => 'Initial Review/Evaluation']);

        DB::table('plantilla_posting_applications')
            ->where('status', 'Approved')
            ->update(['status' => 'Completed']);
    }

    public function down(): void
    {
        DB::table('plantilla_posting_applications')
            ->whereIn('status', [
                'Initial Review/Evaluation',
                'For Initial Deliberation',
                'Scheduled for Interview',
                'For HRMPSB Compliance',
                'For HRMPSB Deliberation',
            ])
            ->update(['status' => 'Under Review']);

        DB::table('plantilla_posting_applications')
            ->where('status', 'Completed')
            ->update(['status' => 'Approved']);

        DB::statement(
            "ALTER TABLE plantilla_posting_applications MODIFY COLUMN status ENUM('Pending','Under Review','Approved','Rejected') NOT NULL DEFAULT 'Pending'"
        );
    }
};