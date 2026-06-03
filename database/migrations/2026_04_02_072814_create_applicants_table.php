<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('username');
            $table->string('email')->nullable();
            $table->string('age')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('source')->nullable();
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            $table->date('date_applied')->nullable();
            $table->string('submission')->nullable();
            $table->string('status')->default('Applied')->index();
            $table->text('remarks')->nullable();

            // Soft delete support for audit trail
            $table->softDeletes();

            $table->timestamps();

            // Indexes for common queries
            $table->index(['status', 'created_at']);
            $table->index(['department', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }
};