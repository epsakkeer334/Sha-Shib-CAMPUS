<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Module 2C — Scholarship Management.
 *
 * Additive only:
 *  - `scholarships` (institute + course, optional batch / academic year, percentage or fixed, semesters covered);
 *  - `scholarship_periods` (the semesters a scholarship covers);
 *  - `student_scholarships` (assignment; one per student + scholarship);
 *  - `student_due_discounts` (the discount of an assignment on one due);
 *  - `student_dues.discount` (sum of its discounts; balance = amount_due − discount − amount_paid). Existing dues: 0.
 *  - permissions scholarships.view (Institute Admin, Accounts), scholarships.manage / scholarships.assign (Institute Admin).
 */
return new class extends Migration
{
    protected array $grants = [
        'scholarships.view' => ['institute-admin', 'accounts'],
        'scholarships.manage' => ['institute-admin'],
        'scholarships.assign' => ['institute-admin'],
    ];

    public function up()
    {
        Schema::create('scholarships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->restrictOnDelete();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();              // null = every batch
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete(); // null = every year
            $table->string('name');
            $table->string('code', 30);
            $table->text('description')->nullable();
            $table->string('type', 12);                     // percentage | fixed
            $table->decimal('value', 10, 2);                // 0–100 (%) or ₹ per semester
            $table->string('applies_to', 20)->default('period_fees'); // period_fees | all_fees
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['institute_id', 'course_id', 'status']);
        });

        Schema::create('scholarship_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scholarship_id')->constrained('scholarships')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_no');
            $table->unique(['scholarship_id', 'period_no']);
        });

        Schema::create('student_scholarships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('scholarship_id')->constrained('scholarships')->restrictOnDelete();
            $table->string('status', 12)->default('active'); // active | revoked
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'scholarship_id']);
        });

        Schema::create('student_due_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_due_id')->constrained('student_dues')->cascadeOnDelete();
            $table->foreignId('student_scholarship_id')->constrained('student_scholarships')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamps();
            $table->unique(['student_due_id', 'student_scholarship_id'], 'sdd_due_assignment_unique');
        });

        Schema::table('student_dues', function (Blueprint $table) {
            $table->decimal('discount', 10, 2)->default(0)->after('amount_due');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($this->grants as $permission => $roles) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            foreach ($roles as $roleName) {
                if ($role = Role::where('name', $roleName)->where('guard_name', 'web')->first()) {
                    $role->givePermissionTo($permission);
                }
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::whereIn('name', array_keys($this->grants))->where('guard_name', 'web')->delete();

        Schema::table('student_dues', function (Blueprint $table) {
            $table->dropColumn('discount');
        });
        Schema::dropIfExists('student_due_discounts');
        Schema::dropIfExists('student_scholarships');
        Schema::dropIfExists('scholarship_periods');
        Schema::dropIfExists('scholarships');
    }
};
