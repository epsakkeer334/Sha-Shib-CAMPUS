<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The fee line paid during registration is called the "admission fee": is_registration → admission_fee.
 * Renamed in place, so lines already flagged keep their flag.
 */
return new class extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE course_fees RENAME COLUMN is_registration TO admission_fee');
    }

    public function down()
    {
        DB::statement('ALTER TABLE course_fees RENAME COLUMN admission_fee TO is_registration');
    }
};
