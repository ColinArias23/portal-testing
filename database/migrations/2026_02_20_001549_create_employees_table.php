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
            $table->string('role_position')->nullable();
            // $table->string('parent');

            // ✅ This is the FK that errored before — now OK
            $table->foreignId('step_increment_id')
                ->nullable()
                ->constrained('step_increments')
                ->nullOnDelete();

            $table->string('prefix')->nullable();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable();

            $table->string('title')->nullable();
            // $table->string('position_designation')->nullable();

            $table->enum('employment_type', ['Plantilla', 'Consultant', 'COS'])
                  ->default('COS');
            $table->enum('employment_status', ['Active', 'Inactive', 'Resign'])
                  ->default('Active');

            $table->string('avatar')->nullable();
            $table->string('border_color')->nullable();
            $table->boolean('aligned')->default(false);
            $table->boolean('expanded')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
