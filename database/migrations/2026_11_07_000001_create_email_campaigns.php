<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Send Email (Module 1 — Email): one email sent to many students or staff. Additive only:
 *  - email_campaigns: who sent what to whom (audience, filters, wording, counts)
 *  - notifications_log.email_campaign_id: every email of a send links back to it
 *  - permission `emails.send` for the Institute Admin.
 */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('email_campaigns')) {
            Schema::create('email_campaigns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->nullable()->constrained('institutes')->nullOnDelete(); // null = several / all institutes
                $table->string('audience', 20); // students | staff
                $table->string('send_to', 20)->nullable(); // students: student | parent | both
                $table->json('filters')->nullable();
                $table->string('filters_summary', 500)->nullable();
                $table->string('template_key', 60)->nullable();
                $table->string('subject', 200);
                $table->text('body');
                $table->string('button_text', 60)->nullable();
                $table->string('button_url', 255)->nullable();
                $table->unsignedInteger('recipients_total')->default(0);
                $table->unsignedInteger('skipped_no_email')->default(0);
                $table->unsignedInteger('skipped_duplicate')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index('created_at');
            });
        }

        if (!Schema::hasColumn('notifications_log', 'email_campaign_id')) {
            Schema::table('notifications_log', function (Blueprint $table) {
                $table->foreignId('email_campaign_id')->nullable()->after('email_template_id')->constrained('email_campaigns')->nullOnDelete();
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'emails.send', 'guard_name' => 'web']);
        if ($role = Role::where('name', 'institute-admin')->where('guard_name', 'web')->first()) {
            $role->givePermissionTo('emails.send');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::where('name', 'emails.send')->where('guard_name', 'web')->delete();

        if (Schema::hasColumn('notifications_log', 'email_campaign_id')) {
            Schema::table('notifications_log', function (Blueprint $table) {
                $table->dropConstrainedForeignId('email_campaign_id');
            });
        }
        Schema::dropIfExists('email_campaigns');
    }
};
