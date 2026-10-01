<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dtr_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->date('work_date');                    
            $table->time('time_in');
            $table->string('time_in_image_path')->nullable();
            $table->string('time_in_source', 16)->default('kiosk');   
            $table->time('time_out')->nullable();
            $table->date('time_out_date')->nullable();    
            $table->string('time_out_image_path')->nullable();
            $table->string('time_out_source', 16)->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['employee_id', 'work_date']);
            $table->index(['work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dtr_attendances');
    }
};
