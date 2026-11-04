<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Institutes\AddonProgramsComponent;
use App\Http\Livewire\Admin\Students\StudentOnboardingComponent;
use App\Http\Livewire\Portal\DetailsPage;
use App\Models\Admin\AddonProgram;
use App\Models\Admin\AuditTrail;
use App\Models\Admin\Category;
use App\Models\Admin\Country;
use App\Models\Admin\Course;
use App\Models\Admin\CourseFee;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteCourse;
use App\Models\Admin\PaymentGateway;
use App\Models\Admin\Qualification;
use App\Models\Admin\Religion;
use App\Models\Admin\State;
use App\Models\Admin\Student;
use App\Models\Admin\StudentAddonProgram;
use App\Models\Admin\StudentDue;
use App\Models\User;
use App\Services\AddonProgramService;
use App\Services\FeeService;
use Database\Seeders\admin\MasterDataSeeder;
use Database\Seeders\admin\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module 2 step 2.10 — add-on programs (optional extras per institute course).
 * Runs inside a transaction on the *_testing database (rolled back).
 */
class Module2AddonProgramsTest extends TestCase
{
    use DatabaseTransactions;

    protected Institute $inst;
    protected Institute $other;
    protected Course $course;
    protected Course $course2;
    protected AddonProgram $english;  // paid
    protected AddonProgram $softSkills; // free
    protected AddonProgram $drone;    // paid, 1 seat
    protected Religion $religion;
    protected Country $country;
    protected State $state;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
        $this->seed(MasterDataSeeder::class);

        $this->inst = $this->institute('ADD');
        $this->other = $this->institute('OTH');
        $this->course = Course::create(['name' => 'AME B1.1', 'code' => 'AB1-' . uniqid(), 'duration_months' => 48, 'total_semesters' => 8, 'status' => true]);
        $this->course2 = Course::create(['name' => 'Cabin Crew', 'code' => 'ACC-' . uniqid(), 'duration_months' => 12, 'total_semesters' => 2, 'status' => true]);
        foreach ([$this->course, $this->course2] as $c) {
            InstituteCourse::create(['institute_id' => $this->inst->id, 'course_id' => $c->id, 'status' => true]);
        }
        InstituteCourse::create(['institute_id' => $this->other->id, 'course_id' => $this->course->id, 'status' => true]);
        CourseFee::create(['institute_id' => $this->inst->id, 'course_id' => $this->course->id, 'fee_head' => 'Admission fee', 'amount' => 15000, 'due_days' => 0, 'status' => true]);

        $this->english = $this->addon('Aviation English', 12000, ['fee_due_days' => 15]);
        $this->softSkills = $this->addon('Soft skills', null);
        $this->drone = $this->addon('Drone pilot', 5000, ['capacity' => 1]);

