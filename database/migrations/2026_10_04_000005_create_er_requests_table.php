<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 2.4 — ER request form lifecycle (generated → printed → TM signed → archived)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->restrictOnDelete();
            $table->foreignId('student_id')->unique()->constrained('students')->cascadeOnDelete();
            $table->string('request_form_path')->nullable(); // printable form is rendered on demand
            $table->timestamp('generated_at');
            $table->timestamp('printed_at')->nullable();
            $table->enum('tm_signature_status', ['pending', 'physically_signed'])->default('pending');
            $table->foreignId('tm_signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tm_signed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['generated', 'printed', 'signed', 'archived'])->default('generated');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('er_requests');
    }
};
