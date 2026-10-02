<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 2.3 — fee structure per institute course (dues are generated from it)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->restrictOnDelete();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->string('fee_head'); // e.g. Admission fee, Semester 1 fee
            $table->decimal('amount', 10, 2);
            $table->unsignedSmallInteger('due_days')->default(0); // due date = joining date + due_days
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['institute_id', 'course_id', 'fee_head']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_fees');
    }
};
