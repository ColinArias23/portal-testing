<?php
 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
 
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('development_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->onDelete('cascade');
            $table->foreignId('skill_assessment_id')
                ->constrained('skill_assessments')
                ->onDelete('cascade');
            $table->foreignId('skill_id')
                ->constrained('skills')
                ->onDelete('cascade');
            $table->text('goal')->nullable();
            $table->json('actions')->nullable();
            $table->json('resources')->nullable();
            $table->date('start_date')->nullable();
            $table->date('target_date')->nullable();
            $table->enum('status', ['planned', 'in_progress', 'completed', 'paused'])->default('planned');
            $table->integer('progress_percentage')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
 
            $table->index(['employee_id', 'status']);
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('development_plans');
    }
};