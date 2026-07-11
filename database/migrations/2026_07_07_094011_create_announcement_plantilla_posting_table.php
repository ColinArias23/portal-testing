<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_plantilla_posting', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plantilla_posting_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['announcement_id', 'plantilla_posting_id'], 'ann_plantilla_posting_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_plantilla_posting');
    }
};