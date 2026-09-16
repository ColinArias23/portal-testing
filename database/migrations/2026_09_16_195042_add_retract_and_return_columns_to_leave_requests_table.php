<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->timestamp('returned_at')->nullable()->after('finalized_at');
            $table->text('return_reason')->nullable()->after('returned_at');
            $table->timestamp('retracted_at')->nullable()->after('cancellation_reason');
            $table->text('retraction_reason')->nullable()->after('retracted_at');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['returned_at', 'return_reason', 'retracted_at', 'retraction_reason']);
        });
    }
};