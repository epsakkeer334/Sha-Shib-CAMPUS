<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 2.3 — fees a student owes (data source for the Accounts gate)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_dues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('course_fee_id')->nullable()->constrained('course_fees')->nullOnDelete(); // null = added manually
            $table->string('fee_head');
            $table->decimal('amount_due', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0); // recalculated from successful payments
            $table->date('due_date');
            $table->enum('status', ['pending', 'partial', 'cleared', 'waived'])->default('pending');
            $table->text('remarks')->nullable(); // waiver reason etc.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_dues');
    }
};
