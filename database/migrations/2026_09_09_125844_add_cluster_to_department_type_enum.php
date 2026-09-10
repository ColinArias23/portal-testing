<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE departments MODIFY COLUMN type ENUM('DEPARTMENT', 'SECTION', 'UNIT', 'CLUSTER') NOT NULL DEFAULT 'DEPARTMENT'"
        );
    }

    public function down(): void
    {
        DB::statement("UPDATE departments SET type = 'DEPARTMENT' WHERE type = 'CLUSTER'");

        DB::statement(
            "ALTER TABLE departments MODIFY COLUMN type ENUM('DEPARTMENT', 'SECTION', 'UNIT') NOT NULL DEFAULT 'DEPARTMENT'"
        );
    }
};