<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropForeign(['plantilla_posting_id']);
            $table->dropColumn('plantilla_posting_id');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->foreignId('plantilla_posting_id')
                ->nullable()->constrained('plantilla_postings')->nullOnDelete();
        });
    }
};