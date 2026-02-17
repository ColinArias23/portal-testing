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

      $table->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();
      $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();

      $table->enum('funding_source', ['Local Fund','Trust Fund','National'])->index();

      // ✅ your statuses
      $table->enum('item_status', [
        'FILLED',
        'FOR PSB',
        'IMPENDING',
        'PENDING APPOINTMENT',
        'UNFILLED',
      ])->default('UNFILLED')->index();

      $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
      $table->date('filled_at')->nullable();

      // optional overrides for manpower display
      $table->decimal('actual_monthly_salary', 12, 2)->nullable();
      $table->text('salary_override_reason')->nullable();

      $table->timestamps();

      $table->index(['org_unit_id', 'position_id']);
      $table->index(['employee_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('plantilla_items');
  }
};
