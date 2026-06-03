<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employee_department_assignments', function (Blueprint $table) {
            $table->id();

            /*
            |------------------------------------------------------------------
            | EMPLOYEE & DEPARTMENT RELATIONSHIP
            |------------------------------------------------------------------
            */
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->foreignId('department_id')
                ->constrained('departments')
                ->cascadeOnDelete();

            /*
            |------------------------------------------------------------------
            | PRIMARY DESIGNATION
            | Each employee can have multiple departments, but only one primary.
            |------------------------------------------------------------------
            */
            $table->boolean('is_primary')->default(true);

            /*
            |------------------------------------------------------------------
            | DEPARTMENT HEAD OVERRIDE
            | If NULL, use the department's default head_employee_id.
            |------------------------------------------------------------------
            */
            $table->foreignId('department_head_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            /*
            |------------------------------------------------------------------
            | ASSIGNMENT DURATION
            |------------------------------------------------------------------
            */
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->timestamps();

            /*
            |------------------------------------------------------------------
            | UNIQUENESS & INDEXES
            |------------------------------------------------------------------
            */
            // Only one primary assignment per employee
            $table->unique(['employee_id', 'is_primary']);

            // Prevent duplicate active assignments to same dept
            $table->unique(
                ['employee_id', 'department_id'],
                'employee_dept_unique'
            );

            $table->index('department_id');
            $table->index('is_primary');
            $table->index(['employee_id', 'end_date']);
            $table->index(['department_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_department_assignments');
    }
};