<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantilla_posting_applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plantilla_posting_id')
                ->constrained('plantilla_postings')
                ->cascadeOnDelete();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->text('notes')->nullable();
            $table->boolean('certified')->default(false);

            $table->enum('status', ['Pending', 'Under Review', 'Approved', 'Rejected'])
                ->default('Pending');

            $table->timestamp('submitted_at')->nullable();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->unique(['plantilla_posting_id', 'employee_id'],
                            'pp_applications_posting_employee_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_posting_applications');
    }
};
