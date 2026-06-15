<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('plantilla_positions', function (Blueprint $table) {
            $table->foreignId('display_division_id')
                ->nullable()
                ->after('display_department_id')
                ->constrained('divisions')
                ->nullOnDelete();

            $table->index(['display_division_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('plantilla_positions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('display_division_id');
        });
    }
};