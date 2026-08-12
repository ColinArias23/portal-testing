<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');

            $table->decimal('opening_balance', 7, 2)->default(0);
            $table->decimal('carry_forward_in', 7, 2)->default(0);
            $table->decimal('earned', 7, 2)->default(0);  
            $table->decimal('used', 7, 2)->default(0);     
            $table->decimal('adjusted', 7, 2)->default(0); 
            $table->decimal('monetized', 7, 2)->default(0);

            $table->decimal('available', 7, 2)->storedAs(
                'opening_balance + carry_forward_in + earned + adjusted - used - monetized'
            );

            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
