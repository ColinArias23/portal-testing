<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 30)->unique();

            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();

            $table->string('status', 20)->default('pending')->index();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_half_day')->default(false);
            $table->decimal('total_days', 6, 2)->nullable(); 

            $table->text('reason')->nullable();

            $table->json('details')->nullable();

            $table->boolean('is_confidential')->default(false); 

            $table->unsignedTinyInteger('current_step_order')->nullable();
            $table->unsignedTinyInteger('total_steps')->default(1);

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index(['leave_type_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
