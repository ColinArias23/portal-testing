<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->index('target_user_id');
            $table->index('visibility');
        });

        Schema::table('notification_reads', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['target_user_id']);
            $table->dropIndex(['visibility']);
        });

        Schema::table('notification_reads', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }
};