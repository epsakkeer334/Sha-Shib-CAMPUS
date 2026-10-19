<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Masters\CourseEligibilityManager;
use App\Http\Livewire\Admin\Masters\HigherSecondaryBoardsManager;
use App\Http\Livewire\Admin\Masters\QualifyingSubjectGroupsManager;
use App\Http\Livewire\Admin\Masters\QualifyingSubjectsManager;
use App\Http\Livewire\Admin\Onboarding\DocumentVerificationComponent;
use App\Http\Livewire\Admin\Students\StudentOnboardingComponent;
use App\Http\Livewire\Portal\AcademicPage;
use App\Http\Livewire\Portal\DocumentsPage;
use App\Models\Admin\AuditTrail;
use App\Models\Admin\Category;
use App\Models\Admin\Course;
use App\Models\Admin\CourseEligibilityCriterion;
use App\Models\Admin\HigherSecondaryBoard;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteCourse;
use App\Models\Admin\MatriculationBoard;
use App\Models\Admin\Qualification;
use App\Models\Admin\QualifyingSubject;
use App\Models\Admin\QualifyingSubjectGroup;
use App\Models\Admin\Religion;
use App\Models\Admin\Student;
use App\Models\Admin\StudentAcademicDetail;
use App\Models\User;
use App\Services\EligibilityService;
use Database\Seeders\admin\MasterDataSeeder;
use Database\Seeders\admin\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module 2 step 2.7 — course eligibility criteria (Higher Secondary / 12th).
 * Runs inside a transaction on the *_testing database (rolled back).
 */
class Module2EligibilityTest extends TestCase
{
    use DatabaseTransactions;

    protected User $super;
    protected Institute $inst;
    protected Course $course;
    protected Course $openCourse; // no criteria
    protected HigherSecondaryBoard $cbse;
    protected HigherSecondaryBoard $state;
    protected QualifyingSubjectGroup $pcm;
    protected QualifyingSubjectGroup $pcb;
    protected QualifyingSubjectGroup $commerce;
    protected array $sub = [];
    protected \App\Models\Admin\Country $country;
    protected \App\Models\Admin\State $stateRow;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
        $this->seed(MasterDataSeeder::class);

        $this->super = $this->makeUser('super-admin');
        $this->inst = Institute::create([
            'name' => 'Elig Aviation ' . uniqid(), 'established_year' => 2004, 'code' => Institute::generateCode('ELG', 2004),
            'email' => uniqid() . '@inst.test', 'phone' => (string) random_int(1000000000, 9999999999), 'status' => true,
        ]);
        $this->course = Course::create(['name' => 'AME B1.1', 'code' => 'EB1-' . uniqid(), 'duration_months' => 48, 'total_semesters' => 8, 'status' => true]);
        $this->openCourse = Course::create(['name' => 'Cabin Crew', 'code' => 'ECC-' . uniqid(), 'duration_months' => 12, 'total_semesters' => 2, 'status' => true]);
        foreach ([$this->course, $this->openCourse] as $c) {
            InstituteCourse::create(['institute_id' => $this->inst->id, 'course_id' => $c->id, 'status' => true]);
        }

        $this->country = \App\Models\Admin\Country::create(['name' => 'Eligland ' . uniqid(), 'code' => substr(uniqid(), -6), 'status' => true]);
        $this->stateRow = \App\Models\Admin\State::create(['country_id' => $this->country->id, 'name' => 'Elig State', 'status' => true]);

        // Seeded by the migration
        $this->pcm = QualifyingSubjectGroup::where('code', 'PCM')->firstOrFail();
        $this->pcb = QualifyingSubjectGroup::where('code', 'PCB')->firstOrFail();
        $this->commerce = QualifyingSubjectGroup::where('code', 'COMMERCE')->firstOrFail();
        $this->sub = QualifyingSubject::pluck('id', 'code')->all();

        $this->cbse = HigherSecondaryBoard::firstOrCreate(['name' => 'CBSE'], ['status' => true]);
        $this->cbse->update(['cgpa_factor' => 9.5]);
        $this->state = HigherSecondaryBoard::firstOrCreate(['name' => 'State Boards'], ['status' => true]);
        $this->state->update(['cgpa_factor' => 10]);

