<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_assignments', function (Blueprint $table) {

            $table->id();

            /*
            |------------------------------------------------------------------
            | EMPLOYEE
            |------------------------------------------------------------------
            */
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            /*
            |------------------------------------------------------------------
            | PLANTILLA ITEM
            |------------------------------------------------------------------
            */
            $table->foreignId('plantilla_item_id')
                ->constrained('plantilla_items')
                ->cascadeOnDelete();

            /*
            |------------------------------------------------------------------
            | STEP OVERRIDE (OPTIONAL)
            |------------------------------------------------------------------
            | If NULL → use plantilla_items.step_increment_id
            */
            $table->foreignId('step_increment_id')
                ->nullable()
                ->constrained('step_increments')
                ->nullOnDelete();

            /*
            |------------------------------------------------------------------
            | PRIMARY ASSIGNMENT
            |------------------------------------------------------------------
            */
            $table->boolean('is_primary')->default(true);

            /*
            |------------------------------------------------------------------
            | ASSIGNMENT DURATION
            |------------------------------------------------------------------
            */
            $table->date('start_date');

            $table->date('end_date')->nullable();

            $table->timestamps();

            /*
            |------------------------------------------------------------------
            | INDEXES
            |------------------------------------------------------------------
            */

            $table->index(['employee_id', 'end_date']);
            $table->index(['plantilla_item_id']);
            $table->index(['start_date']);
            $table->index(['is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_assignments');
    }
};