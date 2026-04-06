<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action');                          // e.g. employee.created
            $table->string('subject_type')->nullable();        // e.g. App\Models\Employee
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();       // e.g. "Juan Dela Cruz"

            $table->json('payload')->nullable();               // before/after snapshots
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('performed_at')->useCurrent();

            $table->index(['user_id', 'performed_at']);
            $table->index(['action', 'performed_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};