<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('step_increments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('salary_grade_id')
                ->constrained('salary_grades')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('step'); // 1-8
            $table->string('description')->nullable();
            $table->decimal('increment_amount', 12, 2)->default(0);

            $table->timestamps();

            $table->unique(['salary_grade_id', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('step_increments');
    }
};
