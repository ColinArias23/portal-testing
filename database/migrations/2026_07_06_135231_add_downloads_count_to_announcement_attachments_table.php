<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcement_attachments', function (Blueprint $table) {
            $table->unsignedInteger('downloads_count')->default(0)->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('announcement_attachments', function (Blueprint $table) {
            $table->dropColumn('downloads_count');
        });
    }
};