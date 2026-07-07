<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->enum('priority', ['normal', 'important', 'urgent'])->default('normal');
            $table->enum('category', ['General', 'Job Posting'])->default('General');

            $table->foreignId('plantilla_posting_id')
                ->nullable()->constrained('plantilla_postings')->nullOnDelete();

            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_archived')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
