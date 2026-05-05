<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('plantilla_items', function (Blueprint $table) {

            $table->id();
            $table->string('base_item_number');

            $table->string('title');
            $table->text('description')->nullable();

            $table->unsignedSmallInteger('approved_slots')->default(1);

            $table->foreignId('division_id')
                ->nullable()
                ->constrained('divisions')
                ->nullOnDelete();

            $table->json('department_ids')
                ->nullable()
                ->comment('Array of department IDs this item belongs to');    

            $table->timestamps();

            $table->unique(
                ['base_item_number', 'title'],
                'plantilla_items_base_title_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_items');
    }
};