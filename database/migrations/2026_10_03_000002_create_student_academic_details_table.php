<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 2 — Student Academic Information (one row per student)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_academic_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained('students')->cascadeOnDelete();

            $table->foreignId('matriculation_board_id')->constrained('matriculation_boards')->restrictOnDelete();
            $table->enum('matriculation_mark_type', ['percentage', 'cgpa']);
            $table->decimal('matriculation_mark', 5, 2);

            $table->foreignId('higher_secondary_board_id')->constrained('higher_secondary_boards')->restrictOnDelete();
            $table->enum('higher_secondary_subject', ['PCM', 'PCB', 'COMMERCE', 'ARTS']);
            $table->enum('higher_secondary_mark_type', ['percentage', 'cgpa']);
            $table->decimal('higher_secondary_mark', 5, 2);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_academic_details');
    }
};
