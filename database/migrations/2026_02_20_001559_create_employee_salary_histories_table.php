<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_histories', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | EMPLOYEE
            |--------------------------------------------------------------------------
            */

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | SALARY RECORD
            |--------------------------------------------------------------------------
            */

            $table->decimal('gross_salary', 12, 2);
            $table->decimal('annual_salary', 12, 2);

            /*
            |--------------------------------------------------------------------------
            | OPTIONAL REFERENCES
            |--------------------------------------------------------------------------
            */

            $table->unsignedTinyInteger('salary_grade')->nullable();
            $table->unsignedTinyInteger('step')->nullable();

            /*
            |--------------------------------------------------------------------------
            | DATE RANGE
            |--------------------------------------------------------------------------
            */

            $table->date('effective_date');
            $table->date('end_date')->nullable();

            /*
            |--------------------------------------------------------------------------
            | NOTES
            |--------------------------------------------------------------------------
            */

            $table->text('notes')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */

            $table->index(['employee_id','effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_histories');
    }
};