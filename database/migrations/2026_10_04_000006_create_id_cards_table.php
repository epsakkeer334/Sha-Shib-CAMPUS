<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 2.5 — student ID card (valid only after the TM signs it)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('id_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->restrictOnDelete();
            $table->foreignId('student_id')->unique()->constrained('students')->cascadeOnDelete();
            $table->date('issue_date')->nullable();
            $table->enum('tm_signature_status', ['pending', 'physically_signed'])->default('pending');
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_path')->nullable();
            $table->unsignedSmallInteger('print_count')->default(0);
            $table->enum('status', ['pending', 'issued', 'reprinted'])->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('id_cards');
    }
};
