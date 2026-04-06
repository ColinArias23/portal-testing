<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('onboarding_id')
                ->constrained('onboardings')
                ->cascadeOnDelete();

            $table->string('title', 150);
            $table->boolean('completed')->default(false);
            $table->dateTime('completed_at')->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_tasks');
    }
};