<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->onDelete('cascade');
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();
            $table->json('scores')->nullable()->comment('key: skill_id, value: score 1-5');
            $table->decimal('average_score', 4, 2)->nullable();
            $table->integer('gap_count')->default(0)->comment('Skills scored 3 or below');
            $table->integer('strength_count')->default(0)->comment('Skills scored 4 or above');
            $table->enum('status', ['pending', 'done'])->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reviewer_notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'submitted_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_assessments');
    }
};