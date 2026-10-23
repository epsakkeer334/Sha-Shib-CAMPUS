<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Module 2 steps 2.8 – 2.9 — existing students: ER number management and bulk update.
 *
 * Additive only:
 *  - `students.admission_source` (onboarding | existing | import), existing rows stay "onboarding";
 *  - `student_er_numbers`: history of every ER number given / changed / cleared (back-filled with an
 *    "issued" row for students that already have one) — an old number is never given to another student;
 *  - `student_imports`: bulk upload previews and history;
 *  - permissions `students.manage_er`, `students.bulk_update` for the Institute Admin.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('admission_source', 20)->default('onboarding')->after('status');
        });

        Schema::create('student_er_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('institute_id')->nullable()->constrained('institutes')->nullOnDelete();
            $table->string('er_number')->nullable()->index();          // the number given by this action (null when cleared)
            $table->string('previous_er_number')->nullable()->index();
            $table->string('action', 20);                              // issued | assigned | generated | changed | cleared
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('student_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->nullable()->constrained('institutes')->nullOnDelete(); // null = several institutes (Super Admin)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_name');
            $table->string('file_path')->nullable();
            $table->string('status', 20)->default('previewed'); // previewed | applied | cancelled
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_new')->default(0);
            $table->unsignedInteger('rows_updated')->default(0);
            $table->unsignedInteger('rows_unchanged')->default(0);
            $table->unsignedInteger('rows_failed')->default(0);
            $table->longText('preview')->nullable(); // json: validated rows + errors
            $table->json('result')->nullable();       // counts after apply + skipped rows
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });

        // History of the ER numbers issued so far.
        $now = now();
        DB::table('students')->whereNotNull('er_number')->orderBy('id')->select(['id', 'institute_id', 'er_number', 'updated_at'])
            ->chunk(500, function ($students) use ($now) {
                DB::table('student_er_numbers')->insert($students->map(fn ($s) => [
                    'student_id' => $s->id, 'institute_id' => $s->institute_id, 'er_number' => $s->er_number,
                    'action' => 'issued', 'reason' => 'Issued before the ER history was kept', 'created_at' => $s->updated_at ?? $now,
                ])->all());
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['students.manage_er', 'students.bulk_update'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            if ($role = Role::where('name', 'institute-admin')->where('guard_name', 'web')->first()) {
                $role->givePermissionTo($permission);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::whereIn('name', ['students.manage_er', 'students.bulk_update'])->where('guard_name', 'web')->delete();

        Schema::dropIfExists('student_imports');
        Schema::dropIfExists('student_er_numbers');
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('admission_source');
        });
    }
};
