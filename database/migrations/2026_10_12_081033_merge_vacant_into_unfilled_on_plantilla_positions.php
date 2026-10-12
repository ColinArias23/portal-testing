<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('plantilla_positions')
            ->where('status', 'VACANT')
            ->update(['status' => 'UNFILLED']);

        DB::statement("
            ALTER TABLE plantilla_positions
            MODIFY status ENUM('UNFILLED', 'FILLED')
            NOT NULL DEFAULT 'UNFILLED'
        ");

        Schema::table('plantilla_positions', function (Blueprint $table) {
            $table->dropIndex(['requires_approval']);
            $table->dropColumn('requires_approval');
        });
    }

    public function down(): void
    {
        Schema::table('plantilla_positions', function (Blueprint $table) {
            $table->boolean('requires_approval')->default(false)->index()->after('step_increment_id');
        });

        DB::statement("
            ALTER TABLE plantilla_positions
            MODIFY status ENUM('VACANT', 'FILLED', 'UNFILLED')
            NOT NULL DEFAULT 'VACANT'
        ");

        // The old VACANT/UNFILLED split can't be recovered, so every unfilled slot comes back as VACANT.
        DB::table('plantilla_positions')
            ->where('status', 'UNFILLED')
            ->update(['status' => 'VACANT']);
    }
};
