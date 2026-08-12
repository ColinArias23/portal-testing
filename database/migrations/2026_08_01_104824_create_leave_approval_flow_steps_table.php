<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_approval_flow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->unsignedTinyInteger('step_order');

            $table->string('approver_source', 30);
            $table->string('approver_role', 100)->nullable(); 
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('is_required')->default(true);
            $table->string('label', 100)->nullable();
            $table->timestamps();

            $table->unique(['leave_type_id', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_approval_flow_steps');
    }
};
