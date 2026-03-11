<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('division_id')
                ->constrained('divisions')
                ->cascadeOnDelete();

            $table->enum('type', ['DEPARTMENT', 'SECTION', 'UNIT'])
                ->default('DEPARTMENT');

            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();

            $table->unsignedBigInteger('head_employee_id')->nullable();
            $table->timestamps();

            $table->unique(['division_id', 'code']);

            $table->index('head_employee_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