        // AME B1.1: PCM (any board) Physics ≥ 50, Chemistry ≥ 45, Maths ≥ 50, average ≥ 50; PCB (any board) all ≥ 60
        $this->criterion($this->course, $this->pcm, null, ['PHY' => 50, 'CHE' => 45, 'MAT' => 50], 50);
        $this->criterion($this->course, $this->pcb, null, ['PHY' => 60, 'CHE' => 60, 'BIO' => 60]);
    }

    protected function makeUser(string $role, ?Institute $institute = null): User
    {
        $user = User::create(['name' => $role, 'email' => uniqid($role) . '@user.test', 'password' => bcrypt('password'), 'institute_id' => $institute?->id, 'status' => true]);
        $user->assignRole($role);

        return $user;
    }

    protected function criterion(Course $course, QualifyingSubjectGroup $group, ?HigherSecondaryBoard $board, array $mins, $aggregate = null): CourseEligibilityCriterion
    {
        $c = CourseEligibilityCriterion::create([
            'course_id' => $course->id, 'higher_secondary_board_id' => $board?->id, 'qualifying_subject_group_id' => $group->id,
            'min_aggregate' => $aggregate, 'status' => true,
        ]);
        foreach ($mins as $code => $min) {
            $c->subjects()->create(['qualifying_subject_id' => $this->sub[$code], 'min_percent' => $min]);
        }

        return $c;
    }

    protected function check(array $marks, string $group = 'PCM', $board = null, string $type = 'percentage', ?Course $course = null): array
    {
        return app(EligibilityService::class)->check([
            'course_id' => ($course ?? $this->course)->id, 'board_id' => ($board ?? $this->cbse)->id, 'group_code' => $group, 'mark_type' => $type,
            'marks' => collect($marks)->mapWithKeys(fn ($m, $code) => [$this->sub[$code] => $m])->all(),
        ]);
    }

    protected function makeStudent(array $override = []): Student
    {
        $religion = Religion::where('name', 'Hindu')->firstOrFail();

        return Student::create(array_merge([
            'institute_id' => $this->inst->id, 'course_id' => $this->course->id, 'first_name' => 'Elig', 'last_name' => 'Student',
            'dob' => '2006-01-01', 'gender' => 'female', 'qualification_id' => Qualification::first()->id,
            'email' => uniqid('el') . '@mail.test', 'phone' => '9876543210', 'emergency_contact' => '9876500001',
            'religion_id' => $religion->id, 'category_id' => Category::where('religion_id', $religion->id)->first()->id,
            'address' => '1 Test Road', 'country_id' => $this->country->id, 'state_id' => $this->stateRow->id, 'city' => 'Kochi', 'pincode' => '682001',
            'parent_name' => 'Parent', 'parent_phone' => '9876500002',
            'joining_date' => now()->toDateString(), 'onboarding_deadline' => now()->addDays(30)->toDateString(), 'status' => 'draft',
        ], $override));
    }

    protected function academicTab(Student $student, array $marks, string $group = 'PCM', string $type = 'percentage')
    {
        $page = Livewire::test(StudentOnboardingComponent::class, ['student' => $student->id])->call('goTo', 'academic')
            ->set('matriculation_board_id', MatriculationBoard::first()->id)->set('matriculation_mark_type', 'percentage')->set('matriculation_mark', '88')
            ->set('higher_secondary_board_id', $this->cbse->id)->set('higher_secondary_subject', $group)
            ->set('higher_secondary_mark_type', $type)->set('higher_secondary_mark', $type === 'cgpa' ? '8' : '70');
        foreach ($marks as $code => $mark) {
            $page->set("subject_marks.{$this->sub[$code]}", $mark);
        }

        return $page;
    }

    // ------------------------------------------------------------------ masters

    public function test_migration_seeds_the_12th_groups_and_masters_are_super_admin_only()
    {
        $this->assertSame(['Physics', 'Chemistry', 'Mathematics'], $this->pcm->subjects->pluck('name')->all());
        $this->assertSame(['Accountancy', 'Business Studies'], $this->commerce->subjects->pluck('name')->all());

        foreach (['admin.masters.qualifying-subjects', 'admin.masters.qualifying-subject-groups', 'admin.masters.course-eligibility'] as $route) {
            $this->actingAs($this->makeUser('institute-admin', $this->inst))->get(route($route))->assertForbidden();
            $this->actingAs($this->super)->get(route($route))->assertOk();
        }

        $this->actingAs($this->super);
        Livewire::test(QualifyingSubjectsManager::class)
            ->set('form.name', 'Computer Applications')->set('form.code', 'cap')->call('save')->assertHasNoErrors();
        $cap = QualifyingSubject::where('code', 'CAP')->firstOrFail(); // code upper-cased

        Livewire::test(QualifyingSubjectGroupsManager::class)
            ->set('form.code', 'pcmc')->set('form.name', 'PCM + Computer')->call('save')->assertHasErrors(['form.subject_ids' => 'required'])
            ->set('form.subject_ids', [(string) $this->sub['PHY'], (string) $this->sub['MAT'], (string) $cap->id])->call('save')->assertHasNoErrors();
        $group = QualifyingSubjectGroup::where('code', 'PCMC')->firstOrFail();
        $this->assertEqualsCanonicalizing(['Physics', 'Mathematics', 'Computer Applications'], $group->subjects->pluck('name')->all());

        // Editing keeps the pivot
        Livewire::test(QualifyingSubjectGroupsManager::class)->call('edit', $group->id)
            ->assertSet('form.subject_ids', $group->subjects->pluck('id')->map(fn ($id) => (string) $id)->all());

        // A subject used by a group cannot be deleted
        Livewire::test(QualifyingSubjectsManager::class)->call('confirmDelete', $cap->id)->call('delete');
        $this->assertNotNull($cap->fresh());

        // Board CGPA factor
        Livewire::test(HigherSecondaryBoardsManager::class)->call('edit', $this->cbse->id)->set('form.cgpa_factor', '9.6')->call('save')->assertHasNoErrors();
        $this->assertEquals(9.6, (float) $this->cbse->fresh()->cgpa_factor);
    }

    public function test_super_admin_sets_course_criteria_with_minimums_per_subject()
    {
        $this->actingAs($this->super);
        $course = Course::create(['name' => 'B.Com Aviation', 'code' => 'EBC-' . uniqid(), 'duration_months' => 36, 'total_semesters' => 6, 'status' => true]);

        $page = Livewire::test(CourseEligibilityManager::class)
            ->call('openModal')->set('course_id', $course->id)->set('qualifying_subject_group_id', $this->commerce->id)
            ->assertSee('Accountancy')->assertSee('Business Studies');
        $page->call('save')->assertHasErrors(['minimums']); // at least one minimum

        $page->set("minimums.{$this->sub['ACC']}", '55')->set("minimums.{$this->sub['BST']}", '101')->call('save')->assertHasErrors(["minimums.{$this->sub['BST']}"]);
        $page->set("minimums.{$this->sub['BST']}", '45')->set('min_aggregate', '50')->set('remarks', 'English must be passed')->call('save')->assertHasNoErrors();

        $criterion = CourseEligibilityCriterion::where('course_id', $course->id)->firstOrFail();
        $this->assertNull($criterion->higher_secondary_board_id); // any board
        $this->assertEquals(['ACC' => 55.0, 'BST' => 45.0], $criterion->subjects->mapWithKeys(fn ($s) => [$s->subject->code => (float) $s->min_percent])->all());
        $this->assertTrue(AuditTrail::where('module', 'course_eligibility')->where('action', 'create')->exists());

        // Same course + group + board again → refused; another board is fine
        $dup = Livewire::test(CourseEligibilityManager::class)->call('openModal')->set('course_id', $course->id)
            ->set('qualifying_subject_group_id', $this->commerce->id)->set("minimums.{$this->sub['ACC']}", '40');
        $dup->call('save')->assertHasErrors(['qualifying_subject_group_id']);
        $dup->set('higher_secondary_board_id', $this->state->id)->call('save')->assertHasNoErrors();

        // Listed per course; deactivate and delete
        Livewire::test(CourseEligibilityManager::class)->set('courseFilter', $course->id)->assertSee('B.Com Aviation')->assertSee('Any board')->assertSee('State Boards')
            ->call('toggleStatus', $criterion->id);
        $this->assertFalse($criterion->fresh()->status);
        Livewire::test(CourseEligibilityManager::class)->call('confirmDelete', $criterion->id)->call('delete');
        $this->assertSoftDeleted($criterion);
    }

    // ------------------------------------------------------------------ the check

    public function test_marks_are_checked_per_subject_aggregate_cgpa_and_board()
    {
        $this->assertSame('eligible', $this->check(['PHY' => 55, 'CHE' => 50, 'MAT' => 60])['status']);

        $fail = $this->check(['PHY' => 48, 'CHE' => 60, 'MAT' => 76]);
        $this->assertSame('not_eligible', $fail['status']);
        $this->assertSame(['Physics 48 % — needs 50 %.'], $fail['reasons']);
        $this->assertEquals(61.33, $fail['aggregate']);

        // every subject passes, the average does not (50 + 45 + 50 → 48.33)
        $agg = $this->check(['PHY' => 50, 'CHE' => 45, 'MAT' => 50]);
        $this->assertSame('not_eligible', $agg['status']);
        $this->assertFalse($agg['aggregate_ok']);

        // CGPA 8.0 × 9.5 (CBSE) = 76 %; the same CGPA × 10 on State Boards = 80 %
        $cgpa = $this->check(['PHY' => 8, 'CHE' => 8, 'MAT' => 8], 'PCM', $this->cbse, 'cgpa');
        $this->assertSame('eligible', $cgpa['status']);
        $this->assertEquals(76.0, $cgpa['subjects'][0]['percent']);
        $this->assertEquals(80.0, $this->check(['PHY' => 8, 'CHE' => 8, 'MAT' => 8], 'PCM', $this->state, 'cgpa')['subjects'][0]['percent']);

        // A board's own criterion wins over "any board"
        $this->criterion($this->course, $this->pcm, $this->state, ['PHY' => 70, 'CHE' => 70, 'MAT' => 70]);
        $this->assertSame('eligible', $this->check(['PHY' => 60, 'CHE' => 60, 'MAT' => 60], 'PCM', $this->cbse)['status']);
        $this->assertSame('not_eligible', $this->check(['PHY' => 60, 'CHE' => 60, 'MAT' => 60], 'PCM', $this->state)['status']);

        // A group the course does not accept; a course without criteria
        $commerce = $this->check(['ACC' => 90, 'BST' => 90], 'COMMERCE');
        $this->assertSame('not_eligible', $commerce['status']);
        $this->assertStringContainsString('PCB / PCM', $commerce['reasons'][0]);
        $this->assertSame('not_configured', $this->check(['ACC' => 10], 'COMMERCE', null, 'percentage', $this->openCourse)['status']);

        // Groups offered: only the accepted ones (all groups for a course without criteria)
        $service = app(EligibilityService::class);
        $this->assertEqualsCanonicalizing(['PCB', 'PCM'], $service->groupsFor($this->course->id)->pluck('code')->all());
        $this->assertContains('COMMERCE', $service->groupsFor($this->openCourse->id)->pluck('code')->all());
    }

    // ------------------------------------------------------------------ admin onboarding

    public function test_admin_onboarding_blocks_submission_until_eligible_or_allowed_with_reason()
    {
        $admin = $this->makeUser('institute-admin', $this->inst);
        $this->actingAs($admin);
        $student = $this->makeStudent();

        // Criteria shown on the Basic tab; marks per subject required once the stream has a criterion
        Livewire::test(StudentOnboardingComponent::class, ['student' => $student->id])->assertSee('Course eligibility (12th)')->assertSee('Physics');
        $this->academicTab($student, [])->call('saveAcademic')
            ->assertHasErrors(["subject_marks.{$this->sub['PHY']}" => 'required', "subject_marks.{$this->sub['MAT']}" => 'required']);

        // Physics below the minimum: saved, live check shows why, submission blocked
        $page = $this->academicTab($student, ['PHY' => '48', 'CHE' => '60', 'MAT' => '76'])
            ->assertSee('Not eligible for this course')->assertSee('Physics 48 % — needs 50 %.')
            ->call('saveAcademic')->assertHasNoErrors();
        $academic = $student->academicDetail()->first();
        $this->assertSame('not_eligible', $academic->eligibility_status);
        $this->assertSame($this->pcm->id, $academic->qualifying_subject_group_id);
        $this->assertSame(3, $student->qualifyingMarks()->count());

        $page->call('submit');
        $this->assertSame('draft', $student->fresh()->status);
        $page->assertSet('activeTab', 'academic');
        $this->assertStringContainsString('Physics 48 %', implode(' ', app(\App\Services\OnboardingService::class)->checklist($student->fresh())['academic']));

        // Accounts cannot allow it
        $this->actingAs($this->makeUser('accounts', $this->inst));
        Livewire::test(StudentOnboardingComponent::class, ['student' => $student->id])->set('eligibility_reason', 'Because')->call('overrideEligibility')->assertForbidden();

        // Institute Admin allows it with a reason → can submit
        $this->actingAs($admin);
        $review = Livewire::test(StudentOnboardingComponent::class, ['student' => $student->id])->call('goTo', 'review')
            ->assertSee('Not eligible for the course (12th criteria)')->assertSee('Allow with reason');
        $review->call('overrideEligibility')->assertHasErrors(['eligibility_reason' => 'required']);
        $review->set('eligibility_reason', 'Marksheet shows Physics 52 — typing error in the form')->call('overrideEligibility')->assertHasNoErrors();
        $academic->refresh();
        $this->assertSame('overridden', $academic->eligibility_status);
        $this->assertSame($admin->id, $academic->eligibility_override_by);
        $this->assertTrue(AuditTrail::where('action', 'override')->where('module', 'students')->exists());

        $review->call('submit');
        $this->assertNotSame('draft', $student->fresh()->status); // submitted (pending documents)

        // Changing a mark checks again and clears the override
        $this->academicTab($student->fresh(), ['PHY' => '49', 'CHE' => '60', 'MAT' => '76'])->call('saveAcademic');
        $this->assertSame('not_eligible', $academic->fresh()->eligibility_status);
        $this->assertNull($academic->fresh()->eligibility_override_by);

        // Fixing the mark makes the student eligible
        $this->academicTab($student->fresh(), ['PHY' => '52', 'CHE' => '60', 'MAT' => '76'])->call('saveAcademic');
        $this->assertSame('eligible', $academic->fresh()->eligibility_status);

        // Gate 1 shows the subject marks and the result
        Livewire::test(DocumentVerificationComponent::class)->call('select', $student->id)
            ->assertSee('Physics')->assertSee('52%')->assertSee('Eligible');
    }

    public function test_course_without_criteria_is_not_blocked_and_subject_marks_are_optional()
    {
        $this->actingAs($this->makeUser('institute-admin', $this->inst));
        $student = $this->makeStudent(['course_id' => $this->openCourse->id]);

        $this->academicTab($student, [], 'COMMERCE')->assertSee('Accountancy')->call('saveAcademic')->assertHasNoErrors();
        $this->assertSame('not_configured', $student->academicDetail()->first()->eligibility_status);
        $this->assertSame([], app(\App\Services\EligibilityService::class)->blockingReasons($student->fresh()));
    }

    // ------------------------------------------------------------------ portal

    public function test_portal_shows_criteria_offers_accepted_streams_and_blocks_submit()
    {
        $user = User::create(['name' => 'Portal Elig', 'email' => uniqid('pe') . '@mail.test', 'password' => bcrypt('secret123'), 'institute_id' => $this->inst->id, 'status' => true]);
        $user->assignRole('student');
        $student = $this->makeStudent(['user_id' => $user->id, 'email' => $user->email]);
        $this->actingAs($user);

        $page = Livewire::test(AcademicPage::class)
            ->assertSee('Eligibility (12th)')->assertSee('Average')
            ->assertSee('PCM')->assertSee('PCB')->assertDontSee('Accountancy, Business Studies')
            ->call('choose', 'higher_secondary_subject', 'COMMERCE')->assertSet('higher_secondary_subject', null) // not accepted
            ->set('matriculation_board_id', MatriculationBoard::first()->id)->set('matriculation_mark', '90')
            ->set('higher_secondary_board_id', $this->cbse->id)->call('choose', 'higher_secondary_subject', 'PCM')
            ->call('choose', 'higher_secondary_mark_type', 'cgpa')->set('higher_secondary_mark', '8')
            ->assertSee('Physics')->assertSee('Mathematics');

        $page->call('save')->assertHasErrors(["subject_marks.{$this->sub['PHY']}" => 'required']);
        $page->set("subject_marks.{$this->sub['PHY']}", '5')->set("subject_marks.{$this->sub['CHE']}", '8')->set("subject_marks.{$this->sub['MAT']}", '8')
            ->assertSee('Not eligible for this course yet')->assertSee('Physics 47.5 % — needs 50 %.')
            ->call('save')->assertHasNoErrors();
        $this->assertSame('not_eligible', $student->academicDetail()->first()->eligibility_status);

        Livewire::test(DocumentsPage::class)->call('submit')
            ->assertDispatchedBrowserEvent('show-toast', fn ($name, $data) => str_contains($data['message'], 'do not meet the eligibility criteria'));
        $this->assertSame('draft', $student->fresh()->status);

        Livewire::test(AcademicPage::class)->set("subject_marks.{$this->sub['PHY']}", '6')
            ->assertSee('You meet the eligibility criteria')->call('save')->assertHasNoErrors();
        $this->assertSame('eligible', $student->academicDetail()->first()->eligibility_status);
        $this->assertEquals(57.0, (float) $student->qualifyingMarks()->where('qualifying_subject_id', $this->sub['PHY'])->value('percent'));
    }
}
