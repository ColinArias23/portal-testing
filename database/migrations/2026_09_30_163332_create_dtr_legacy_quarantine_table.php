<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dtr_legacy_quarantine', function (Blueprint $table) {
            $table->id();
            $table->string('source_table', 32);          
            $table->unsignedBigInteger('legacy_id');
            $table->string('employee_number')->nullable();
            $table->string('name')->nullable();
            $table->string('reason', 64);               
            $table->longText('payload');                 
            $table->timestamps();

            $table->unique(['source_table', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dtr_legacy_quarantine');
    }
};
