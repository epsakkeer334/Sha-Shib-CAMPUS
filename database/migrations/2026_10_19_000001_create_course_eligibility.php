<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Module 2 step 2.7 — Course eligibility criteria (Higher Secondary / 12th).
 *
 * Additive only:
 *  - masters `qualifying_subjects`, `qualifying_subject_groups` (+ pivot) — the 12th subjects, NOT the
 *    Module 3 course `subjects`; seeded with PCM / PCB / Commerce / Arts;
 *  - `higher_secondary_boards.cgpa_factor` (CGPA → % conversion, default 9.5);
 *  - `course_eligibility_criteria` (+ minimum % per subject);
 *  - per-subject 12th marks of a student and the stored eligibility result;
 *  - `student_academic_details.higher_secondary_subject` widened from a fixed enum to the group code
 *    (existing values PCM / PCB / COMMERCE / ARTS are kept and linked to the seeded groups);
 *  - permission `eligibility.override` for the Institute Admin.
 */
return new class extends Migration
{
    // code => [name, subject codes]
    protected array $groups = [
        'PCM' => ['Physics, Chemistry, Mathematics', ['PHY', 'CHE', 'MAT']],
        'PCB' => ['Physics, Chemistry, Biology', ['PHY', 'CHE', 'BIO']],
        'COMMERCE' => ['Commerce', ['ACC', 'BST']],
        'ARTS' => ['Arts / Humanities', ['HIS', 'POL', 'ECO']],
    ];

    // code => name
    protected array $subjects = [
        'PHY' => 'Physics', 'CHE' => 'Chemistry', 'MAT' => 'Mathematics', 'BIO' => 'Biology',
        'ACC' => 'Accountancy', 'BST' => 'Business Studies', 'ECO' => 'Economics', 'ENG' => 'English',
        'CSC' => 'Computer Science', 'HIS' => 'History', 'POL' => 'Political Science', 'GEO' => 'Geography',
    ];

    public function up()
    {
        $audit = function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        };

        Schema::create('qualifying_subjects', function (Blueprint $table) use ($audit) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 20)->unique();
            $table->boolean('status')->default(true);
            $audit($table);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('qualifying_subject_groups', function (Blueprint $table) use ($audit) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->boolean('status')->default(true);
            $audit($table);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('qualifying_group_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qualifying_subject_group_id')->constrained('qualifying_subject_groups')->cascadeOnDelete();
            $table->foreignId('qualifying_subject_id')->constrained('qualifying_subjects')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['qualifying_subject_group_id', 'qualifying_subject_id'], 'qgs_group_subject_unique');
        });

        Schema::table('higher_secondary_boards', function (Blueprint $table) {
            $table->decimal('cgpa_factor', 4, 2)->default(9.5)->after('name');
        });

        Schema::create('course_eligibility_criteria', function (Blueprint $table) use ($audit) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->foreignId('higher_secondary_board_id')->nullable()->constrained('higher_secondary_boards')->restrictOnDelete(); // null = any board
            $table->foreignId('qualifying_subject_group_id')->constrained('qualifying_subject_groups')->restrictOnDelete();
            $table->decimal('min_aggregate', 5, 2)->nullable(); // average % of the required subjects
            $table->text('remarks')->nullable();
            $table->boolean('status')->default(true);
            $audit($table);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['course_id', 'status']);
        });

        Schema::create('course_eligibility_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_eligibility_criterion_id');
            $table->foreign('course_eligibility_criterion_id', 'ces_criterion_fk')->references('id')->on('course_eligibility_criteria')->cascadeOnDelete(); // default name > 64 chars
            $table->foreignId('qualifying_subject_id')->constrained('qualifying_subjects')->restrictOnDelete();
            $table->decimal('min_percent', 5, 2);
            $table->timestamps();
            $table->unique(['course_eligibility_criterion_id', 'qualifying_subject_id'], 'ces_criterion_subject_unique');
        });

        Schema::create('student_qualifying_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('qualifying_subject_id')->constrained('qualifying_subjects')->restrictOnDelete();
            $table->string('mark_type', 12); // percentage | cgpa
            $table->decimal('mark', 5, 2);
            $table->decimal('percent', 5, 2); // converted with the board's CGPA factor
            $table->timestamps();
            $table->unique(['student_id', 'qualifying_subject_id'], 'sqm_student_subject_unique');
        });

        // Fixed enum → group code (values kept as they are).
        DB::statement("ALTER TABLE student_academic_details MODIFY higher_secondary_subject VARCHAR(20) NOT NULL");

        Schema::table('student_academic_details', function (Blueprint $table) {
            $table->foreignId('qualifying_subject_group_id')->nullable()->after('higher_secondary_subject')
                ->constrained('qualifying_subject_groups')->restrictOnDelete();
            $table->string('eligibility_status', 20)->nullable()->after('higher_secondary_mark'); // eligible | not_eligible | not_configured | overridden
            $table->json('eligibility_snapshot')->nullable()->after('eligibility_status');
            $table->timestamp('eligibility_checked_at')->nullable()->after('eligibility_snapshot');
            $table->foreignId('eligibility_override_by')->nullable()->after('eligibility_checked_at')->constrained('users')->nullOnDelete();
            $table->timestamp('eligibility_override_at')->nullable()->after('eligibility_override_by');
            $table->text('eligibility_override_reason')->nullable()->after('eligibility_override_at');
        });

        $this->seedMasters();

        // Link existing academic rows to the seeded groups.
        foreach (DB::table('qualifying_subject_groups')->pluck('id', 'code') as $code => $id) {
            DB::table('student_academic_details')->where('higher_secondary_subject', $code)->whereNull('qualifying_subject_group_id')
                ->update(['qualifying_subject_group_id' => $id]);
        }

        $this->grantPermission();
    }

    protected function seedMasters(): void
    {
        $now = now();
        foreach ($this->subjects as $code => $name) {
            if (!DB::table('qualifying_subjects')->where('code', $code)->exists()) {
                DB::table('qualifying_subjects')->insert(['code' => $code, 'name' => $name, 'status' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $subjectIds = DB::table('qualifying_subjects')->pluck('id', 'code');

        foreach ($this->groups as $code => [$name, $codes]) {
            if (DB::table('qualifying_subject_groups')->where('code', $code)->exists()) {
                continue;
            }
            $groupId = DB::table('qualifying_subject_groups')->insertGetId(['code' => $code, 'name' => $name, 'status' => true, 'created_at' => $now, 'updated_at' => $now]);
            foreach (array_values($codes) as $i => $subjectCode) {
                DB::table('qualifying_group_subjects')->insert([
                    'qualifying_subject_group_id' => $groupId, 'qualifying_subject_id' => $subjectIds[$subjectCode],
                    'sort_order' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    protected function grantPermission(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'eligibility.override', 'guard_name' => 'web']);
        if ($role = Role::where('name', 'institute-admin')->where('guard_name', 'web')->first()) {
            $role->givePermissionTo('eligibility.override');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::where('name', 'eligibility.override')->where('guard_name', 'web')->delete();

        Schema::table('student_academic_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qualifying_subject_group_id');
            $table->dropConstrainedForeignId('eligibility_override_by');
            $table->dropColumn(['eligibility_status', 'eligibility_snapshot', 'eligibility_checked_at', 'eligibility_override_at', 'eligibility_override_reason']);
        });
        // The column stays a VARCHAR: groups added later may not fit the old enum.

        Schema::dropIfExists('student_qualifying_marks');
        Schema::dropIfExists('course_eligibility_subjects');
        Schema::dropIfExists('course_eligibility_criteria');
        Schema::table('higher_secondary_boards', function (Blueprint $table) {
            $table->dropColumn('cgpa_factor');
        });
        Schema::dropIfExists('qualifying_group_subjects');
        Schema::dropIfExists('qualifying_subject_groups');
        Schema::dropIfExists('qualifying_subjects');
    }
};
