<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('description', 500)->nullable()->after('name');
            $table->string('access_scope', 20)->default('organization')->after('description');
        });

        Schema::create('department_role', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('department_id');

            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade');

            $table->primary(['role_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_role');

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['description', 'access_scope']);
        });
    }
};
