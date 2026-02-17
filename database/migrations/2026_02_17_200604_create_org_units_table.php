<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('org_units', function (Blueprint $table) {
      $table->id();

      $table->foreignId('division_id')->nullable()
        ->constrained('divisions')->nullOnDelete();

      $table->string('code')->unique();
      $table->string('name');

      $table->enum('type', ['Department','Section','Unit','Office','Committee'])
        ->default('Department');

      $table->foreignId('parent_id')->nullable()
        ->constrained('org_units')->nullOnDelete();

      $table->text('description')->nullable();
      $table->timestamps();

      $table->index(['division_id', 'parent_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('org_units');
  }
};
