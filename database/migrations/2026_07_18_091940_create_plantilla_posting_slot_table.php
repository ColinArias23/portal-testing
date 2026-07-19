<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantilla_posting_slots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plantilla_posting_id')
                ->constrained('plantilla_postings')
                ->cascadeOnDelete();

            $table->foreignId('plantilla_position_id')
                ->constrained('plantilla_positions')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(
                ['plantilla_posting_id', 'plantilla_position_id'],
                'pp_slots_posting_position_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_posting_slots');
    }
};