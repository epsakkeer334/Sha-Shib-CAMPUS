<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Institutes\AcademicPeriodsComponent;
use App\Http\Livewire\Admin\Institutes\BatchesComponent;
use App\Http\Livewire\Admin\Masters\AcademicYearsManager;
use App\Http\Livewire\Admin\Masters\CoursesManager;
use App\Http\Livewire\Admin\Onboarding\FeeStructureComponent;
use App\Http\Livewire\Admin\Students\StudentsComponent;
use App\Models\Admin\AcademicYear;
use App\Models\Admin\Batch;
use App\Models\Admin\Category;
use App\Models\Admin\Country;
use App\Models\Admin\Course;
use App\Models\Admin\CourseFee;
use App\Models\Admin\CoursePeriod;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteCourse;
use App\Models\Admin\Qualification;
use App\Models\Admin\Religion;
use App\Models\Admin\State;
use App\Models\Admin\Student;
use App\Models\Admin\StudentPeriod;
use App\Models\User;
use App\Services\FeeService;
use App\Services\PeriodService;
use Database\Seeders\admin\MasterDataSeeder;
use Database\Seeders\admin\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module 2B: academic years, course period structure, academic periods, student periods and
 * period-wise fees. Runs inside a transaction (rolled back).
 */
class Module2BPeriodsTest extends TestCase
{
    use DatabaseTransactions;

    protected Institute $inst;
    protected Institute $otherInst;
    protected Course $course;
    protected User $super;
    protected User $admin;
    protected User $otherAdmin;
    protected AcademicYear $ay26;
    protected AcademicYear $ay27;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(MasterDataSeeder::class);

        $this->ay26 = $this->year('2026-27', '2026-06-01', '2027-05-31');
        $this->ay27 = $this->year('2027-28', '2027-06-01', '2028-05-31');

        $this->inst = $this->makeInstitute('Sigma Aviation');
        $this->otherInst = $this->makeInstitute('Tau Aero');
        // 4 years, 8 semesters of 6 months
        $this->course = Course::create(['name' => 'AME B1.1', 'code' => 'S11-' . uniqid(), 'duration_months' => 48, 'total_semesters' => 8, 'status' => true]);
        foreach ([$this->inst, $this->otherInst] as $inst) {
            InstituteCourse::create(['institute_id' => $inst->id, 'course_id' => $this->course->id, 'status' => true]);
        }

