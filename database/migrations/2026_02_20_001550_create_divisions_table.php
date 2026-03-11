<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('head_employee_id')->nullable();
            
            $table->timestamps();

            $table->index('head_employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('divisions');
    }
};
