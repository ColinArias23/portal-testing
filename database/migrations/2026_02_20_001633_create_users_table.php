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
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            // Authentication fields
            $table->string('username')
                ->unique()
                ->index();

            $table->string('email')
                ->nullable()
                ->unique();

            $table->string('password');

            // Approval workflow fields
            $table->enum('approval_status', ['PENDING', 'APPROVED', 'REJECTED'])
                ->default('PENDING')
                ->index();

            $table->timestamp('approved_at')
                ->nullable()
                ->index();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // API token
            $table->rememberToken();

            // Timestamps
            $table->timestamps();

            // Composite index for common queries
            $table->index(['approval_status', 'approved_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
