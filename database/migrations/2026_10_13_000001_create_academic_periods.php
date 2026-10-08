<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 2B — academic years, course period structure, academic periods per institute course,
 * student period history and period-wise fee lines / dues.
 * Existing fee lines stay one-time lines for every year (behaviour unchanged until periods are set up).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique();          // e.g. 2026-27
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['start_date', 'end_date']);
        });

        // Course period structure: label + length; the number of periods is courses.total_semesters
        Schema::table('courses', function (Blueprint $table) {
            $table->string('period_label', 20)->default('Semester')->after('total_semesters'); // Semester / Term / Trimester / Module / Year
            $table->unsignedSmallInteger('period_months')->nullable()->after('period_label');    // length of one period
        });
        DB::statement('UPDATE courses SET period_months = GREATEST(1, ROUND(duration_months / GREATEST(total_semesters, 1))) WHERE period_months IS NULL');

        Schema::table('batches', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('course_id')->constrained('academic_years')->nullOnDelete(); // intake year
        });

        // Periods of an institute course for one intake (academic year of period 1), optionally per batch
        Schema::create('course_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->restrictOnDelete();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('intake_academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete(); // year the period starts in
            $table->unsignedSmallInteger('period_no');
            $table->string('label', 50);                    // e.g. Semester 3
            $table->unsignedSmallInteger('year_of_study');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 12)->default('planned'); // planned | ongoing | completed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['institute_id', 'course_id', 'intake_academic_year_id', 'batch_id'], 'course_periods_calendar_index');
        });

        Schema::create('student_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('course_period_id')->constrained('course_periods')->restrictOnDelete();
            $table->unsignedSmallInteger('period_no');
            $table->string('status', 12)->default('current');  // current | completed | detained
            $table->date('started_on')->nullable();
            $table->date('completed_on')->nullable();
            $table->unsignedBigInteger('promotion_id')->nullable(); // Module 5
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['student_id', 'status']);
        });

        // Period-wise fee lines: academic year + period (null period = one-time; null year = every year)
        Schema::table('course_fees', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('course_id')->constrained('academic_years')->nullOnDelete();
            $table->unsignedSmallInteger('period_no')->nullable()->after('academic_year_id');
            $table->unique(['institute_id', 'course_id', 'academic_year_id', 'period_no', 'fee_head'], 'course_fees_period_fee_unique');
        });
        Schema::table('course_fees', function (Blueprint $table) {
            $table->dropUnique(['institute_id', 'course_id', 'fee_head']);
        });

        Schema::table('student_dues', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('course_fee_id')->constrained('academic_years')->nullOnDelete();
            $table->unsignedSmallInteger('period_no')->nullable()->after('academic_year_id');
        });
    }

    public function down()
    {
        Schema::table('student_dues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_year_id');
            $table->dropColumn('period_no');
        });
        Schema::table('course_fees', function (Blueprint $table) {
            $table->unique(['institute_id', 'course_id', 'fee_head']);
        });
        Schema::table('course_fees', function (Blueprint $table) {
            $table->dropUnique('course_fees_period_fee_unique');
            $table->dropConstrainedForeignId('academic_year_id');
            $table->dropColumn('period_no');
        });
        Schema::dropIfExists('student_periods');
        Schema::dropIfExists('course_periods');
        Schema::table('batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_year_id');
        });
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['period_label', 'period_months']);
        });
        Schema::dropIfExists('academic_years');
    }
};
