<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('plantilla_items', function (Blueprint $table) {
      $table->id();

      /*
      |--------------------------------------------------------------------------
      | ORGANIZATIONAL LINK
      |--------------------------------------------------------------------------
      | Every plantilla item must belong to a Department.
      | Division is already linked through Department.
      */
      $table->foreignId('department_id')
            ->constrained('departments')
            ->cascadeOnDelete();

      // $table->foreignId('division_id')->nullable()
      //   ->constrained('divisions')->nullOnDelete();

      /*
      |--------------------------------------------------------------------------
      | POSITION DETAILS
      |--------------------------------------------------------------------------
      */
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

      /*
      |--------------------------------------------------------------------------
      | INDEXES
      |--------------------------------------------------------------------------
      */
      $table->index('department_id');
      $table->index('status');

    //   $table->index(['division_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('plantilla_items');
  }
};
