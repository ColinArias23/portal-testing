<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('plantilla_items', function (Blueprint $table) {

            $table->id();

            $table->string('item_number')->unique();

            $table->string('title');

            $table->text('description')->nullable();

            $table->enum('status', [
                'FILLED',
                'VACANT',
            ])->default('VACANT');

            /*
            |--------------------------------
            | SALARY GRADE
            |--------------------------------
            */

            $table->foreignId('salary_grade_id')
                ->nullable()
                ->constrained('salary_grades')
                ->restrictOnDelete();

            /*
            |--------------------------------
            | STEP
            |--------------------------------
            */

            $table->foreignId('step_increment_id')
                ->nullable()
                ->constrained('step_increments')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_items');
    }
};