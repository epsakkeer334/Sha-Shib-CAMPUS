<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * System Settings ▸ Background Jobs: one row per background process (the scheduler cron, the email
 * queue worker) with its last check-in ("heartbeat"), hit counters and the last 60 minutes it was seen.
 * Additive only.
 */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('background_heartbeats')) {
            Schema::create('background_heartbeats', function (Blueprint $table) {
                $table->id();
                $table->string('name', 50)->unique();          // scheduler | email_worker
                $table->timestamp('last_beat_at')->nullable();
                $table->unsignedBigInteger('beats_total')->default(0);
                $table->unsignedInteger('beats_today')->default(0);
                $table->date('beats_date')->nullable();
                $table->json('recent')->nullable();            // minute stamps (Y-m-d H:i) of the last 60 minutes with a check-in
                $table->unsignedBigInteger('jobs_processed')->default(0);
                $table->unsignedBigInteger('jobs_failed')->default(0);
                $table->timestamp('last_job_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('background_heartbeats');
    }
};
