<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 2 — Student Onboarding (see plan.md)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete(); // student portal login (later)
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->string('er_number')->nullable()->unique(); // single source of the ER number

            // Basic details
            $table->string('first_name');
            $table->string('last_name');
            $table->date('dob');
            $table->string('gender', 20);
            $table->foreignId('qualification_id')->constrained('qualifications')->restrictOnDelete();
            $table->string('email')->unique();
            $table->string('phone', 15);
            $table->string('emergency_contact', 15);
            $table->foreignId('religion_id')->constrained('religions')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->date('joining_date');
            $table->date('onboarding_deadline'); // joining_date + config('camp.onboarding_days')

            // Address (filled on the second tab)
            $table->text('address')->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->restrictOnDelete();
            $table->foreignId('state_id')->nullable()->constrained('states')->restrictOnDelete();
            $table->string('city')->nullable();
            $table->string('pincode', 10)->nullable();

            // Parent / guardian
            $table->string('parent_name')->nullable();
            $table->string('parent_phone', 15)->nullable();
            $table->string('parent_email')->nullable();
            $table->string('parent_occupation')->nullable();

            $table->enum('status', ['draft', 'pending_docs', 'pending_approval', 'er_issued', 'active', 'alumni', 'rejected'])->default('draft');
            $table->timestamp('submitted_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['institute_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
