<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('employee_org_unit_assignments', function (Blueprint $table) {
      $table->id();

      $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
      $table->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();

      $table->enum('assignment_role', ['Head','Officer','Member','Staff'])->default('Staff')->index();
      $table->boolean('is_primary')->default(false);

      $table->date('start_date')->nullable();
      $table->date('end_date')->nullable();

      $table->timestamps();

      $table->unique(['employee_id', 'org_unit_id', 'assignment_role'], 'emp_unit_role_unique');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('employee_org_unit_assignments');
  }
};
