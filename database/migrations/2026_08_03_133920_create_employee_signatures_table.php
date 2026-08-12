<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_signatures', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->string('type', 20);

            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('disk', 50)->default('signatures');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->string('checksum_sha256', 64);

            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['employee_id', 'type']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_signatures');
    }
};
