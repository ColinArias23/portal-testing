<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('employee_info', function (Blueprint $table) {
      $table->id();

      $table->foreignId('employee_id')
        ->constrained('employees')
        ->cascadeOnDelete()
        ->unique(); // 1:1

      $table->string('email')->nullable();
      $table->string('contact')->nullable();
      $table->string('address')->nullable();
      $table->date('birthdate')->nullable();
      $table->string('gender')->nullable();

      $table->string('department')->nullable();
      $table->string('designation')->nullable();

      $table->decimal('annual_salary', 12, 2)->nullable();
      $table->decimal('monthly_salary', 12, 2)->nullable();
      $table->integer('sg_level')->nullable();

      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('employee_info');
  }
};
