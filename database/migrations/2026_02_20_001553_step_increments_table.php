<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('step_increments', function (Blueprint $table) {

            $table->id();

            $table->foreignId('salary_grade_id')
                ->constrained('salary_grades')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('step');

            $table->decimal('monthly_salary',12,2)->nullable();
            $table->decimal('annual_salary',12,2)->nullable();

            $table->timestamps();

            $table->unique(['salary_grade_id','step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('step_increments');
    }
};