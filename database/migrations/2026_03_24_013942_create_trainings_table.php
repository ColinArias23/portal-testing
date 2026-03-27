<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('department')->nullable();
            $table->string('instructor')->nullable();
            $table->string('category')->nullable();
            $table->string('event_address')->nullable();
            $table->enum('training_mode', ['online', 'face-to-face'])->nullable();
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->string('duration')->nullable();
            $table->enum('status', ['active', 'upcoming', 'ongoing', 'finished', 'cancelled', 'draft'])
                  ->default('active');
            $table->integer('progress')->default(0);
            $table->unsignedInteger('max_participants')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainings');
    }
};