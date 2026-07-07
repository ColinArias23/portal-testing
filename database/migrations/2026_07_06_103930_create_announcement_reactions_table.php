<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['like', 'love', 'insightful', 'celebrate']);
            $table->timestamps();

            $table->unique(['announcement_id', 'user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reactions');
    }
};