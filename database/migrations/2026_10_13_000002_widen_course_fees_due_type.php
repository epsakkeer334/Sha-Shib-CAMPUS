<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Module 2B: due_type gains "period_start" (11 characters), longer than the original 10.
 */
return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE course_fees MODIFY due_type VARCHAR(20) NOT NULL DEFAULT 'joining'");
    }

    public function down()
    {
        DB::statement("UPDATE course_fees SET due_type = 'joining' WHERE due_type = 'period_start'");
        DB::statement("ALTER TABLE course_fees MODIFY due_type VARCHAR(10) NOT NULL DEFAULT 'joining'");
    }
};
