<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Institutes\InstituteCoursesComponent;
use App\Http\Livewire\Admin\Masters\CoursesManager;
use App\Http\Livewire\Admin\Students\StudentOnboardingComponent;
use App\Http\Livewire\Admin\Students\StudentsComponent;
use App\Models\Admin\Category;
use App\Models\Admin\Country;
use App\Models\Admin\Course;
use App\Models\Admin\HigherSecondaryBoard;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteCourse;
use App\Models\Admin\MatriculationBoard;
use App\Models\Admin\Qualification;
use App\Models\Admin\Religion;
use App\Models\Admin\State;
use App\Models\Admin\Student;
use App\Models\Admin\StudentDocument;
use App\Models\User;
use Database\Seeders\admin\MasterDataSeeder;
use Database\Seeders\admin\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module 2 — admin-side student onboarding (tabs, validation, KYC uploads, submit, scoping).
 * Runs inside a transaction (rolled back); files go to a fake disk.
 */
class Module2StudentOnboardingTest extends TestCase
{
    use DatabaseTransactions;

    protected User $super;
    protected Institute $instA;
    protected Institute $instB;
    protected Course $course;
    protected Religion $religion;
    protected Category $category;
    protected Country $country;
    protected State $state;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
        $this->seed(MasterDataSeeder::class);

        $this->super = $this->makeUser('super-admin');
        $this->instA = $this->makeInstitute('Alpha Aviation', 2001);
        $this->instB = $this->makeInstitute('Beta Aero', 2010);

        $this->course = Course::create(['name' => 'AME B1.1', 'code' => 'B11-' . uniqid(), 'duration_months' => 48, 'total_semesters' => 8, 'status' => true]);
        InstituteCourse::create(['institute_id' => $this->instA->id, 'course_id' => $this->course->id, 'status' => true]);
        InstituteCourse::create(['institute_id' => $this->instB->id, 'course_id' => $this->course->id, 'status' => true]);

