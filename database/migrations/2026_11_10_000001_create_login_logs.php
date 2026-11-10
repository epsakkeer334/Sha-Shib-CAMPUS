<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Login Activity (Monitoring): every sign-in, failed / blocked attempt and sign-out of the admin panel
 * and the admissions portal. Rows are only added (App\Services\LoginLogger), never edited.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('login_logs')) {
            return;
        }

        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // null: no account with that email
            $table->foreignId('institute_id')->nullable()->constrained('institutes')->nullOnDelete();
            $table->string('email', 191)->nullable();       // as typed on the sign-in form
            $table->string('panel', 10);                    // admin | portal
            $table->string('event', 10);                    // login | failed | blocked | logout
            $table->string('reason', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('browser', 60)->nullable();
            $table->string('platform', 60)->nullable();
            $table->string('device', 10)->nullable();       // desktop | mobile | tablet | bot
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['event', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('ip');
        });
    }

    public function down()
    {
        Schema::dropIfExists('login_logs');
    }
};
