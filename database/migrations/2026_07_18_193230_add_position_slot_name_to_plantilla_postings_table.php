<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plantilla_postings', function (Blueprint $table) {
            $table->string('position_slot_name')
                ->nullable()
                ->after('base_item_number');
        });
    }

    public function down(): void
    {
        Schema::table('plantilla_postings', function (Blueprint $table) {
            $table->dropColumn('position_slot_name');
        });
    }
};