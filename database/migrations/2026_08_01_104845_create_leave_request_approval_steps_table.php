<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_request_approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->foreignId('flow_step_id')->nullable()->constrained('leave_approval_flow_steps')->nullOnDelete();

            $table->unsignedTinyInteger('step_order');
            $table->string('approver_source', 30);
            $table->string('label', 100)->nullable();

            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 20)->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['leave_request_id', 'step_order']);
            $table->index(['approver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_approval_steps');
    }
};
