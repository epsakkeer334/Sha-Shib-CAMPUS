<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Scholarships\AssignScholarshipComponent;
use App\Http\Livewire\Admin\Scholarships\ScholarshipsComponent;
use App\Http\Livewire\Admin\Students\StudentFeesComponent;
use App\Models\Admin\AcademicYear;
use App\Models\Admin\AuditTrail;
use App\Models\Admin\Batch;
use App\Models\Admin\Category;
use App\Models\Admin\Course;
use App\Models\Admin\CourseFee;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteCourse;
use App\Models\Admin\PaymentGateway;
use App\Models\Admin\Qualification;
use App\Models\Admin\Religion;
use App\Models\Admin\Scholarship;
use App\Models\Admin\Student;
use App\Models\Admin\StudentDue;
use App\Models\Admin\StudentScholarship;
use App\Models\User;
use App\Services\FeeService;
use App\Services\PeriodService;
use App\Services\ScholarshipService;
use Database\Seeders\admin\MasterDataSeeder;
use Database\Seeders\admin\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module 2C — Scholarship Management. Runs inside a transaction on the *_testing database (rolled back).
 */
class Module2CScholarshipsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $super;
    protected User $admin;
    protected Institute $inst;
    protected Institute $other;
    protected Course $course;
    protected Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
        $this->seed(MasterDataSeeder::class);

        $this->inst = $this->makeInstitute('Schol Aviation', 2003);
        $this->other = $this->makeInstitute('Other Wings', 2012);
        $this->super = $this->makeUser('super-admin');
        $this->admin = $this->makeUser('institute-admin', $this->inst);

        $this->course = Course::create(['name' => 'AME B1.1', 'code' => 'SB1-' . substr(uniqid(), -5), 'duration_months' => 24, 'total_semesters' => 4, 'period_months' => 6, 'status' => true]);
        foreach ([$this->inst, $this->other] as $i) {
            InstituteCourse::create(['institute_id' => $i->id, 'course_id' => $this->course->id, 'status' => true]);
        }
        AcademicYear::firstOrCreate(['name' => '2025-26'], ['start_date' => '2025-06-01', 'end_date' => '2026-05-31', 'status' => true]);
        AcademicYear::firstOrCreate(['name' => '2026-27'], ['start_date' => '2026-06-01', 'end_date' => '2027-05-31', 'status' => true]);
        $this->batch = Batch::create(['institute_id' => $this->inst->id, 'course_id' => $this->course->id, 'name' => 'June 2025', 'code' => 'SC-' . substr(uniqid(), -6), 'start_date' => '2025-06-01', 'status' => true]);
        app(PeriodService::class)->generate($this->inst, $this->course, '2025-06-01', $this->batch);

        // Fee structure: one-time ₹15,000 · Semester 1: tuition ₹40,000 + lab ₹10,000 · Semester 2: tuition ₹42,000
        foreach ([['Admission fee', 15000, null], ['Tuition S1', 40000, 1], ['Lab S1', 10000, 1], ['Tuition S2', 42000, 2]] as $i => [$head, $amount, $period]) {
            CourseFee::create(['institute_id' => $this->inst->id, 'course_id' => $this->course->id, 'fee_head' => $head, 'amount' => $amount,
                'period_no' => $period, 'due_type' => $period ? 'period_start' : 'joining', 'due_days' => 0, 'sort_order' => $i, 'status' => true]);
        }
    }

    protected function makeInstitute(string $name, int $year): Institute
    {
        return Institute::create([
            'name' => $name . ' ' . uniqid(), 'established_year' => $year, 'code' => Institute::generateCode(strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3)), $year),
            'email' => uniqid() . '@inst.test', 'phone' => (string) random_int(1000000000, 9999999999), 'status' => true,
        ]);
    }

    protected function makeUser(string $role, ?Institute $institute = null): User
    {
        $user = User::create(['name' => $role, 'email' => uniqid($role) . '@user.test', 'password' => bcrypt('password'), 'institute_id' => $institute?->id, 'status' => true]);
        $user->assignRole($role);

        return $user;
    }

    /** An admitted student in Semester 1 with the one-time + Semester 1 dues charged. */
    protected function admitted(string $name = 'Asha', ?Institute $inst = null, ?Batch $batch = null): Student
    {
        $religion = Religion::where('name', 'Hindu')->firstOrFail();
        $student = Student::create([
            'institute_id' => ($inst ?? $this->inst)->id, 'course_id' => $this->course->id, 'batch_id' => ($batch ?? ($inst ? null : $this->batch))?->id,
            'first_name' => $name, 'last_name' => 'Student', 'dob' => '2005-01-01', 'gender' => 'female', 'qualification_id' => Qualification::first()->id,
            'email' => uniqid('sc') . '@mail.test', 'phone' => '+919800000011', 'emergency_contact' => '+919800000012',
            'religion_id' => $religion->id, 'category_id' => Category::where('religion_id', $religion->id)->first()->id,
            'joining_date' => '2025-06-01', 'onboarding_deadline' => '2025-07-01', 'status' => 'er_issued', 'er_number' => 'ER-SC-' . uniqid(),
        ]);
        app(PeriodService::class)->placeInPeriod($student, 1);
        app(FeeService::class)->generateDues($student->fresh());

        return $student->fresh();
    }

    protected function scholarship(array $override = [], array $periods = [1, 2]): Scholarship
    {
        $s = Scholarship::create(array_merge([
            'institute_id' => $this->inst->id, 'course_id' => $this->course->id, 'name' => 'Merit', 'code' => 'M' . substr(uniqid(), -6),
            'type' => 'percentage', 'value' => 25, 'applies_to' => 'period_fees', 'status' => true,
        ], $override));
        foreach ($periods as $p) {
            $s->periodRows()->create(['period_no' => $p]);
        }

        return $s->fresh('periodRows');
    }

    protected function due(Student $student, string $head): StudentDue
    {
        return StudentDue::where('student_id', $student->id)->where('fee_head', $head)->firstOrFail();
    }

    // ------------------------------------------------------------------ calculation

    public function test_percentage_and_fixed_discounts_with_stacking_cap_and_later_semesters()
    {
        $this->actingAs($this->admin);
        $student = $this->admitted();
        $this->assertSame(3, $student->dues()->count()); // one-time + Semester 1 lines

        // 25 % on semester fees only (one-time admission fee untouched)
        $merit = $this->scholarship();
        $preview = app(ScholarshipService::class)->preview($merit, $student);
        $this->assertEquals(12500.0, $preview['total']);
        $this->assertSame([2], $preview['later']);           // Semester 2 not charged yet
        $this->assertEquals(37500.0, $preview['periods'][0]['payable']);

        $result = app(ScholarshipService::class)->assign($merit, [$student->id]);
        $this->assertSame(1, $result['assigned']);
        $this->assertEquals(10000.0, (float) $this->due($student, 'Tuition S1')->discount);
        $this->assertEquals(30000.0, $this->due($student, 'Tuition S1')->balance);
        $this->assertEquals(2500.0, (float) $this->due($student, 'Lab S1')->discount);
        $this->assertEquals(0.0, (float) $this->due($student, 'Admission fee')->discount);

        // same scholarship twice → refused
        $again = app(ScholarshipService::class)->assign($merit, [$student->id]);
        $this->assertSame(0, $again['assigned']);
        $this->assertStringContainsString('Already', $again['skipped'][$student->id]);

        // fixed ₹5,000 per semester on top: split 80/20 over the two Semester 1 lines
        $sports = $this->scholarship(['name' => 'Sports', 'type' => 'fixed', 'value' => 5000], [1]);
        app(ScholarshipService::class)->assign($sports, [$student->id]);
        $this->assertEquals(14000.0, (float) $this->due($student, 'Tuition S1')->discount); // 10,000 + 4,000
        $this->assertEquals(3500.0, (float) $this->due($student, 'Lab S1')->discount);      // 2,500 + 1,000

        // stacking never goes past the fee
        $big = $this->scholarship(['name' => 'Full', 'type' => 'percentage', 'value' => 100], [1]);
        app(ScholarshipService::class)->assign($big, [$student->id]);
        $tuition = $this->due($student, 'Tuition S1');
        $this->assertEquals(40000.0, (float) $tuition->discount);
        $this->assertSame(0.0, $tuition->balance);
        $this->assertSame('cleared', $tuition->status);

        // Semester 2 charged later (promotion) → discounted automatically (25 % of 42,000)
        app(PeriodService::class)->placeInPeriod($student, 2);
        app(FeeService::class)->generateDues($student->fresh());
        $this->assertEquals(10500.0, (float) $this->due($student, 'Tuition S2')->discount);
        $this->assertEquals(31500.0, $this->due($student, 'Tuition S2')->balance);
    }

    public function test_paid_fees_are_not_discounted_and_revoke_keeps_paid_discounts()
    {
        $this->actingAs($this->admin);
        $student = $this->admitted();
        $gpay = PaymentGateway::where('code', 'cash')->firstOrFail();
        $fees = app(FeeService::class);

        // Lab fully paid, tuition ₹30,000 of ₹40,000 paid
        $fees->recordPayment($this->due($student, 'Lab S1'), $gpay, 10000, now()->toDateString(), null, null, null, null, true);
        $fees->recordPayment($this->due($student, 'Tuition S1'), $gpay, 30000, now()->toDateString(), null, null, null, null, true);

        $merit = $this->scholarship();
        app(ScholarshipService::class)->assign($merit, [$student->id]);
        $this->assertEquals(0.0, (float) $this->due($student, 'Lab S1')->discount);         // already paid: no refund
        $tuition = $this->due($student, 'Tuition S1');
        $this->assertEquals(10000.0, (float) $tuition->discount);                         // 25 % = 10,000 fits the open 10,000
        $this->assertSame('cleared', $tuition->status);

        // a second student without payments; revoke removes their discount, but the paid student keeps theirs
        $bina = $this->admitted('Bina');
        app(ScholarshipService::class)->assign($merit, [$bina->id]);
        $this->assertEquals(10000.0, (float) $this->due($bina, 'Tuition S1')->discount);

        foreach ([$student, $bina] as $s) {
            app(ScholarshipService::class)->revoke(StudentScholarship::where('student_id', $s->id)->firstOrFail(), 'Merit list corrected');
        }
        $this->assertEquals(10000.0, (float) $this->due($student, 'Tuition S1')->discount); // paid → kept
        $this->assertEquals(0.0, (float) $this->due($bina, 'Tuition S1')->discount);
        $this->assertEquals(40000.0, $this->due($bina, 'Tuition S1')->balance);
        $this->assertTrue(AuditTrail::where('action', 'scholarship_revoked')->exists());

        // a revoked scholarship can be given again
        $this->assertSame(1, app(ScholarshipService::class)->assign($merit, [$bina->id])['assigned']);
    }

    public function test_eligibility_batch_admission_and_institute_isolation()
    {
        $this->actingAs($this->admin);
        $inBatch = $this->admitted('Inbatch');
        $otherBatch = Batch::create(['institute_id' => $this->inst->id, 'course_id' => $this->course->id, 'name' => 'Dec', 'code' => 'SD-' . substr(uniqid(), -6), 'start_date' => '2025-12-01', 'status' => true]);
        $outBatch = $this->admitted('Outbatch', null, $otherBatch);
        $draft = $this->admitted('Drafty');
        $draft->update(['er_number' => null, 'status' => 'pending_approval']);
        $foreign = $this->admitted('Foreign', $this->other);

        $s = $this->scholarship(['batch_id' => $this->batch->id]);
        $ids = app(ScholarshipService::class)->eligibleQuery($s)->pluck('id')->all();
        $this->assertContains($inBatch->id, $ids);
        $this->assertNotContains($outBatch->id, $ids);
        $this->assertNotContains($draft->id, $ids);
        $this->assertNotContains($foreign->id, $ids);

        $r = app(ScholarshipService::class)->assign($s, [$outBatch->id, $draft->id, $foreign->id]);
        $this->assertSame(0, $r['assigned']);
        $this->assertCount(3, $r['skipped']);

        // an Institute Admin never sees another institute's scholarships
        $theirs = Scholarship::withoutGlobalScopes()->create(['institute_id' => $this->other->id, 'course_id' => $this->course->id, 'name' => 'Theirs', 'code' => 'THEIRS', 'type' => 'fixed', 'value' => 100, 'applies_to' => 'period_fees', 'status' => true]);
        Livewire::test(ScholarshipsComponent::class)->assertDontSee('Theirs');
        Livewire::test(AssignScholarshipComponent::class)->set('scholarship', $theirs->id)->assertDontSee('Foreign');
        $this->assertNull(Scholarship::find($theirs->id));
    }

    // ------------------------------------------------------------------ screens

    public function test_scholarship_screen_create_edit_reapply_status_delete()
    {
        $this->actingAs($this->admin);
        $student = $this->admitted();

        $page = Livewire::test(ScholarshipsComponent::class)->assertSee('No scholarships yet')->call('openModal')
            ->set('course_id', $this->course->id)->set('name', 'Merit')->set('code', 'merit25');
        $page->call('save')->assertHasErrors(['value', 'periods']);
        $page->set('value', '120')->call('togglePeriod', 1)->call('save')->assertHasErrors(['value' => 'max']);
        $page->set('value', '25')->assertSee('student pays')->call('save')->assertHasNoErrors();

        $s = Scholarship::where('code', 'MERIT25')->firstOrFail();
        $this->assertSame([1], $s->periods);
        Livewire::test(ScholarshipsComponent::class)->assertSee('Merit')->assertSee('MERIT25')->assertSee('25');

        // duplicate code in the same institute
        Livewire::test(ScholarshipsComponent::class)->call('openModal')->set('course_id', $this->course->id)->set('name', 'X')->set('code', 'MERIT25')
            ->set('value', '10')->call('togglePeriod', 1)->call('save')->assertHasErrors(['code' => 'unique']);

        // edit with an assignment → unpaid fees follow (25 % → 50 %)
        app(ScholarshipService::class)->assign($s, [$student->id]);
        Livewire::test(ScholarshipsComponent::class)->call('edit', $s->id)->set('value', '50')->call('save')->assertHasNoErrors()
            ->assertDispatchedBrowserEvent('show-toast', fn ($n, $d) => str_contains($d['message'], 'recalculated for 1 student'));
        $this->assertEquals(20000.0, (float) $this->due($student, 'Tuition S1')->discount);

        // in use → cannot be deleted; can be deactivated
        Livewire::test(ScholarshipsComponent::class)->call('confirmDelete', $s->id)->call('delete')
            ->assertDispatchedBrowserEvent('show-toast', fn ($n, $d) => str_contains($d['message'], 'cannot be deleted'));
        Livewire::test(ScholarshipsComponent::class)->call('toggleStatus', $s->id);
        $this->assertFalse($s->fresh()->status);
        $this->assertStringContainsString('inactive', app(ScholarshipService::class)->problem($s->fresh(), $this->admitted('Late')));

        // Accounts: view only
        $this->actingAs($this->makeUser('accounts', $this->inst));
        Livewire::test(ScholarshipsComponent::class)->assertSee('Merit')->assertDontSee('New scholarship')->call('openModal')->assertForbidden();
        $this->get(route('admin.scholarships.assign'))->assertForbidden();
    }

    public function test_assign_page_lists_students_first_then_scholarship_preview_select_and_assign()
    {
        $this->actingAs($this->admin);
        $a = $this->admitted('Anu');
        $b = $this->admitted('Binu');
        $s = $this->scholarship();

        // default: admitted students with their fees, no scholarship chosen yet
        $page = Livewire::test(AssignScholarshipComponent::class)
            ->assertSee('Anu')->assertSee('Binu')->assertSee('Choose a scholarship')->assertSee($s->name)
            ->assertSee('Total fees')->assertSee('Payable now')->assertSee('65,000')   // 15,000 + 40,000 + 10,000 owed today
            ->call('confirmAssign')->assertDispatchedBrowserEvent('show-toast', fn ($n, $d) => str_contains($d['message'], 'Choose the scholarship'));

        // choosing the scholarship: filters follow it, preview appears
        $page->set('scholarship', $s->id)->assertSet('course', (string) $this->course->id)->assertSet('show', 'eligible')
            ->assertSee('52,500 after assigning')                // 15,000 + 30,000 + 7,500
            ->assertSee('+ ₹10,500 later', false)                 // Semester 2 (not charged yet): 25 % of 42,000
            ->call('showFees', $a->id)->assertSet('feesFor', $a->id)->assertDispatchedBrowserEvent('open-fees-modal')
            ->assertSee('Complete fee structure')->assertSee('Semester 1 total')->assertSee('37,500')->assertSee('not charged yet')->assertSee('projected');

        $page->call('confirmAssign')->assertDispatchedBrowserEvent('show-toast'); // nothing selected
        $page->call('selectAllEligible')->assertSet('selected', [(string) $a->id, (string) $b->id])
            ->assertSee('Review selection')->assertSee('Payable after assigning')->assertSee('1,05,000')  // 2 × 52,500
            ->call('confirmAssign', $a->id)->assertSet('confirm.count', 1)->assertSet('assignOnly', (string) $a->id)   // one row
            ->call('confirmAssign')->assertSet('confirm.count', 2)->assertSet('confirm.total', 25000.0)->assertSet('confirm.later', 21000.0)
            ->set('remarks', 'Merit list 2026')->call('assign');
        $this->assertSame(2, StudentScholarship::where('scholarship_id', $s->id)->active()->count());
        $this->assertTrue(AuditTrail::where('action', 'scholarship_assigned')->exists());

        // without a scholarship again: "With a scholarship" shows their codes
        Livewire::test(AssignScholarshipComponent::class)->call('setShow', 'with')->assertSee('Anu')->assertSee($s->code)
            ->call('setShow', 'without')->assertDontSee('Anu');

        $assignment = StudentScholarship::where('student_id', $a->id)->firstOrFail();
        Livewire::test(AssignScholarshipComponent::class)->set('scholarship', $s->id)->call('setShow', 'assigned')->assertSee('Anu')->assertSee('given')
            ->call('openRevoke', $assignment->id)->call('revoke')->assertHasErrors('revokeReason')
            ->set('revokeReason', 'Did not meet attendance')->call('revoke')->assertHasNoErrors();
        $this->assertSame('revoked', $assignment->fresh()->status);
        $this->assertEquals(0.0, (float) $this->due($a, 'Tuition S1')->discount);
    }

    public function test_assign_page_filters_institute_course_batch_semester_scholarship()
    {
        $this->actingAs($this->super);
        $a = $this->admitted('Filtera');
        $b = $this->admitted('Filterb');
        app(PeriodService::class)->placeInPeriod($b, 2);
        app(FeeService::class)->generateDues($b->fresh());
        $c = $this->admitted('Otherinst', $this->other);
        $dec = Batch::create(['institute_id' => $this->inst->id, 'course_id' => $this->course->id, 'name' => 'Dec', 'code' => 'SF-' . substr(uniqid(), -6), 'start_date' => '2025-12-01', 'status' => true]);
        $d = $this->admitted('Decbatch', null, $dec);
        $merit = $this->scholarship(['name' => 'Merit Gold', 'code' => 'MGOLD', 'batch_id' => $this->batch->id]);
        $otherCourse = Course::create(['name' => 'Second course', 'code' => 'SC2-' . substr(uniqid(), -5), 'duration_months' => 12, 'total_semesters' => 2, 'status' => true]);
        InstituteCourse::create(['institute_id' => $this->inst->id, 'course_id' => $otherCourse->id, 'status' => true]);

        Livewire::test(AssignScholarshipComponent::class)
            ->assertSee('Filtera')->assertSee('Filterb')->assertSee('Otherinst')->assertSee('Decbatch')
            ->set('institute', $this->other->id)->assertSee('Otherinst')->assertDontSee('Filtera')
            ->set('institute', $this->inst->id)->set('course', $this->course->id)->assertSee('Filtera')->assertDontSee('Otherinst')
            ->call('pickBatch', $dec->id)->assertSet('batch', (string) $dec->id)->assertSee('Decbatch')->assertDontSee('Filtera')
            ->call('pickBatch', null)->call('pickSemester', 2)->assertSet('semester', '2')->assertSee('Filterb')->assertDontSee('Filtera')
            ->call('pickSemester', 0)->assertSet('semester', '')->set('search', 'filtera')->assertSee('Filtera')->assertDontSee('Filterb')
            ->call('clearFilters')->assertSet('search', '')->assertSee('Otherinst')
            // scholarship: only its institute / course / batch are eligible
            ->set('scholarship', $merit->id)->assertSet('batch', (string) $this->batch->id)
            ->assertSee('Filtera')->assertSee('Filterb')->assertDontSee('Decbatch')->assertDontSee('Otherinst')
            // switching to another course drops the scholarship (it belongs to one course)
            ->set('course', $otherCourse->id)->assertSet('scholarship', '')->assertSet('show', 'all')
            // the dropdowns' click handlers
            ->call('pickScholarship', $merit->id)->assertSet('scholarship', (string) $merit->id)->assertSet('show', 'eligible')
            ->call('pickCourse', $otherCourse->id)->assertSet('scholarship', '')
            ->call('pickInstitute', $this->other->id)->assertSet('course', '')->assertSee('Otherinst')->assertDontSee('Filtera')
            ->call('pickInstitute', null)->assertSet('institute', '');
    }

    public function test_assigned_student_discount_is_counted_once_on_the_assign_page()
    {
        $this->actingAs($this->admin);
        $a = $this->admitted('Oncea');
        $s = $this->scholarship(['value' => 10], [1]);                  // 10 % of Semester 1 (40,000 + 10,000)
        app(ScholarshipService::class)->assign($s, [$a->id]);
        $this->assertEquals(36000.0, $this->due($a, 'Tuition S1')->balance);  // saved: 40,000 − 4,000

        $t = app(ScholarshipService::class)->structure($s, $a->fresh());
        $sem1 = collect($t['groups'])->firstWhere('label', 'Semester 1');
        $tuition = collect($sem1['lines'])->firstWhere('fee', 'Tuition S1');
        $this->assertEquals(0.0, $tuition['existing']);   // its own discount is not an "existing" one
        $this->assertEquals(4000.0, $tuition['this']);
        $this->assertEquals(36000.0, $tuition['payable']); // not 32,000
        $this->assertEquals(15000 + 36000 + 9000, $t['totals']['payable_now']);

        // with another scholarship on top, only that one is "existing"
        $other = $this->scholarship(['name' => 'Sports', 'type' => 'fixed', 'value' => 1000], [1]);
        app(ScholarshipService::class)->assign($other, [$a->id]);
        $t = app(ScholarshipService::class)->structure($s, $a->fresh());
        $tuition = collect(collect($t['groups'])->firstWhere('label', 'Semester 1')['lines'])->firstWhere('fee', 'Tuition S1');
        $this->assertEquals(800.0, $tuition['existing']);  // Sports: 1,000 split 80/20
        $this->assertEquals(4000.0, $tuition['this']);
        $this->assertEquals(35200.0, $tuition['payable']);
        $this->assertEquals($this->due($a, 'Tuition S1')->balance, $tuition['payable']); // same as the saved balance
        $this->assertEquals($t['totals']['payable_now'], $t['totals']['balance_now']);       // assigned: preview = today

        // after a revoke the student owes the full fee again; the page shows that as "Payable now"
        app(ScholarshipService::class)->revoke(StudentScholarship::where('student_id', $a->id)->where('scholarship_id', $s->id)->firstOrFail(), 'Committee decision');
        $this->assertEquals(40000.0 - 800, $this->due($a, 'Tuition S1')->balance);           // only the Sports discount is left
        $t = app(ScholarshipService::class)->structure($s, $a->fresh());
        $this->assertEquals(15000 + 39200 + 9800, $t['totals']['balance_now']);               // owed today
        $this->assertEquals(15000 + 35200 + 8800, $t['totals']['payable_now']);               // if assigned again
        Livewire::test(AssignScholarshipComponent::class)->set('scholarship', $s->id)
            ->assertSee('Payable now')->assertSee('64,000')->assertSee('59,000 after assigning');
    }

    public function test_revoke_from_the_student_list_and_fee_popup()
    {
        $this->actingAs($this->admin);
        $a = $this->admitted('Revokea');
        $s = $this->scholarship();
        app(ScholarshipService::class)->assign($s, [$a->id]);

        Livewire::test(AssignScholarshipComponent::class)->set('scholarship', $s->id)->call('setShow', 'assigned')
            ->assertSee('Revoke')
            ->call('showFees', $a->id)->assertSee('Revoke from Revokea')
            ->call('openRevokeFor', $a->id)->assertDispatchedBrowserEvent('open-revoke-modal')
            ->assertSet('revokeInfo.removed', 12500.0)->assertSee('Removed from unpaid fees')
            ->set('revokeReason', 'Attendance below 75 %')->call('revoke')->assertHasNoErrors();
        $this->assertSame('revoked', StudentScholarship::where('student_id', $a->id)->value('status'));
        $this->assertEquals(0.0, (float) $this->due($a, 'Tuition S1')->discount);

        // Assigned / Revoked tabs; assign again from Revoked
        Livewire::test(AssignScholarshipComponent::class)->set('scholarship', $s->id)
            ->call('setShow', 'assigned')->assertSee('Nobody has this scholarship yet')
            ->call('setShow', 'revoked')->assertSet('show', 'revoked')->assertSee('Revokea')->assertSee('Reason: Attendance below 75 %')->assertSee('Assign again')
            ->call('confirmAssign', $a->id)->assertSet('confirm.count', 1)->call('assign');
        $this->assertSame('active', StudentScholarship::where('student_id', $a->id)->value('status'));
        $this->assertEquals(10000.0, (float) $this->due($a, 'Tuition S1')->discount);
        Livewire::test(AssignScholarshipComponent::class)->set('scholarship', $s->id)->call('setShow', 'revoked')->assertSee('No revoked assignments');

        // links from the Scholarships page
        $this->get(route('admin.scholarships.assign', ['scholarship' => $s->id, 'tab' => 'assigned']))->assertOk()->assertSee('Revokea');
    }

    public function test_student_fees_page_and_portal_show_the_discount()
    {
        $this->actingAs($this->admin);
        $student = $this->admitted();
        $s = $this->scholarship(['name' => 'Merit Award', 'code' => 'MERITAW']);
        app(ScholarshipService::class)->assign($s, [$student->id]);

        $assignment = StudentScholarship::where('student_id', $student->id)->firstOrFail();
        Livewire::test(StudentFeesComponent::class, ['student' => $student->id])
            ->assertSee('Merit Award')->assertSee('MERITAW')->assertSee('scholarship')->assertSee('30,000')
            ->call('openScholarshipRevoke', $assignment->id)->set('revokeReason', 'Withdrawn by committee')->call('revokeScholarship')->assertHasNoErrors();
        $this->assertSame('revoked', $assignment->fresh()->status);

        // outstanding on the dashboard uses the net amount
        app(ScholarshipService::class)->assign($s, [$student->id]);
        $this->assertEquals(15000 + 30000 + 7500, $student->fresh()->outstandingAmount());

        // portal: the student sees the scholarship line (payment page opens once a payment exists)
        app(FeeService::class)->recordPayment($this->due($student, 'Admission fee'), PaymentGateway::where('code', 'cash')->firstOrFail(), 15000, now()->toDateString(), null, null, null, null, true);
        $user = User::create(['name' => 'Asha', 'email' => uniqid('pt') . '@mail.test', 'password' => bcrypt('secret123'), 'institute_id' => $this->inst->id, 'status' => true]);
        $user->assignRole('student');
        $student->update(['user_id' => $user->id]);
        $this->actingAs($user);
        Livewire::test(\App\Http\Livewire\Portal\PaymentPage::class)->assertSee('Scholarship − ₹10,000', false);
    }
}
