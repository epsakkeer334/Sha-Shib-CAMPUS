<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_trail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('institute_id')->nullable()->constrained('institutes')->nullOnDelete();
            $table->string('action');  // create, update, delete, approve, reject, bypass, correct_marks ...
            $table->string('module');  // institutes, students, exams, documents ...
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('meta')->nullable(); // old/new values, reason, description
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'module']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_trail');
    }
};
