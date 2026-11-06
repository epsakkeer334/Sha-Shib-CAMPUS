<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * SMTP2GO email service (Module 1 — Email). Additive only:
 *  - email_templates: group-wide (institute_id null) or per-institute wording of each email in config/email_templates.php
 *  - institute_email_settings: per-institute sender name, reply-to and signature
 *  - notifications_log: subject, template, SMTP2GO email id, attempts (the email log)
 *  - jobs: Laravel queue table, used only if QUEUE_CONNECTION=database
 *  - permission `email_templates.manage` for the Institute Admin.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->nullable()->constrained('institutes')->cascadeOnDelete(); // null = group-wide
            $table->string('key', 60);
            $table->string('subject', 200);
            $table->text('body');
            $table->string('button_text', 60)->nullable();
            $table->string('button_url', 255)->nullable();
            $table->boolean('status')->default(true); // off = this email is not sent
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['key', 'institute_id']);
        });

        Schema::create('institute_email_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->unique()->constrained('institutes')->cascadeOnDelete();
            $table->string('sender_name', 100)->nullable();
            $table->string('reply_to', 150)->nullable();
            $table->text('signature')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('notifications_log', function (Blueprint $table) {
            $table->string('subject', 255)->nullable()->after('recipient');
            $table->string('template_key', 60)->nullable()->after('event_type');
            $table->foreignId('email_template_id')->nullable()->after('template_key')->constrained('email_templates')->nullOnDelete();
            $table->string('provider', 30)->nullable()->after('status');
            $table->string('provider_message_id', 100)->nullable()->after('provider');
            $table->unsignedTinyInteger('attempts')->default(0)->after('provider_message_id');
            $table->timestamp('last_attempt_at')->nullable()->after('attempts');
            $table->index(['channel', 'status']);
        });

        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'email_templates.manage', 'guard_name' => 'web']);
        if ($role = Role::where('name', 'institute-admin')->where('guard_name', 'web')->first()) {
            $role->givePermissionTo('email_templates.manage');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::where('name', 'email_templates.manage')->where('guard_name', 'web')->delete();

        Schema::table('notifications_log', function (Blueprint $table) {
            $table->dropConstrainedForeignId('email_template_id');
            $table->dropIndex(['channel', 'status']);
            $table->dropColumn(['subject', 'template_key', 'provider', 'provider_message_id', 'attempts', 'last_attempt_at']);
        });
        Schema::dropIfExists('institute_email_settings');
        Schema::dropIfExists('email_templates');
        // `jobs` is left in place: it may hold queued work.
    }
};
