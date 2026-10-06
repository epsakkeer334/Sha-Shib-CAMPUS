<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A fee line is due either N days after the student's joining date (as before) or on a fixed
 * calendar date (e.g. "Semester 2 fee — due 15 Jan 2027").
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('course_fees', function (Blueprint $table) {
            $table->string('due_type', 10)->default('joining')->after('amount'); // joining | fixed
            $table->date('due_date')->nullable()->after('due_type');            // when due_type = fixed
        });
    }

    public function down()
    {
        Schema::table('course_fees', function (Blueprint $table) {
            $table->dropColumn(['due_type', 'due_date']);
        });
    }
};
