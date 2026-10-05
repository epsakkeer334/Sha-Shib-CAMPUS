<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Institute code = PREFIX/ESTABLISHED_YEAR/RUNNING_NUMBER, where PREFIX is chosen by the
 * Super Admin (2–6 letters) instead of the first 3 letters of the name.
 * Existing institutes keep their codes; their prefix is copied from the code.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('institutes', function (Blueprint $table) {
            $table->string('code_prefix', 6)->nullable()->after('code');
        });

        DB::table('institutes')->whereNotNull('code')->where('code', 'like', '%/%')
            ->update(['code_prefix' => DB::raw("UPPER(SUBSTRING_INDEX(code, '/', 1))")]);
    }

    public function down()
    {
        Schema::table('institutes', function (Blueprint $table) {
            $table->dropColumn('code_prefix');
        });
    }
};
