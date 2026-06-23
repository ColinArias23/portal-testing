<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['release_id', 'report_id']);
            $table->index('report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_reports');
    }
};