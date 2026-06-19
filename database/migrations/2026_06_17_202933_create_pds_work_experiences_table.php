<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pds_work_experiences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->string('position', 150)->nullable();
            $table->string('department', 200)->nullable();
            $table->decimal('monthly_salary', 12, 2)->nullable();
            $table->string('salary_grade', 30)->nullable();
            $table->string('appointment_status', 60)->nullable();
            // Y or N
            $table->string('govt_service', 5)->nullable();
            $table->unsignedInteger('order')->default(0);

            $table->timestamps();

            $table->index(['employee_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pds_work_experiences');
    }
};