        $this->super = $this->makeUser('super-admin');
        $this->admin = $this->makeUser('institute-admin', $this->inst);
        $this->otherAdmin = $this->makeUser('institute-admin', $this->otherInst);
    }

    protected function year(string $name, string $start, string $end): AcademicYear
    {
        return AcademicYear::firstOrCreate(['name' => $name], ['start_date' => $start, 'end_date' => $end, 'status' => true]);
    }

    protected function makeInstitute(string $name): Institute
    {
        return Institute::create([
            'name' => $name . ' ' . uniqid(), 'established_year' => 2004, 'code' => Institute::generateCode(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3), 2004),
            'email' => uniqid() . '@inst.test', 'phone' => (string) random_int(1000000000, 9999999999), 'status' => true,
        ]);
    }

    protected function makeUser(string $role, ?Institute $institute = null): User
    {
        $user = User::create(['name' => ucfirst($role), 'email' => uniqid($role) . '@user.test', 'password' => bcrypt('x'), 'institute_id' => $institute?->id, 'status' => true]);
        $user->assignRole($role);

        return $user;
    }

    protected function makeStudent(array $override = []): Student
    {
        $religion = Religion::where('name', 'Christian')->firstOrFail();
        $country = Country::firstOrCreate(['code' => 'TST'], ['name' => 'Testland', 'status' => true]);

        return Student::create($override + [
            'institute_id' => $this->inst->id, 'course_id' => $this->course->id, 'first_name' => 'Nikhil', 'last_name' => 'Varma',
            'dob' => '2008-03-14', 'gender' => 'male', 'qualification_id' => Qualification::first()->id,
            'email' => uniqid('nikhil') . '@mail.test', 'phone' => '+919847012399', 'emergency_contact' => '+919447055299',
            'religion_id' => $religion->id, 'category_id' => Category::where('religion_id', $religion->id)->first()->id,
            'joining_date' => '2026-10-01', 'onboarding_deadline' => '2026-10-31',
            'address' => 'TC 14/221', 'country_id' => $country->id,
            'state_id' => State::firstOrCreate(['country_id' => $country->id, 'name' => 'Test State'], ['status' => true])->id,
            'city' => 'Kochi', 'pincode' => '682001', 'parent_name' => 'Ravi Varma', 'parent_phone' => '+919447055299',
            'status' => 'pending_approval', 'submitted_at' => now(),
        ]);
    }

    protected function generateCalendar(string $start = '2026-07-01', ?Institute $inst = null): \Illuminate\Support\Collection
    {
        return app(PeriodService::class)->generate($inst ?? $this->inst, $this->course, $start);
    }

    protected function fee(array $attributes): CourseFee
    {
        return CourseFee::withoutGlobalScopes()->create($attributes + [
            'institute_id' => $this->inst->id, 'course_id' => $this->course->id, 'due_type' => CourseFee::DUE_JOINING, 'due_days' => 0,
            'sort_order' => 1, 'status' => true,
        ]);
    }

    // ------------------------------------------------------------------ master data

    public function test_academic_years_master_rejects_overlaps_and_courses_have_a_period_structure()
    {
        $this->actingAs($this->super);

        Livewire::test(AcademicYearsManager::class)
            ->call('openModal')
            ->set('form.name', '2027')->set('form.start_date', '2027-01-01')->set('form.end_date', '2027-12-31')
            ->call('save')
            ->assertHasErrors(['form.end_date', 'form.name' => 'regex']);

        // course: blank period length = duration ÷ number of periods; label defaults to Semester
        $code = 'CRT-' . strtoupper(substr(uniqid(), -5));
        Livewire::test(CoursesManager::class)
            ->call('openModal')
            ->set('form.name', 'Cabin crew certificate')->set('form.code', $code)
            ->set('form.duration_months', 6)->set('form.total_semesters', 2)
            ->call('save')->assertHasNoErrors();
        $course = Course::where('code', $code)->firstOrFail();
        $this->assertSame('Semester', $course->period_label);
        $this->assertSame(3, (int) $course->period_months);
        $this->assertSame('2 × Semester · 3 months', $course->period_summary);

        // a 3-month course with a single term
        $short = Course::create(['name' => 'Ramp safety', 'code' => 'RMP-' . uniqid(), 'duration_months' => 3, 'total_semesters' => 1, 'period_label' => 'Term', 'period_months' => 3, 'status' => true]);
        $this->assertSame('Term 1', $short->periodName(1));
        $this->assertSame(1, $short->yearOfStudy(1));
        $this->assertSame('Semester 3', $this->course->periodName(3));
        $this->assertSame(2, $this->course->yearOfStudy(3));
    }

    // ------------------------------------------------------------------ academic periods page

    public function test_institute_admin_generates_edits_and_removes_academic_periods()
    {
        $this->actingAs($this->admin);
        $this->get(route('admin.academic-periods'))->assertOk()->assertSee('Academic Periods');

        $page = Livewire::test(AcademicPeriodsComponent::class)
            ->call('openGenerate', $this->course->id)
            ->set('startDate', '2026-07-01')
            ->assertSee('Will create 8 periods')
            ->call('generate')
            ->assertHasNoErrors()
            ->assertDispatchedBrowserEvent('close-periods-modal');

        $periods = CoursePeriod::where('course_id', $this->course->id)->orderBy('period_no')->get();
        $this->assertCount(8, $periods);
        $this->assertSame($this->inst->id, $periods->first()->institute_id);
        $this->assertSame($this->ay26->id, $periods->first()->intake_academic_year_id);
        $this->assertSame('2027-01-01', $periods[1]->start_date->toDateString());
        $this->assertSame('2027-06-30', $periods[1]->end_date->toDateString());
        $this->assertSame('Semester 3', $periods[2]->label);
        $this->assertSame(2, $periods[2]->year_of_study);
        $this->assertSame($this->ay27->id, $periods[2]->academic_year_id);

        // the same intake twice is refused
        $page->call('openGenerate', $this->course->id)->set('startDate', '2026-08-01')->call('generate')->assertHasErrors('startDate');
        $this->assertSame(8, CoursePeriod::where('course_id', $this->course->id)->count());

        // edit dates and status
        $page->call('editPeriod', $periods[0]->id)->set('periodEnd', '2026-12-15')->set('periodStatus', 'ongoing')->call('savePeriod')->assertHasNoErrors();
        $this->assertSame('2026-12-15', $periods[0]->fresh()->end_date->toDateString());
        $this->assertSame('ongoing', $periods[0]->fresh()->status);
        $page->call('editPeriod', $periods[0]->id)->set('periodEnd', '2026-06-01')->call('savePeriod')->assertHasErrors('periodEnd');

        // the other institute cannot see or touch them
        $this->actingAs($this->otherAdmin);
        Livewire::test(AcademicPeriodsComponent::class)->assertSee('No academic periods yet');

        // removed while no student is in them; refused once a student is
        $this->actingAs($this->admin);
        $student = $this->makeStudent(['er_number' => 'ER-T-' . uniqid(), 'status' => 'er_issued']);
        app(PeriodService::class)->startFirstPeriod($student);
        $page->call('removeCalendar', $periods[0]->id)->assertDispatchedBrowserEvent('show-toast', fn ($n, $d) => $d['type'] === 'warning');
        $this->assertSame(8, CoursePeriod::where('course_id', $this->course->id)->count());

        StudentPeriod::where('student_id', $student->id)->forceDelete();
        $page->call('removeCalendar', $periods[0]->id);
        $this->assertSame(0, CoursePeriod::where('course_id', $this->course->id)->count());
    }

    public function test_generate_needs_an_academic_year_and_batch_calendars_follow_the_batch()
    {
        $this->actingAs($this->super);

        Livewire::test(AcademicPeriodsComponent::class)
            ->call('openGenerate', $this->course->id, $this->inst->id)
            ->set('startDate', '1999-07-01')->call('generate')->assertHasErrors('startDate');

        // batch: start date fills in, the batch's students use the batch calendar
        $batch = Batch::create(['institute_id' => $this->inst->id, 'course_id' => $this->course->id, 'name' => 'Jan intake', 'code' => 'SIG-JAN-' . strtoupper(substr(uniqid(), -4)),
            'start_date' => '2027-01-10', 'status' => true]);
        Livewire::test(AcademicPeriodsComponent::class)
            ->call('openGenerate', $this->course->id, $this->inst->id)
            ->set('batchId', $batch->id)->assertSet('startDate', '2027-01-10')
            ->call('generate')->assertHasNoErrors();
        $this->generateCalendar('2026-07-01'); // intake calendar (no batch)

        $inBatch = $this->makeStudent(['batch_id' => $batch->id]);
        $noBatch = $this->makeStudent();
        $this->assertSame('2027-01-10', app(PeriodService::class)->periodFor($inBatch, 1)->start_date->toDateString());
        $this->assertSame('2026-07-01', app(PeriodService::class)->periodFor($noBatch, 1)->start_date->toDateString());
    }

    public function test_batch_intake_year_follows_the_start_date()
    {
        $this->actingAs($this->admin);
        Livewire::test(BatchesComponent::class)
            ->call('create', $this->course->id)
            ->set('name', 'July 2027')->set('code', 'SIG-JUL27-' . strtoupper(substr(uniqid(), -4)))
            ->set('start_date', '2027-07-05')
            ->assertSet('academicYearId', $this->ay27->id)
            ->call('save')->assertHasNoErrors();
        $this->assertSame($this->ay27->id, Batch::where('name', 'July 2027')->firstOrFail()->academic_year_id);
    }

    // ------------------------------------------------------------------ student periods & period fees

    public function test_er_starts_period_one_and_period_fees_follow_the_students_period()
    {
        $calendar = $this->generateCalendar('2026-07-01');
        $this->fee(['fee_head' => 'Admission fee', 'amount' => 15000, 'admission_fee' => true]);
        $this->fee(['fee_head' => 'Caution deposit', 'amount' => 5000, 'sort_order' => 2]);
        $this->fee(['fee_head' => 'Caution deposit', 'amount' => 6000, 'sort_order' => 2, 'academic_year_id' => $this->ay27->id]); // later intakes only
        $this->fee(['fee_head' => 'Tuition', 'amount' => 40000, 'period_no' => 1, 'due_type' => CourseFee::DUE_PERIOD_START, 'academic_year_id' => $this->ay26->id]);
        $this->fee(['fee_head' => 'Tuition', 'amount' => 42000, 'period_no' => 2, 'due_type' => CourseFee::DUE_PERIOD_START]);
        $fees = app(FeeService::class);

        // registration: the admission fee only
        $student = $this->makeStudent();
        $fees->generateDues($student);
        $this->assertSame(['Admission fee'], $student->dues()->pluck('fee_head')->all());
        $this->assertNull($student->fresh()->current_period_label);

        // ER issued: period 1 starts; one-time fees of the intake year + period 1 fees are added
        $student->update(['er_number' => 'ER-T-' . uniqid(), 'status' => 'er_issued']);
        $row = app(PeriodService::class)->startFirstPeriod($student->fresh());
        $this->assertSame(1, $row->period_no);
        $this->assertSame('Semester 1 · 2026-27', $student->fresh()->current_period_label);
        $fees->generateDues($student->fresh());

        $dues = $student->dues()->get()->keyBy(fn ($d) => $d->fee_head . '#' . $d->period_no);
        $this->assertEqualsCanonicalizing(['Admission fee#', 'Caution deposit#', 'Tuition#1'], $dues->keys()->all());
        $this->assertEquals(5000, (float) $dues['Caution deposit#']->amount_due);
        $this->assertSame('2026-07-01', $dues['Tuition#1']->due_date->toDateString()); // period start date
        $this->assertSame($this->ay26->id, $dues['Tuition#1']->academic_year_id);
        $this->assertSame('Semester 1', $dues['Tuition#1']->period_name);

        // moving to period 2 (Module 5 will do this): period 2 fees, due on its start date
        $row->update(['status' => 'completed', 'completed_on' => '2026-12-31']);
        StudentPeriod::create(['institute_id' => $student->institute_id, 'student_id' => $student->id, 'course_period_id' => $calendar[1]->id,
            'period_no' => 2, 'status' => 'current', 'started_on' => '2027-01-01']);
        $fees->generateDues($student->fresh());
        $sem2 = $student->dues()->where('period_no', 2)->firstOrFail();
        $this->assertEquals(42000, (float) $sem2->amount_due);
        $this->assertSame('2027-01-01', $sem2->due_date->toDateString());
        $this->assertSame(4, $student->dues()->count()); // nothing charged twice

        // students list: current period chip and period filter
        $this->actingAs($this->admin);
        Livewire::test(StudentsComponent::class)->set('course', $this->course->id)->set('period', 2)->assertSee($student->er_number)->assertSee('Semester 2 · 2026-27');
        Livewire::test(StudentsComponent::class)->set('course', $this->course->id)->set('period', 1)->assertDontSee($student->er_number);
    }

    public function test_a_new_period_fee_reaches_only_students_in_that_period()
    {
        $this->generateCalendar('2026-07-01');
        $inPeriod = $this->makeStudent(['er_number' => 'ER-T-' . uniqid(), 'status' => 'er_issued']);
        app(PeriodService::class)->startFirstPeriod($inPeriod);
        $applicant = $this->makeStudent();
        $this->fee(['fee_head' => 'Admission fee', 'amount' => 15000, 'admission_fee' => true]);

        $this->actingAs($this->admin);
        $page = Livewire::test(FeeStructureComponent::class)
            ->call('create', $this->course->id)
            ->set('period_no', 1)
            ->assertSet('due_type', CourseFee::DUE_PERIOD_START)
            ->set('admission_fee', true)
            ->set('fee_head', 'Lab fee')->set('amount', 3000)
            ->call('save')->assertHasNoErrors();

        $lab = CourseFee::where('fee_head', 'Lab fee')->firstOrFail();
        $this->assertSame(1, $lab->period_no);
        $this->assertFalse($lab->admission_fee); // a period fee is never the admission fee
        $this->assertSame(1, $inPeriod->dues()->where('course_fee_id', $lab->id)->count());
        $this->assertSame('2026-07-01', $inPeriod->dues()->where('course_fee_id', $lab->id)->first()->due_date->toDateString());
        $this->assertSame(0, $applicant->dues()->where('course_fee_id', $lab->id)->count());

        // same name: allowed for another period, refused in the same period and year
        $page->call('create', $this->course->id)->set('period_no', 2)->set('fee_head', 'Lab fee')->set('amount', 3200)->call('save')->assertHasNoErrors();
        $page->call('create', $this->course->id)->set('period_no', 2)->set('fee_head', 'Lab fee')->set('amount', 3300)->call('save')->assertHasErrors(['fee_head' => 'unique']);
        $page->call('create', $this->course->id)->set('period_no', 9)->set('fee_head', 'Extra')->set('amount', 10)->call('save')->assertHasErrors(['period_no' => 'max']);
    }

    public function test_fees_are_paid_in_order_and_the_next_semester_can_be_paid_in_advance()
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $calendar = $this->generateCalendar('2026-07-01');
        $this->fee(['fee_head' => 'Admission fee', 'amount' => 15000, 'admission_fee' => true]);
        $this->fee(['fee_head' => 'Caution deposit', 'amount' => 5000, 'due_days' => 30, 'sort_order' => 2]);
        $this->fee(['fee_head' => 'Tuition', 'amount' => 40000, 'period_no' => 1, 'due_type' => CourseFee::DUE_PERIOD_START]);
        $sem2 = $this->fee(['fee_head' => 'Tuition', 'amount' => 42000, 'period_no' => 2, 'due_type' => CourseFee::DUE_PERIOD_START, 'allow_advance' => true]);
        $exam2 = $this->fee(['fee_head' => 'Exam fee', 'amount' => 2000, 'period_no' => 2, 'due_type' => CourseFee::DUE_PERIOD_START, 'sort_order' => 2]);
        $sem3 = $this->fee(['fee_head' => 'Tuition', 'amount' => 44000, 'period_no' => 3, 'due_type' => CourseFee::DUE_PERIOD_START, 'allow_advance' => true]);
        $fees = app(FeeService::class);
        $cash = \App\Models\Admin\PaymentGateway::where('code', 'cash')->firstOrFail();
        $this->actingAs($this->admin);
        $pay = function ($due) use ($fees, $cash) {
            $payment = $fees->recordPayment($due->fresh(), $cash, (float) $due->fresh()->payableBalance(), now(), null, null, null, null);
            $fees->approvePayment($payment);
        };

        $studentUser = User::create(['name' => 'Nikhil', 'email' => uniqid('nikhil') . '@student.test', 'password' => bcrypt('x'), 'institute_id' => $this->inst->id, 'status' => true]);
        $studentUser->assignRole('student');
        $student = $this->makeStudent(['user_id' => $studentUser->id]);
        $fees->generateDues($student);

        // registration: every other fee is listed as upcoming; nothing can be paid in advance yet
        $upcoming = $fees->upcomingFees($student->fresh());
        $this->assertSame(['Caution deposit', 'Tuition', 'Tuition', 'Exam fee', 'Tuition'], $upcoming->pluck('fee.fee_head')->all());
        $this->assertSame('Charged with your ER number', $upcoming[1]['when']);
        $this->assertSame('2027-01-01', $upcoming[2]['expected']->toDateString());
        $this->assertFalse($upcoming->contains('advance', true));

        // ER issued: Semester 1 starts; fees are paid in due-date order
        $student->update(['er_number' => 'ER-T-' . uniqid(), 'status' => 'er_issued']);
        app(PeriodService::class)->startFirstPeriod($student->fresh());
        $fees->generateDues($student->fresh());
        $student = $student->fresh();
        $this->assertSame(['Tuition', 'Admission fee', 'Caution deposit'], $fees->openDuesInOrder($student)->pluck('fee_head')->all());
        $first = $fees->nextPayableDue($student);
        $this->assertSame('Tuition', $first->fee_head);

        // a payment waiting for verification that covers it opens the next fee
        $payment = $fees->recordPayment($first, $cash, 40000, now(), null, null, null, null);
        $this->assertSame('Admission fee', $fees->nextPayableDue($student)->fee_head);
        $fees->approvePayment($payment);
        $pay($fees->nextPayableDue($student));
        $pay($fees->nextPayableDue($student));
        $this->assertNull($fees->nextPayableDue($student));

        // everything charged is paid: the next semester's tuition can be paid in advance (exam fee does not allow it)
        $upcoming = $fees->upcomingFees($student->fresh());
        $this->assertSame(['Tuition', 'Exam fee', 'Tuition'], $upcoming->pluck('fee.fee_head')->all());
        $this->assertSame([true, false, false], $upcoming->pluck('advance')->all());
        $this->assertSame([true, false, true], $upcoming->pluck('advance_allowed')->all());
        try {
            $fees->chargeAdvance($student, $sem3);
            $this->fail('Semester 3 must not be payable before Semester 2');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Pay the fees before it first', $e->getMessage());
        }

        // the student pays it in advance on the portal
        \App\Models\Admin\InstitutePaymentGateway::create(['institute_id' => $this->inst->id, 'payment_gateway_id' => \App\Models\Admin\PaymentGateway::where('code', 'gpay')->value('id'),
            'upi_id' => 'sigma@okaxis', 'payee_name' => 'Sigma Aviation', 'status' => true]);
        $this->actingAs($studentUser);
        Livewire::test(\App\Http\Livewire\Portal\PaymentPage::class)
            ->assertSee('Pay in advance')->assertSee('Exam fee')->assertSee('Charged when Semester 2 starts')
            ->assertSet('due_id', null) // the advance form opens only on request
            ->call('payAdvance', $sem2->id)->assertSet('due_id', 'advance-' . $sem2->id)->assertSet('amount', 42000.0)
            ->set('due_id', 'advance-' . $sem3->id)->set('amount', 100)->set('utr', '627514903311')
            ->set('proof', \Illuminate\Http\UploadedFile::fake()->image('gpay.png'))->call('pay')->assertHasErrors('due_id')
            ->set('due_id', 'advance-' . $sem2->id)->set('amount', 42000)->call('pay')->assertHasNoErrors();
        $advanceDue = $student->dues()->where('course_fee_id', $sem2->id)->firstOrFail();
        $this->assertTrue($advanceDue->advance);
        $this->assertSame(2, $advanceDue->period_no);
        $this->assertSame('2027-01-01', $advanceDue->due_date->toDateString());

        // once confirmed, Semester 3 tuition opens for advance
        $this->actingAs($this->admin);
        $fees->approvePayment(\App\Models\Admin\StudentPayment::where('student_due_id', $advanceDue->id)->firstOrFail());
        $this->assertSame([false, true], $fees->upcomingFees($student->fresh())->pluck('advance')->all());

        // moving to Semester 2 charges only the exam fee — the tuition paid in advance is not charged again
        StudentPeriod::where('student_id', $student->id)->update(['status' => 'completed']);
        StudentPeriod::create(['institute_id' => $student->institute_id, 'student_id' => $student->id, 'course_period_id' => $calendar[1]->id, 'period_no' => 2, 'status' => 'current', 'started_on' => '2027-01-01']);
        $fees->generateDues($student->fresh());
        $this->assertSame(1, $student->dues()->where('course_fee_id', $sem2->id)->count());
        $this->assertSame(1, $student->dues()->where('course_fee_id', $exam2->id)->count());
        $this->assertSame('Exam fee', $fees->nextPayableDue($student->fresh())->fee_head);

        // the admin fee page lists paid, open and upcoming fees
        Livewire::test(\App\Http\Livewire\Admin\Students\StudentFeesComponent::class, ['student' => $student->id])
            ->assertSee('Next to pay')->assertSee('Paid in advance')->assertSee('Upcoming — not charged yet')->assertSee('Advance allowed');
    }

    public function test_pay_in_advance_clicked_by_mistake_can_be_cancelled()
    {
        $this->generateCalendar('2026-07-01');
        $sem2 = $this->fee(['fee_head' => 'Tuition', 'amount' => 42000, 'period_no' => 2, 'due_type' => CourseFee::DUE_PERIOD_START, 'allow_advance' => true]);
        $fees = app(FeeService::class);
        $cash = \App\Models\Admin\PaymentGateway::where('code', 'cash')->firstOrFail();
        $studentUser = User::create(['name' => 'Nikhil', 'email' => uniqid('nikhil') . '@student.test', 'password' => bcrypt('x'), 'institute_id' => $this->inst->id, 'status' => true]);
        $studentUser->assignRole('student');
        $student = $this->makeStudent(['user_id' => $studentUser->id, 'er_number' => 'ER-T-' . uniqid(), 'status' => 'er_issued']);
        app(PeriodService::class)->startFirstPeriod($student);

        // the admission fee is already paid (so nothing is open and the portal payment page is available)
        $this->actingAs($this->admin);
        $admission = $fees->addDue($student, 'Admission fee', 15000, '2026-10-01');
        $fees->approvePayment($fees->recordPayment($admission, $cash, 15000, now(), null, null, null, null));

        // office: "Pay in advance" adds the fee at once — "Cancel advance" removes it again
        $page = Livewire::test(\App\Http\Livewire\Admin\Students\StudentFeesComponent::class, ['student' => $student->id])
            ->call('ask', 'advance', $sem2->id)->assertDispatchedBrowserEvent('open-fee-modal')->assertSee('Pay in advance?');
        $this->assertSame(0, $student->dues()->where('course_fee_id', $sem2->id)->count()); // nothing until confirmed
        $page->call('confirmed');
        $due = $student->dues()->where('course_fee_id', $sem2->id)->firstOrFail();
        $page->assertSet('pay_due_id', $due->id)->assertSee('Cancel advance')
            ->call('ask', 'revoke', $due->id)->assertSee('Cancel advance?')->assertSee('listed as upcoming again')
            ->call('confirmed')->assertSet('pay_due_id', null)->assertSet('confirmAction', null);
        $this->assertSoftDeleted($due);
        $this->assertTrue(\App\Models\Admin\AuditTrail::where('action', 'revoke_advance')->where('reference_id', $due->id)->exists());
        $this->assertSame([true], $fees->upcomingFees($student->fresh())->pluck('advance')->all()); // upcoming again

        // a payment waiting for verification blocks cancelling; once Accounts rejects it, it can be cancelled
        $due = $fees->chargeAdvance($student->fresh(), $sem2);
        $payment = $fees->recordPayment($due, $cash, 42000, now(), null, null, null, null);
        Livewire::test(\App\Http\Livewire\Admin\Students\StudentFeesComponent::class, ['student' => $student->id])
            ->assertDontSee('Cancel advance')
            ->call('revokeAdvance', $due->id)->assertDispatchedBrowserEvent('show-toast', fn ($n, $d) => $d['type'] === 'danger');
        $this->assertNotSoftDeleted($due);
        $fees->rejectPayment($payment, 'Paid by mistake');

        // the student cancels it on the portal
        $this->actingAs($studentUser);
        Livewire::test(\App\Http\Livewire\Portal\PaymentPage::class)
            ->assertSee('Cancel advance')
            ->call('ask', 'revoke', $due->id)->assertSee('Cancel advance payment?')
            ->call('closeConfirm')->assertDontSee('Cancel advance payment?') // pop-up Cancel keeps the fee
            ->call('ask', 'revoke', $due->id)->call('confirmed')->assertDispatchedBrowserEvent('show-toast', fn ($n, $d) => $d['type'] === 'success');
        $this->assertSoftDeleted($due);

        // portal: opening the advance form by mistake and cancelling it charges nothing
        Livewire::test(\App\Http\Livewire\Portal\PaymentPage::class)
            ->call('ask', 'advance', $sem2->id)->assertSee('Pay in advance?')->assertSet('due_id', null)
            ->call('confirmed')->assertSet('due_id', 'advance-' . $sem2->id)->assertSee('Nothing is charged until you submit')
            ->call('cancelAdvance')->assertSet('due_id', null)->assertDontSee('Nothing is charged until you submit');
        $this->assertSame(0, $student->dues()->where('course_fee_id', $sem2->id)->count());

        // a fee that was not added as an advance cannot be removed this way
        $this->actingAs($this->admin);
        Livewire::test(\App\Http\Livewire\Admin\Students\StudentFeesComponent::class, ['student' => $student->id])
            ->call('ask', 'revoke', $admission->id)->call('confirmed')->assertDispatchedBrowserEvent('show-toast', fn ($n, $d) => $d['type'] === 'danger');
        $this->assertNotSoftDeleted($admission);
    }

    public function test_fee_structure_saves_the_advance_payment_switch_for_semester_fees_only()
    {
        $this->actingAs($this->admin);
        Livewire::test(FeeStructureComponent::class)
            ->call('create', $this->course->id)->set('period_no', 2)->set('allow_advance', true)
            ->set('fee_head', 'Tuition')->set('amount', 42000)->call('save')->assertHasNoErrors()
            ->call('create', $this->course->id)->set('allow_advance', true)
            ->set('fee_head', 'Library')->set('amount', 1000)->call('save')->assertHasNoErrors()
            ->assertSee('Advance');
        $this->assertTrue(CourseFee::where('fee_head', 'Tuition')->firstOrFail()->allow_advance);
        $this->assertFalse(CourseFee::where('fee_head', 'Library')->firstOrFail()->allow_advance); // one-time fee: never advance
    }

    public function test_fee_structure_year_filter_grouping_and_copy_from_previous_year()
    {
        $this->fee(['fee_head' => 'Admission fee', 'amount' => 15000, 'admission_fee' => true]);
        $this->fee(['fee_head' => 'Tuition 26', 'amount' => 40000, 'period_no' => 1, 'due_type' => CourseFee::DUE_PERIOD_START, 'academic_year_id' => $this->ay26->id]);
        $this->fee(['fee_head' => 'Exam fee', 'amount' => 2000, 'period_no' => 2, 'due_type' => CourseFee::DUE_FIXED, 'due_date' => '2027-03-01', 'academic_year_id' => $this->ay26->id]);

        $this->actingAs($this->admin);
        $page = Livewire::test(FeeStructureComponent::class)
            ->assertSee('One-time fees')->assertSee('Semester 1')->assertSee('Semester 2')->assertSee('On period start')
            ->set('filterYear', $this->ay27->id)
            ->assertSee('Admission fee')->assertDontSee('Tuition 26')
            ->assertSee('Copy from 2026-27');

        $page->call('copyPreviousYear');
        $copied = CourseFee::where('academic_year_id', $this->ay27->id)->orderBy('period_no')->get();
        $this->assertSame(['Tuition 26', 'Exam fee'], $copied->pluck('fee_head')->all());
        $this->assertSame('2028-03-01', $copied[1]->due_date->toDateString());

        // a second copy adds nothing
        $page->call('copyPreviousYear')->assertDispatchedBrowserEvent('show-toast', fn ($n, $d) => str_contains($d['message'], 'Nothing to copy'));
        $this->assertSame(2, CourseFee::where('academic_year_id', $this->ay27->id)->count());
    }
}
