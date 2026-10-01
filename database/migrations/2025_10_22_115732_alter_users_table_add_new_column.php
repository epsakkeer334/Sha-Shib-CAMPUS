<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 15)->unique()->nullable();
            $table->tinyInteger('status')->default(1)->comment('0=Inactive,1=Active,2=Pending,3=Suspended');
            $table->string('user_image')->nullable()->after('remember_token');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();

            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
            $table->dropColumn('role');
            $table->dropColumn('status');
            $table->dropColumn('user_image');
            $table->dropColumn('last_login_at');
            $table->dropColumn('last_login_ip');
        });
    }
};
