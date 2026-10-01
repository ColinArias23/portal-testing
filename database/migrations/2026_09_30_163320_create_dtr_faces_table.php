<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dtr_faces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
            $table->longText('encodings');               
            $table->string('image_path')->nullable();     
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dtr_faces');
    }
};
