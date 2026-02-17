<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('employees', function (Blueprint $table) {
      $table->id();

      $table->string('employee_number')->unique();

      $table->string('first_name');
      $table->string('middle_name')->nullable();
      $table->string('last_name');
      $table->string('suffix')->nullable();

      $table->enum('sex', ['Male','Female','Other'])->nullable();
      $table->date('birthdate')->nullable();

      $table->string('civil_status')->nullable();
      $table->string('blood_type')->nullable();
      $table->string('citizenship')->nullable();

      $table->string('email')->nullable()->index();
      $table->string('contact_no')->nullable();

      $table->string('region')->nullable();
      $table->string('city')->nullable();
      $table->string('barangay')->nullable();
      $table->string('zipcode')->nullable();

      $table->enum('employment_status', ['Active','Inactive','Separated'])->default('Active');

      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('employees');
  }
};
