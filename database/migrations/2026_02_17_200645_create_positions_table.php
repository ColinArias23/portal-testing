<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('positions', function (Blueprint $table) {
      $table->id();
      $table->string('code')->unique();
      $table->string('title');
      $table->unsignedTinyInteger('salary_grade')->nullable()->index();
      $table->enum('employment_type', ['Plantilla','COS','Consultant'])
        ->default('Plantilla')->index();
      $table->text('description')->nullable();
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('positions');
  }
};
