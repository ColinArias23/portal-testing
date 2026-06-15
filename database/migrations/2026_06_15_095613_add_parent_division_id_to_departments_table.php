<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('parent_division_id')
                ->nullable()
                ->after('parent_id')
                ->constrained('divisions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeignIdFor('divisions', 'parent_division_id');
            $table->dropColumn('parent_division_id');
        });
    }
};
