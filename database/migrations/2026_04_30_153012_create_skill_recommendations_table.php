<?php
 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
 
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')
                ->constrained('skills')
                ->onDelete('cascade');
            $table->json('actions')->nullable()->comment('Array of L&D actions');
            $table->json('resources')->nullable()->comment('Array of learning resources');
            $table->json('manager_support')->nullable()->comment('Array of manager support strategies');
            $table->string('timeline')->nullable()->comment('e.g., "3-6 months", "1 year"');
            $table->timestamps();
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('skill_recommendations');
    }
};