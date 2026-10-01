<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        // Add group_id to permissions table
        Schema::table('permissions', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->constrained('permission_groups')->nullOnDelete();
            $table->string('display_name')->nullable();
            $table->string('description')->nullable();
            $table->string('module')->nullable();
            $table->integer('sort_order')->default(0);
        });

        // Add display_name to roles table
        Schema::table('roles', function (Blueprint $table) {
            $table->string('display_name')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn(['group_id', 'display_name', 'description', 'module', 'sort_order']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'description', 'is_system']);
        });

        Schema::dropIfExists('permission_groups');
    }
};
