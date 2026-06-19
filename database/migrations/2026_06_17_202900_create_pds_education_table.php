<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pds_education', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->string('level', 20);

            $table->string('school', 200)->nullable();
            $table->string('degree', 150)->nullable();
            $table->string('period_from', 30)->nullable();
            $table->string('period_to', 30)->nullable();
            $table->string('highest_level', 100)->nullable();
            $table->string('year_graduated', 10)->nullable();
            $table->string('honors', 150)->nullable();
            $table->unsignedInteger('order')->default(0);

            $table->timestamps();

            $table->index(['employee_id', 'level', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pds_education');
    }
};