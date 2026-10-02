<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 2 — KYC documents (files on the private "local" disk)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('institute_id')->constrained('institutes')->restrictOnDelete();
            $table->enum('document_type', ['kyc_photo', 'medical_certificate', 'marksheet_10', 'marksheet_12', 'other']);
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size'); // bytes
            $table->timestamp('uploaded_at');

            // Filled by the Admin document verification step (next step of Module 2)
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->text('remarks')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['student_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_documents');
    }
};
