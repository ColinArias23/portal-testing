<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hiring_events', function (Blueprint $table) {
            $table->id();

            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->string('location', 150)->nullable();
            $table->string('color', 30)->default('bg-cyan-500');
            $table->boolean('all_day')->default(false);

            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->string('start_time', 20)->nullable();

            // Interviewer — references an employee
            $table->foreignId('interviewer_id')->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hiring_events');
    }
};