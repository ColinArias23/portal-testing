<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('employee_hierarchies', function (Blueprint $table) {
      $table->id();

      $table->foreignId('parent_id')->constrained('employees')->cascadeOnDelete();
      $table->foreignId('child_id')->constrained('employees')->cascadeOnDelete();

      $table->foreignId('division_id')->nullable()->constrained('divisions')->nullOnDelete();

      $table->timestamps();

      $table->unique(['parent_id', 'child_id', 'division_id']);
      $table->index(['division_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('employee_hierarchies');
  }
};