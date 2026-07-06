<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE plantilla_postings
            MODIFY employment_status ENUM('Permanent')
            NOT NULL DEFAULT 'Permanent'
        ");

        DB::statement("
            ALTER TABLE plantilla_postings
            MODIFY status ENUM('Open', 'Closing Soon', 'Closed')
            NOT NULL DEFAULT 'Open'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE plantilla_postings
            MODIFY employment_status ENUM(
                'Permanent',
                'Casual',
                'Job Order',
                'Contractual'
            ) NOT NULL DEFAULT 'Permanent'
        ");

        DB::statement("
            ALTER TABLE plantilla_postings
            MODIFY status ENUM(
                'Open',
                'Closing Soon',
                'Closed',
                'Filled'
            ) NOT NULL DEFAULT 'Open'
        ");
    }
};