<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_request_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();

            $table->string('requirement_key', 60);
            $table->string('original_filename', 255);
            $table->string('stored_path', 500); 
            $table->string('disk', 30)->default('leave_documents');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64)->nullable(); 

            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['leave_request_id', 'requirement_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_documents');
    }
};
