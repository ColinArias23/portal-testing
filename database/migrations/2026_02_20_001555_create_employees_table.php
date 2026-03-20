<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            $table->text('employee_number')->nullable();
            $table->json('role_position')->nullable();

            $table->foreignId('division_id')
                ->nullable()
                ->constrained('divisions')
                ->nullOnDelete();

            $table->json('department_ids')->nullable();

            $table->text('prefix')->nullable();
            $table->text('first_name')->nullable();
            $table->text('middle_name')->nullable();
            $table->text('last_name')->nullable();
            $table->text('suffix')->nullable();

            $table->json('position_designation')->nullable();
            $table->text('title')->nullable();

            $table->enum('employment_type', ['Plantilla', 'Consultant', 'Contract of Service'])
                ->default('Contract of Service');

            $table->text('employment_status')->nullable();

            $table->string('avatar_url')->nullable();
            $table->text('border_color')->nullable();

            $table->boolean('aligned')->default(false);
            $table->boolean('expanded')->default(false);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('division_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};