<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboardings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            $table->foreignId('applicant_id')->nullable()
                ->constrained('applicants')
                ->nullOnDelete();

            $table->string('name', 150);
            $table->string('position', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->dateTime('start_date')->nullable();
            $table->enum('status', ['Pending', 'In Progress', 'Completed'])->default('Pending');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboardings');
    }
};