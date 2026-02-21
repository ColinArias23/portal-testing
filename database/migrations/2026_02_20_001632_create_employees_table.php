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

            $table->foreignId('plantilla_item_id')
                ->nullable()
                ->constrained('plantilla_items')
                ->nullOnDelete();

            // if you still want SG snapshot on employee (optional)
            $table->unsignedSmallInteger('sg_level')->nullable();

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
            $table->string('position_designation')->nullable();

            $table->string('role')->nullable();
            $table->string('employment_type')->nullable();
            $table->string('employment_status')->nullable();

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
