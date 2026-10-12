<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advance payment: a fee line can allow students to pay it before it is charged (e.g. the
 * Semester 2 fee while in Semester 1). The due created that way is marked as an advance.
 * Existing rows: off (no change in behaviour).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('course_fees', function (Blueprint $table) {
            $table->boolean('allow_advance')->default(false)->after('admission_fee');
        });
        Schema::table('student_dues', function (Blueprint $table) {
            $table->boolean('advance')->default(false)->after('period_no');
        });
    }

    public function down()
    {
        Schema::table('student_dues', function (Blueprint $table) {
            $table->dropColumn('advance');
        });
        Schema::table('course_fees', function (Blueprint $table) {
            $table->dropColumn('allow_advance');
        });
    }
};
