<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique(); 
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('color', 20)->nullable();
            $table->string('bg_color', 20)->nullable();

            $table->boolean('requires_date_range')->default(true);
            $table->boolean('is_confidential')->default(false);
            $table->unsignedSmallInteger('max_days')->nullable();
            $table->boolean('is_accrual_based')->default(false); 
            $table->decimal('default_monthly_accrual', 5, 2)->default(0); 
            $table->boolean('requires_balance_check')->default(true); 
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
