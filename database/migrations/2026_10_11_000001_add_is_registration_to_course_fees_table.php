<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registration fee flag: during registration (before the ER number) a student is charged and pays only
 * the course's registration fee line(s); the other fee lines are added when the ER number is issued.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('course_fees', function (Blueprint $table) {
            $table->boolean('is_registration')->default(false)->after('amount');
        });
    }

    public function down()
    {
        Schema::table('course_fees', function (Blueprint $table) {
            $table->dropColumn('is_registration');
        });
    }
};
