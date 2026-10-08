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
