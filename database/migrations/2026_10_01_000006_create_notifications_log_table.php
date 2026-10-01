<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->nullable()->constrained('institutes')->nullOnDelete();
            $table->morphs('notifiable'); // user or student
            $table->enum('channel', ['sms', 'email']);
            $table->string('event_type'); // er_issued, exam_approved, result_published, mou_expiry ...
            $table->string('recipient')->nullable(); // email address / phone number used
            $table->json('payload')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_log');
    }
};
