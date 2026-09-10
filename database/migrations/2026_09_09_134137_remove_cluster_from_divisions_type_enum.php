<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE divisions SET type = 'DIVISION' WHERE type = 'CLUSTER'");

        DB::statement(
            "ALTER TABLE divisions MODIFY COLUMN type ENUM('OFFICE', 'DIVISION', 'DIRECTORATE') NOT NULL DEFAULT 'DIVISION'"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE divisions MODIFY COLUMN type ENUM('OFFICE', 'DIVISION', 'DIRECTORATE', 'CLUSTER') NOT NULL DEFAULT 'DIVISION'"
        );
    }
};