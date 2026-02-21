<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            $table->string('email')->unique();
            $table->string('password');

            $table->string('role')->nullable();

            $table->string('approval_status')->default('PENDING');
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('approved_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->rememberToken();
            $table->timestamps();

            $table->index(['approval_status', 'approved_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