        $this->religion = Religion::where('name', 'Hindu')->firstOrFail();
        $this->country = Country::firstOrCreate(['code' => 'TST'], ['name' => 'Testland', 'status' => true]);
        $this->state = State::firstOrCreate(['country_id' => $this->country->id, 'name' => 'Test State'], ['status' => true]);
    }

    protected function institute(string $prefix): Institute
    {
        return Institute::create([
            'name' => "{$prefix} Aviation " . uniqid(), 'established_year' => 2005, 'code' => Institute::generateCode($prefix, 2005),
            'email' => uniqid() . '@inst.test', 'phone' => (string) random_int(1000000000, 9999999999), 'status' => true,
        ]);
    }

    protected function addon(string $name, $fee, array $extra = [], ?Course $course = null, ?Institute $inst = null): AddonProgram
    {
        return AddonProgram::create(array_merge([
            'institute_id' => ($inst ?? $this->inst)->id, 'course_id' => ($course ?? $this->course)->id, 'name' => $name,
            'description' => "{$name} — short description", 'fee_amount' => $fee, 'status' => true,
        ], $extra));
    }

    protected function makeUser(string $role, ?Institute $institute = null): User
    {
        $user = User::create(['name' => $role, 'email' => uniqid($role) . '@user.test', 'password' => bcrypt('password'), 'institute_id' => $institute?->id, 'status' => true]);
        $user->assignRole($role);

        return $user;
    }

    protected function registration(array $override = [])
    {
        Auth::shouldUse('student');
        $component = Livewire::test(DetailsPage::class);
        foreach (array_merge([
            'email' => uniqid('addon') . '@mail.test', 'phone' => '+9198' . random_int(10000000, 99999999),
            'password' => 'secret123', 'password_confirmation' => 'secret123',
            'institute_id' => $this->inst->id, 'course_id' => $this->course->id, 'joining_date' => now()->toDateString(),
            'first_name' => 'Ananya', 'last_name' => 'Menon', 'dob' => '2008-03-14', 'gender' => 'female',
            'qualification_id' => Qualification::first()->id, 'emergency_contact' => '+919447055210',
            'religion_id' => $this->religion->id, 'category_id' => Category::where('religion_id', $this->religion->id)->first()->id,
            'address' => 'TC 14/221', 'country_id' => $this->country->id, 'state_id' => $this->state->id,
            'city' => 'Kochi', 'pincode' => '682001', 'parent_name' => 'Suresh Menon', 'parent_phone' => '+919447055211',
        ], $override) as $field => $value) {
            $component->set($field, $value);
        }

        return $component;
    }

    protected function studentWith(array $addonIds): Student
    {
        $this->registration()->set('addon_ids', $addonIds)->call('save')->assertHasNoErrors();

        return Auth::user()->student->fresh();
    }

    // ------------------------------------------------------------------ admin screen

    public function test_super_admin_adds_edits_and_limits_add_ons()
    {
        $this->be($this->makeUser('super-admin'));

        Livewire::test(AddonProgramsComponent::class)
            ->call('create', $this->course->id, $this->inst->id)
            ->set('name', 'Type rating prep')->set('code', 'trp')->set('fee_amount', '2500')->set('capacity', '20')
            ->call('save')->assertHasNoErrors();

        $program = AddonProgram::where('name', 'Type rating prep')->firstOrFail();
        $this->assertSame('TRP', $program->code);
        $this->assertEquals(2500, (float) $program->fee_amount);
        $this->assertSame(20, $program->capacity);

        // duplicate name on the same course is refused
        Livewire::test(AddonProgramsComponent::class)
            ->call('create', $this->course->id, $this->inst->id)->set('name', 'Type rating prep')->call('save')->assertHasErrors('name');

        // choice limit per institute course
        $ic = InstituteCourse::where('institute_id', $this->inst->id)->where('course_id', $this->course->id)->first();
        Livewire::test(AddonProgramsComponent::class)->call('setMax', $ic->id, '2');
        $this->assertSame(2, $ic->fresh()->max_addon_programs);
        Livewire::test(AddonProgramsComponent::class)->call('setMax', $ic->id, '');
        $this->assertNull($ic->fresh()->max_addon_programs);

        Livewire::test(AddonProgramsComponent::class)->call('toggleStatus', $program->id);
        $this->assertFalse($program->fresh()->status);
        $this->assertTrue(AuditTrail::where('module', 'addon_programs')->exists());
    }

    public function test_institute_admin_sees_only_own_institute_and_accounts_is_refused()
    {
        $foreign = $this->addon('Foreign add-on', 100, [], $this->course, $this->other);
        $admin = $this->makeUser('institute-admin', $this->inst);
        $this->be($admin);

        $this->get(route('admin.addon-programs'))->assertOk()->assertSee('Aviation English')->assertDontSee('Foreign add-on');

        // cannot open another institute's add-on
        $threw = false;
        try {
            Livewire::test(AddonProgramsComponent::class)->call('edit', $foreign->id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $threw = true;
        }
        $this->assertTrue($threw);

        // a new add-on is always on the own institute, whatever the browser sends
        Livewire::test(AddonProgramsComponent::class)->set('instituteId', $this->other->id)
            ->call('create', $this->course->id)->set('name', 'Own add-on')->call('save')->assertHasNoErrors();
        $this->assertSame($this->inst->id, AddonProgram::where('name', 'Own add-on')->value('institute_id'));

        $this->be($this->makeUser('accounts', $this->inst));
        $this->get(route('admin.addon-programs'))->assertForbidden();
    }

    public function test_add_on_chosen_by_students_cannot_be_deleted()
    {
        $student = $this->studentWith([$this->softSkills->id]);
        $this->be($this->makeUser('super-admin'));

        Livewire::test(AddonProgramsComponent::class)->call('confirmDelete', $this->softSkills->id)->call('delete');
        $this->assertNotNull($this->softSkills->fresh(), 'chosen add-on stays');

        Livewire::test(AddonProgramsComponent::class)->call('confirmDelete', $this->drone->id)->call('delete');
        $this->assertSoftDeleted('addon_programs', ['id' => $this->drone->id]);
        $this->assertSame(1, $student->addonChoices()->count());
    }

    // ------------------------------------------------------------------ portal

    public function test_portal_shows_only_active_add_ons_of_the_chosen_course()
    {
        $this->addon('Hidden one', 100, ['status' => false]);
        $this->addon('Cabin extra', 100, [], $this->course2);

        $page = $this->registration();
        $page->assertSee('Add-on programs')->assertSee('Aviation English')->assertSee('Soft skills')
            ->assertDontSee('Hidden one')->assertDontSee('Cabin extra');

        // changing the course clears the choices
        $page->call('toggleAddon', $this->english->id)->assertSet('addon_ids', [$this->english->id]);
        $page->set('course_id', $this->course2->id)->assertSet('addon_ids', [])->assertSee('Cabin extra');
    }

    public function test_registration_saves_choices_and_paid_add_on_adds_a_due()
    {
        $student = $this->studentWith([$this->english->id, $this->softSkills->id]);

        $this->assertEqualsCanonicalizing([$this->english->id, $this->softSkills->id], app(AddonProgramService::class)->chosenIds($student));
        $due = StudentDue::where('student_id', $student->id)->where('fee_head', 'Add-on: Aviation English')->firstOrFail();
        $this->assertEquals(12000, (float) $due->amount_due);
        $this->assertSame(now()->addDays(15)->toDateString(), $due->due_date->toDateString());
        $this->assertNull($due->course_fee_id);
        $this->assertSame($due->id, StudentAddonProgram::where('addon_program_id', $this->english->id)->value('student_due_id'));
        $this->assertNull(StudentAddonProgram::where('addon_program_id', $this->softSkills->id)->value('student_due_id'), 'free add-on adds no due');
        $this->assertSame(1, StudentDue::where('student_id', $student->id)->where('fee_head', 'Admission fee')->count(), 'course fees unchanged');
    }

    public function test_maximum_and_seats_are_enforced()
    {
        InstituteCourse::where('institute_id', $this->inst->id)->where('course_id', $this->course->id)->update(['max_addon_programs' => 1]);

        $page = $this->registration();
        $page->call('toggleAddon', $this->english->id)->call('toggleAddon', $this->softSkills->id)
            ->assertSet('addon_ids', [$this->english->id]); // second one refused at the maximum

        // a forged selection over the maximum is refused on save
        $page->set('addon_ids', [$this->english->id, $this->softSkills->id])->call('save')->assertHasErrors('addon_ids');
        $this->assertFalse(Auth::check() && Auth::user()->student, 'nothing registered');

        // seats: the drone add-on has 1 seat
        InstituteCourse::where('institute_id', $this->inst->id)->where('course_id', $this->course->id)->update(['max_addon_programs' => null]);
        $this->studentWith([$this->drone->id]);
        Auth::logout();

        $errors = app(AddonProgramService::class)->validate([$this->drone->id], $this->inst->id, $this->course->id);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('full', $errors[0]);
    }

    public function test_student_changes_choices_and_unpaid_due_is_removed()
    {
        $student = $this->studentWith([$this->english->id]);
        $this->assertTrue(StudentDue::where('student_id', $student->id)->where('fee_head', 'Add-on: Aviation English')->exists());

        $page = Livewire::test(DetailsPage::class)->assertSet('addon_ids', [$this->english->id]);
        $page->call('toggleAddon', $this->english->id)->call('toggleAddon', $this->softSkills->id)->call('save')->assertHasNoErrors();

        $this->assertSame([$this->softSkills->id], app(AddonProgramService::class)->chosenIds($student));
        $this->assertFalse(StudentDue::where('student_id', $student->id)->where('fee_head', 'Add-on: Aviation English')->exists());
        $this->assertTrue(AuditTrail::where('module', 'student_addon_programs')->exists());
    }

    public function test_course_change_clears_add_ons()
    {
        $student = $this->studentWith([$this->english->id]);

        Livewire::test(DetailsPage::class)->set('course_id', $this->course2->id)->assertSet('addon_ids', [])->call('save')->assertHasNoErrors();

        $this->assertSame([], app(AddonProgramService::class)->chosenIds($student->fresh()));
        $this->assertFalse(StudentDue::where('student_id', $student->id)->where('fee_head', 'Add-on: Aviation English')->exists());
    }

    public function test_choices_are_locked_after_a_confirmed_payment()
    {
        $student = $this->studentWith([$this->english->id]);
        $due = StudentDue::where('student_id', $student->id)->where('fee_head', 'Admission fee')->firstOrFail();
        $user = Auth::user();
        Auth::shouldUse('web');
        $this->be($this->makeUser('super-admin'));
        app(FeeService::class)->recordPayment($due, PaymentGateway::where('code', 'cash')->firstOrFail(), 15000, now(), 'R1', null, null, null, true);

        Auth::shouldUse('student');
        $this->be($user, 'student');
        Livewire::test(DetailsPage::class)->call('toggleAddon', $this->softSkills->id)->assertSet('addon_ids', [$this->english->id]);
        $this->assertSame([$this->english->id], app(AddonProgramService::class)->chosenIds($student));
    }

    // ------------------------------------------------------------------ admin onboarding

    public function test_admin_onboarding_saves_add_ons_with_audit()
    {
        $student = $this->studentWith([]);
        Auth::shouldUse('web');
        $this->be($this->makeUser('super-admin'));

        Livewire::test(StudentOnboardingComponent::class, ['student' => $student->id])
            ->assertSee('Add-on programs')
            ->set('addon_ids', [(string) $this->english->id])
            ->call('saveBasic')->assertHasNoErrors();

        $this->assertSame([$this->english->id], app(AddonProgramService::class)->chosenIds($student));
        $this->assertTrue(StudentDue::where('student_id', $student->id)->where('fee_head', 'Add-on: Aviation English')->exists());
        $this->assertStringContainsString('Office changed add-on programs', (string) (AuditTrail::where('module', 'student_addon_programs')->latest('id')->first()->meta['description'] ?? ''));
    }

    public function test_course_without_add_ons_shows_nothing()
    {
        $page = $this->registration(['course_id' => $this->course2->id]);
        $page->assertDontSee('Add-on programs');
        $page->call('save')->assertHasNoErrors();
        $this->assertSame(0, StudentAddonProgram::where('student_id', Auth::user()->student->id)->count());
    }
}
