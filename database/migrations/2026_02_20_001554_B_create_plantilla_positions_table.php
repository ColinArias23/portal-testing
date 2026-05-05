<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('plantilla_positions', function (Blueprint $table) {

            $table->id();

            $table->foreignId('plantilla_item_id')
                ->constrained('plantilla_items')
                ->cascadeOnDelete();

            $table->string('item_number')->unique();

            $table->string('position_title')->nullable();
            
            $table->foreignId('salary_grade_id')
                ->nullable()
                ->constrained('salary_grades')
                ->restrictOnDelete();

            $table->foreignId('step_increment_id')
                ->nullable()
                ->constrained('step_increments')
                ->restrictOnDelete();

            $table->enum('status', ['VACANT', 'FILLED', 'UNFILLED'])
                ->default('VACANT');

            $table->date('date_of_assumption')->nullable();

            $table->boolean('requires_approval')->default(false);
            $table->timestamp('vacated_at')->nullable();
            $table->text('vacancy_notes')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('requires_approval');
            $table->index('plantilla_item_id');
            $table->index('salary_grade_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_positions');
    }
};