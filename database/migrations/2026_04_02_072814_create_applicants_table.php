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

            $table->foreignId('employee_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            $table->string('first_name');
            $table->string('last_name');
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

            $table->softDeletes();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['department', 'status']);
            $table->index(['employee_id', 'status']);
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