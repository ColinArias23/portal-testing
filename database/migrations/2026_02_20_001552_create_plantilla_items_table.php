<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('plantilla_items', function (Blueprint $table) {
      $table->id();

      // ✅ unit links (must exist first: departments/divisions)
      $table->foreignId('department_id')->nullable()
        ->constrained('departments')->nullOnDelete();

      $table->foreignId('division_id')->nullable()
        ->constrained('divisions')->nullOnDelete();

      $table->string('item_number')->unique();

      $table->enum('status', [
        'FILLED',
        'UNFILLED',
        'FOR_PSB',
        'PENDING_APPOINTMENT',
        'IMPENDING',
      ])->default('UNFILLED');

      $table->foreignId('salary_grade_id')
        ->constrained('salary_grades')
        ->restrictOnDelete();

      $table->string('title');
      $table->text('description')->nullable();

      $table->timestamps();

      $table->index(['department_id']);
      $table->index(['division_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('plantilla_items');
  }
};
