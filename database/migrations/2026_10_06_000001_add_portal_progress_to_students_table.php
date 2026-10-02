<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Admissions portal: resume where the student left off, and keep unsaved form entries.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('portal_last_step', 20)->nullable()->after('submitted_at'); // details | academic | documents | payment
            $table->json('portal_drafts')->nullable()->after('portal_last_step'); // step => field => value (not yet saved)
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['portal_last_step', 'portal_drafts']);
        });
    }
};
