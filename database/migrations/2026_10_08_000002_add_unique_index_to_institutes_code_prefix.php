<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every institute has its own code prefix (no two institutes share one, deleted ones included).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('institutes', function (Blueprint $table) {
            $table->unique('code_prefix', 'institutes_code_prefix_unique');
        });
    }

    public function down()
    {
        Schema::table('institutes', function (Blueprint $table) {
            $table->dropUnique('institutes_code_prefix_unique');
        });
    }
};
