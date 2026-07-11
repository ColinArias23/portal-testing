<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantilla_posting_application_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plantilla_posting_application_id')
                ->constrained(
                    'plantilla_posting_applications',
                    'id',
                    'pp_app_docs_application_id_foreign'
                )
                ->cascadeOnDelete();

            $table->string('document_key');

            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_posting_application_documents');
    }
};
