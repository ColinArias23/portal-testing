<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plantilla_positions', function (Blueprint $table) {
            $table->id();

            $table->string('base_item_number')->index();
            $table->string('title');
            $table->text('description')->nullable();

            $table->unsignedSmallInteger('slot_number');
                // ->comment('Slot number within the item (1-n)');

            $table->string('position_slot_name')
                ->unique();
                // ->comment('Generated name like "Pharmacist I-1", "Pharmacist I-2", etc');

            $table->string('position_title')->nullable();

            $table->string('role')
                ->nullable();
                // ->comment('e.g. CHIEF, DIRECTOR, STAFF, SUPERVISOR');
 
            $table->foreignId('display_department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();
                // ->comment('Primary department this position is displayed under in the org chart');

            $table->enum('status', ['VACANT', 'FILLED', 'UNFILLED'])
                ->default('VACANT')
                ->index();

            $table->foreignId('salary_grade_id')
                ->nullable()
                ->constrained('salary_grades')
                ->nullOnDelete();

            $table->foreignId('step_increment_id')
                ->nullable()
                ->constrained('step_increments')
                ->nullOnDelete();

            $table->boolean('requires_approval')->default(false)->index();
            $table->timestamp('vacated_at')->nullable();
            $table->text('vacancy_notes')->nullable();
            $table->date('date_of_assumption')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['base_item_number', 'slot_number']);
            $table->index(['display_department_id', 'status']);
            $table->index(['salary_grade_id']);
            $table->unique(['base_item_number', 'slot_number', 'deleted_at'], 
                          'pp_base_slot_deleted_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plantilla_positions');
    }
};
