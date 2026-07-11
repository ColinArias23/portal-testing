<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_comments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plantilla_posting_application_document_id')
                ->constrained(
                    'plantilla_posting_application_documents',
                    'id',
                    'doc_comments_document_id_foreign'
                )
                ->cascadeOnDelete();

            $table->text('comment');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users', 'id', 'doc_comments_created_by_foreign')
                ->nullOnDelete();

            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users', 'id', 'doc_comments_resolved_by_foreign')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(
                'plantilla_posting_application_document_id',
                'doc_comments_document_id_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_comments');
    }
};