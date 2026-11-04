<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Module 2.10 — Add-on programs: optional extras per institute course (e.g. a language course) that applicants
 * may choose at registration. Additive only:
 *  - addon_programs (per institute + course, optional fee, seats)
 *  - student_addon_programs (the choices; a paid add-on links to the due it created)
 *  - institute_courses.max_addon_programs (null = no limit)
 *  - permission `addon_programs.manage` for the Institute Admin.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('addon_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->restrictOnDelete();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('code', 30)->nullable();
            $table->text('description')->nullable();
            $table->decimal('fee_amount', 10, 2)->nullable();      // null / 0 = free
            $table->unsignedSmallInteger('fee_due_days')->default(0); // due = joining date + days
            $table->unsignedInteger('capacity')->nullable();        // seats; null = no limit
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['institute_id', 'course_id', 'status']);
        });

        Schema::create('student_addon_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('addon_program_id')->constrained('addon_programs')->restrictOnDelete();
            $table->foreignId('student_due_id')->nullable()->constrained('student_dues')->nullOnDelete();
            $table->timestamp('selected_at')->nullable();
            $table->foreignId('selected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'addon_program_id']);
        });

        Schema::table('institute_courses', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_addon_programs')->nullable()->after('status');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'addon_programs.manage', 'guard_name' => 'web']);
        if ($role = Role::where('name', 'institute-admin')->where('guard_name', 'web')->first()) {
            $role->givePermissionTo('addon_programs.manage');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::where('name', 'addon_programs.manage')->where('guard_name', 'web')->delete();

        Schema::table('institute_courses', function (Blueprint $table) {
            $table->dropColumn('max_addon_programs');
        });
        Schema::dropIfExists('student_addon_programs');
        Schema::dropIfExists('addon_programs');
    }
};
