<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();

            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 100)->nullable();
            $table->string('age', 5)->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('source', 60)->nullable();  
            $table->string('position', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->date('date_applied')->nullable();
            $table->string('submission', 100)->nullable();
            $table->string('status', 60)->default('For Interview');
            $table->text('remarks')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }
};