        $this->religion = Religion::where('name', 'Hindu')->firstOrFail();
        $this->category = Category::where('religion_id', $this->religion->id)->where('name', 'General')->firstOrFail();
        $this->country = Country::create(['name' => 'Testland ' . uniqid(), 'code' => substr(uniqid(), -6), 'status' => true]);
        $this->state = State::create(['country_id' => $this->country->id, 'name' => 'Test State', 'status' => true]);
    }

    protected function makeInstitute(string $name, int $year): Institute
    {
        return Institute::create([
            'name' => $name . ' ' . uniqid(), 'established_year' => $year, 'code' => Institute::generateCode($name, $year),
            'email' => uniqid() . '@inst.test', 'phone' => (string) random_int(1000000000, 9999999999), 'status' => true,
        ]);
    }

    protected function makeUser(string $role, ?Institute $institute = null): User
    {
        $user = User::create([
            'name' => $role, 'email' => uniqid($role) . '@user.test', 'password' => bcrypt('password'),
            'institute_id' => $institute?->id, 'status' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    protected function basic($component, array $override = [])
    {
        $values = array_merge([
            'course_id' => $this->course->id,
            'joining_date' => now()->toDateString(),
            'first_name' => 'Arjun',
            'last_name' => 'Nair',
            'dob' => '2005-04-12',
            'gender' => 'male',
            'qualification_id' => Qualification::first()->id,
            'religion_id' => $this->religion->id,
            'category_id' => $this->category->id,
            'email' => uniqid('stu') . '@mail.test',
            'phone' => '+919876543210',
            'emergency_contact' => '+919876500000',
            'create_login' => false, // portal login has its own test
        ], $override);

        foreach ($values as $field => $value) {
            $component->set($field, $value);
        }

        return $component;
    }

    protected function createStudent(Institute $institute, array $override = []): Student
    {
        return Student::create(array_merge([
            'institute_id' => $institute->id, 'course_id' => $this->course->id, 'first_name' => 'Test', 'last_name' => 'Student',
            'dob' => '2005-01-01', 'gender' => 'female', 'qualification_id' => Qualification::first()->id,
            'email' => uniqid('s') . '@mail.test', 'phone' => '9876543210', 'emergency_contact' => '9876500001',
            'religion_id' => $this->religion->id, 'category_id' => $this->category->id,
            'joining_date' => now()->toDateString(), 'onboarding_deadline' => now()->addDays(30)->toDateString(), 'status' => 'draft',
        ], $override));
    }

    public function test_admin_creates_the_student_portal_login()
    {
        $this->actingAs($this->super);
        $email = uniqid('login') . '@mail.test';
        $phone = '+9198' . random_int(10000000, 99999999);

        // Login is on by default for a new student: password required and confirmed
        $page = $this->basic(Livewire::test(StudentOnboardingComponent::class)->set('institute_id', $this->instA->id),
            ['create_login' => true, 'email' => $email, 'phone' => $phone, 'first_name' => 'Loginy'])
            ->assertSee('Student portal login')
            ->assertSee('name="gender"', false);                    // one gender at a time
        $page->call('saveBasic')->assertHasErrors(['login_password' => 'required']);
        $page->set('login_password', 'short')->set('login_password_confirmation', 'short')->call('saveBasic')->assertHasErrors(['login_password' => 'min']);
        $page->call('generatePassword');
        $password = $page->get('login_password');
        $this->assertSame($password, $page->get('login_password_confirmation'));
        $this->assertGreaterThanOrEqual(8, strlen($password));
        $page->assertSee($password)->call('saveBasic')->assertHasNoErrors();

        $student = Student::where('email', $email)->firstOrFail();
        $user = $student->user;
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('student'));
        $this->assertSame($this->instA->id, $user->institute_id);
        $this->assertSame($phone, $user->phone);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($password, $user->password));
        $this->assertNull($page->get('login_password'));

        // Existing student with a login: email change follows, password reset is optional
        $edit = Livewire::test(StudentOnboardingComponent::class, ['student' => $student->id])
            ->assertSee('Login active')->assertSee($email)->assertDontSee($password);
        $newEmail = uniqid('moved') . '@mail.test';
        $edit->set('email', $newEmail)->call('saveBasic')->assertHasNoErrors();
        $this->assertSame($newEmail, $user->fresh()->email);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($password, $user->fresh()->password)); // unchanged when left blank
        $edit->call('goTo', 'basic')->set('login_password', 'NewPass#2026')->set('login_password_confirmation', 'NewPass#2026')->call('saveBasic')->assertHasNoErrors();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPass#2026', $user->fresh()->password));

        // The student can sign in to the portal with these credentials
        auth()->logout();
        $this->assertTrue(\Illuminate\Support\Facades\Auth::attempt(['email' => $newEmail, 'password' => 'NewPass#2026']));

        // Email already used by another login is refused
        $this->actingAs($this->super);
        $this->basic(Livewire::test(StudentOnboardingComponent::class)->set('institute_id', $this->instA->id),
            ['create_login' => true, 'email' => uniqid() . '@mail.test', 'phone' => $phone])
            ->set('login_password', 'Another#123')->set('login_password_confirmation', 'Another#123')
            ->call('saveBasic')->assertHasErrors(['phone' => 'unique']);

        // Existing student without a login: adding one is opt-in
        $plain = $this->basic(Livewire::test(StudentOnboardingComponent::class)->set('institute_id', $this->instA->id), ['email' => uniqid() . '@mail.test', 'first_name' => 'Nologin']);
        $plain->call('saveBasic')->assertHasNoErrors();
        $noLogin = Student::where('first_name', 'Nologin')->latest('id')->firstOrFail();
        $this->assertNull($noLogin->user_id);
        Livewire::test(StudentOnboardingComponent::class, ['student' => $noLogin->id])
            ->assertSet('create_login', false)->call('saveBasic')->assertHasNoErrors()      // no password needed
            ->set('create_login', true)->set('phone', '+9197' . random_int(10000000, 99999999))
            ->set('login_password', 'Later#1234')->set('login_password_confirmation', 'Later#1234')->call('saveBasic')->assertHasNoErrors();
        $this->assertNotNull($noLogin->fresh()->user_id);
    }

    public function test_documents_upload_on_select_and_can_be_replaced_or_deleted()
    {
        $this->actingAs($this->super);
        $this->basic(Livewire::test(StudentOnboardingComponent::class)->set('institute_id', $this->instA->id), ['first_name' => 'Autoup'])->call('saveBasic');
        $student = Student::where('first_name', 'Autoup')->latest('id')->firstOrFail();

        $page = Livewire::test(StudentOnboardingComponent::class, ['student' => $student->id])->call('goTo', 'documents')
            ->assertSee('Choose file')->assertSee('uploads automatically');

        // Choosing a file is enough — no Upload click
        $page->set('upload_marksheet_10', UploadedFile::fake()->create('first.pdf', 100, 'application/pdf'))->assertHasNoErrors()
            ->assertSet('upload_marksheet_10', null)->assertSee('first.pdf')->assertSee('Replace');
        $this->assertSame(1, $student->documents()->where('document_type', 'marksheet_10')->count());

        // Replace: choosing again swaps the file (still one document of this type)
        $page->set('upload_marksheet_10', UploadedFile::fake()->create('second.pdf', 100, 'application/pdf'))->assertHasNoErrors()
            ->assertSee('second.pdf')->assertDontSee('first.pdf')
            ->assertDispatchedBrowserEvent('show-toast', fn ($name, $data) => $data['message'] === '10th Marksheet replaced.');
        $this->assertSame(['second.pdf'], $student->documents()->where('document_type', 'marksheet_10')->pluck('original_name')->all());

        // A wrong file type is refused and nothing is stored
        $page->set('upload_kyc_photo', UploadedFile::fake()->create('photo.exe', 10))->assertHasErrors(['upload_kyc_photo']);
        $this->assertSame(0, $student->documents()->where('document_type', 'kyc_photo')->count());

        // Delete
        $doc = $student->documents()->where('document_type', 'marksheet_10')->firstOrFail();
        $page->call('confirmDeleteDocument', $doc->id)->call('deleteDocument')->assertSee('Choose file');
        $this->assertSame(0, $student->documents()->where('document_type', 'marksheet_10')->count());
    }

    public function test_full_onboarding_flow_by_super_admin()
    {
        $this->actingAs($this->super);

        // Tab 1: basic → creates draft and redirects to the address tab
        $component = $this->basic(Livewire::test(StudentOnboardingComponent::class)->set('institute_id', $this->instA->id));
        $component->call('saveBasic')->assertHasNoErrors();

        $student = Student::where('first_name', 'Arjun')->latest('id')->firstOrFail();
        $component->assertRedirect(route('admin.students.edit', ['student' => $student->id, 'tab' => 'address']));
        $this->assertSame('draft', $student->status);
        $this->assertSame($this->instA->id, $student->institute_id);
        $this->assertSame(now()->addDays(config('camp.onboarding_days'))->toDateString(), $student->onboarding_deadline->toDateString());

        // Tab 2: address & parent
        $edit = Livewire::test(StudentOnboardingComponent::class, ['student' => $student->id])
            ->set('address', '12 MG Road')
            ->set('country_id', $this->country->id)
            ->set('state_id', $this->state->id)
            ->set('city', 'Kochi')
            ->set('pincode', '682001')
            ->set('parent_name', 'Ravi Nair')
            ->set('parent_phone', '+919800000000')
            ->call('saveAddress')
            ->assertHasNoErrors()
            ->assertSet('activeTab', 'academic');

        // Tab 3: academic
        $edit->set('matriculation_board_id', MatriculationBoard::first()->id)
            ->set('matriculation_mark_type', 'percentage')
            ->set('matriculation_mark', '91.5')
            ->set('higher_secondary_board_id', HigherSecondaryBoard::first()->id)
            ->set('higher_secondary_subject', 'PCM')
            ->set('higher_secondary_mark_type', 'cgpa')
            ->set('higher_secondary_mark', '9.2')
            ->call('saveAcademic')
            ->assertHasNoErrors()
            ->assertSet('activeTab', 'documents');

        // Submit with only the photo uploaded → Pending Documents
        $edit->set('upload_kyc_photo', UploadedFile::fake()->image('photo.jpg'))
            ->call('uploadDocument', 'kyc_photo')
            ->assertHasNoErrors()
            ->call('submit');
        $this->assertSame('pending_docs', $student->fresh()->status);

        // Upload remaining required documents, submit again → Pending Approval
        foreach (['medical_certificate', 'marksheet_10', 'marksheet_12'] as $type) {
            $edit->set("upload_{$type}", UploadedFile::fake()->create("{$type}.pdf", 200, 'application/pdf'))
                ->call('uploadDocument', $type)
                ->assertHasNoErrors();
        }
        $edit->call('submit');

        $student->refresh();
        $this->assertSame('pending_approval', $student->status);
        $this->assertNotNull($student->submitted_at);
        $this->assertSame(4, $student->documents()->count());
        Storage::disk('local')->assertExists($student->documents()->first()->file_path);
    }

    public function test_validation_rules()
    {
        $this->actingAs($this->super);
        $otherCourse = Course::create(['name' => 'Not offered', 'code' => 'NO-' . uniqid(), 'duration_months' => 12, 'total_semesters' => 2, 'status' => true]);
        $otherReligionCategory = Category::where('religion_id', '!=', $this->religion->id)->first();

        $this->basic(Livewire::test(StudentOnboardingComponent::class)->set('institute_id', $this->instA->id), [
            'course_id' => $otherCourse->id,                  // not offered by the institute
            'category_id' => $otherReligionCategory->id,      // belongs to another religion
            'emergency_contact' => '+919876543210',           // same as phone
            'first_name' => 'Arjun123',
            'dob' => now()->addDay()->toDateString(),
        ])->call('saveBasic')->assertHasErrors([
            'course_id' => 'exists', 'category_id' => 'exists', 'emergency_contact' => 'different', 'first_name' => 'regex', 'dob' => 'before',
        ]);

        $student = $this->createStudent($this->instA);
        Livewire::test(StudentOnboardingComponent::class, ['student' => $student->id])
            ->set('matriculation_board_id', MatriculationBoard::first()->id)
            ->set('matriculation_mark_type', 'cgpa')
            ->set('matriculation_mark', '11')                 // CGPA max 10
            ->set('higher_secondary_board_id', HigherSecondaryBoard::first()->id)
            ->set('higher_secondary_subject', 'BIOLOGY')      // not in the fixed list
            ->set('higher_secondary_mark_type', 'percentage')
            ->set('higher_secondary_mark', '101')
            ->call('saveAcademic')
            ->assertHasErrors(['matriculation_mark' => 'max', 'higher_secondary_subject' => 'in', 'higher_secondary_mark' => 'max']);

        // Wrong file type for the photo
        Livewire::test(StudentOnboardingComponent::class, ['student' => $student->id])
            ->set('upload_kyc_photo', UploadedFile::fake()->create('photo.pdf', 100, 'application/pdf'))
            ->call('uploadDocument', 'kyc_photo')
            ->assertHasErrors(['upload_kyc_photo' => 'mimes']);
    }

    public function test_submit_is_blocked_until_details_are_complete()
    {
        $this->actingAs($this->super);
        $student = $this->createStudent($this->instA);

        Livewire::test(StudentOnboardingComponent::class, ['student' => $student->id])
            ->call('submit')
            ->assertSet('activeTab', 'address');

        $this->assertSame('draft', $student->fresh()->status);
    }

    public function test_institute_admin_onboards_only_into_own_institute()
    {
        $admin = $this->makeUser('institute-admin', $this->instA);
        $this->actingAs($admin);

        $this->basic(Livewire::test(StudentOnboardingComponent::class)->set('institute_id', $this->instB->id))
            ->call('saveBasic')
            ->assertHasNoErrors();
        $this->assertSame($this->instA->id, Student::latest('id')->first()->institute_id);

        $other = $this->createStudent($this->instB);
        $this->get(route('admin.students.edit', $other->id))->assertNotFound();
        $this->assertFalse(Student::pluck('id')->contains($other->id)); // list is institute-scoped
    }

    public function test_page_access_and_document_privacy()
    {
        $student = $this->createStudent($this->instA);
        $path = UploadedFile::fake()->image('p.jpg')->store("students/{$student->id}", 'local');
        $doc = StudentDocument::create([
            'student_id' => $student->id, 'institute_id' => $this->instA->id, 'document_type' => 'kyc_photo', 'file_path' => $path,
            'original_name' => 'p.jpg', 'mime_type' => 'image/jpeg', 'size' => 100, 'uploaded_at' => now(),
        ]);

        $this->actingAs($this->super);
        $this->get(route('admin.students'))->assertOk()->assertSee('Add Student');
        $this->get(route('admin.students.create'))->assertOk()->assertSee('Basic Details');
        $this->get(route('admin.students.edit', ['student' => $student->id, 'tab' => 'documents']))->assertOk()->assertSee('Passport Size Photo');
        $this->get(route('admin.students.documents.show', $doc->id))->assertOk();

        $this->actingAs($this->makeUser('institute-admin', $this->instB));
        $this->get(route('admin.students.documents.show', $doc->id))->assertNotFound();

        // Accounts may view students (fees work) but not add them
        $this->actingAs($this->makeUser('accounts', $this->instA));
        $this->get(route('admin.students'))->assertOk()->assertDontSee('Add Student');
        $this->get(route('admin.students.create'))->assertForbidden();

        $this->actingAs($this->makeUser('faculty', $this->instA)); // no student permissions
        $this->get(route('admin.students'))->assertForbidden();
        $this->get(route('admin.students.create'))->assertForbidden();
        $this->get(route('admin.students.documents.show', $doc->id))->assertForbidden();
    }

    public function test_only_draft_students_can_be_deleted_and_submitted_ones_are_read_only()
    {
        $this->actingAs($this->super);
        $draft = $this->createStudent($this->instA);
        $submitted = $this->createStudent($this->instA, ['status' => 'er_issued']);

        Livewire::test(StudentsComponent::class)->call('confirmDelete', $draft->id)->call('delete');
        $this->assertSoftDeleted($draft);

        Livewire::test(StudentsComponent::class)->call('confirmDelete', $submitted->id)->call('delete');
        $this->assertNotSoftDeleted($submitted);

        Livewire::test(StudentOnboardingComponent::class, ['student' => $submitted->id])
            ->set('city', 'Changed')
            ->call('saveAddress')
            ->assertForbidden();
    }

    public function test_master_data_used_by_students_is_protected()
    {
        $this->actingAs($this->super);
        $this->createStudent($this->instA);

        // Course used by a student cannot be deleted from Master Data
        Livewire::test(CoursesManager::class)->call('confirmDelete', $this->course->id)->call('delete');
        $this->assertNotSoftDeleted($this->course);

        // Course with enrolled students cannot be removed from the institute
        $link = InstituteCourse::where('institute_id', $this->instA->id)->where('course_id', $this->course->id)->first();
        Livewire::test(InstituteCoursesComponent::class)->call('confirmDelete', $link->id)->call('delete');
        $this->assertNotSoftDeleted($link);
    }
}
