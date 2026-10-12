<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dtr_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('grace_minutes')->default(15);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('dtr_employee_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('dtr_shifts')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::table('dtr_attendances', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('employee_id')->constrained('dtr_shifts')->restrictOnDelete();
            $table->time('shift_start')->nullable()->after('shift_id');
            $table->time('shift_end')->nullable()->after('shift_start');
            $table->unsignedSmallInteger('shift_grace_minutes')->default(0)->after('shift_end');
        });
    }

    public function down(): void
    {
        Schema::table('dtr_attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
            $table->dropColumn(['shift_start', 'shift_end', 'shift_grace_minutes']);
        });

        Schema::dropIfExists('dtr_employee_shifts');
        Schema::dropIfExists('dtr_shifts');
    }
};
