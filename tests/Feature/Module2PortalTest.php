<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Institutes\PaymentSettingsComponent;
use App\Http\Livewire\Admin\Onboarding\DocumentVerificationComponent;
use App\Http\Livewire\Portal\AcademicPage;
use App\Http\Livewire\Portal\DetailsPage;
use App\Http\Livewire\Portal\DocumentsPage;
use App\Http\Livewire\Portal\LoginPage;
use App\Http\Livewire\Portal\PaymentPage;
use App\Models\Admin\Category;
use App\Models\Admin\Country;
use App\Models\Admin\Course;
use App\Models\Admin\CourseFee;
use App\Models\Admin\EnrollmentApproval;
use App\Models\Admin\HigherSecondaryBoard;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteCourse;
use App\Models\Admin\InstitutePaymentGateway;
use App\Models\Admin\MatriculationBoard;
use App\Models\Admin\NotificationLog;
use App\Models\Admin\PaymentGateway;
use App\Models\Admin\Qualification;
use App\Models\Admin\Religion;
use App\Models\Admin\State;
use App\Models\Admin\Student;
use App\Models\Admin\StudentPayment;
use App\Models\User;
use Database\Seeders\admin\MasterDataSeeder;
use Database\Seeders\admin\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module 2.6 — admissions portal (student website) + document re-review + payment settings.
 * Runs inside a transaction (rolled back); files go to a fake disk.
 */
class Module2PortalTest extends TestCase
{
    use DatabaseTransactions;

    protected Institute $inst;
    protected Course $course;
    protected Religion $religion;
    protected Country $country;
    protected State $state;
    protected PaymentGateway $gpay;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
        $this->seed(MasterDataSeeder::class);

        $this->inst = Institute::create([
            'name' => 'Portal Aviation ' . uniqid(), 'established_year' => 2003, 'code' => Institute::generateCode('Portal Aviation', 2003),
            'email' => uniqid() . '@inst.test', 'phone' => (string) random_int(1000000000, 9999999999), 'status' => true,
        ]);
        $this->course = Course::create(['name' => 'Diploma in Aviation', 'code' => 'DA-' . uniqid(), 'duration_months' => 24, 'total_semesters' => 4, 'status' => true]);
        InstituteCourse::create(['institute_id' => $this->inst->id, 'course_id' => $this->course->id, 'status' => true]);
        CourseFee::create(['institute_id' => $this->inst->id, 'course_id' => $this->course->id, 'fee_head' => 'Admission fee', 'amount' => 15000, 'due_days' => 0, 'status' => true]);

        $this->religion = Religion::where('name', 'Hindu')->firstOrFail();
        $this->country = Country::firstOrCreate(['code' => 'TST'], ['name' => 'Testland', 'status' => true]);
        $this->state = State::firstOrCreate(['country_id' => $this->country->id, 'name' => 'Test State'], ['status' => true]);
        $this->gpay = PaymentGateway::where('code', 'gpay')->firstOrFail();
    }

    protected function register(array $override = [])
    {
        $component = Livewire::test(DetailsPage::class);
        foreach ($this->registrationValues($override) as $field => $value) {
            $component->set($field, $value);
        }

        return $component->call('save');
    }

    protected function registrationValues(array $override = []): array
    {
        return array_merge([
            'email' => uniqid('ananya') . '@mail.test', 'phone' => '+9198' . random_int(10000000, 99999999),
            'password' => 'secret123', 'password_confirmation' => 'secret123',
            'institute_id' => $this->inst->id, 'course_id' => $this->course->id, 'joining_date' => now()->toDateString(),
            'first_name' => 'Ananya', 'last_name' => 'Menon', 'dob' => '2008-03-14', 'gender' => 'female',
            'qualification_id' => Qualification::first()->id, 'emergency_contact' => '+919447055210',
            'religion_id' => $this->religion->id, 'category_id' => Category::where('religion_id', $this->religion->id)->first()->id,
            'address' => 'TC 14/221', 'country_id' => $this->country->id, 'state_id' => $this->state->id,
            'city' => 'Thiruvananthapuram', 'pincode' => '695010', 'parent_name' => 'Suresh Menon', 'parent_phone' => '+919447055211',
        ], $override);
    }

    protected function completeApplication(): Student
    {
        $this->register()->assertHasNoErrors();
        $student = Auth::user()->student;

        Livewire::test(AcademicPage::class)
            ->set('matriculation_board_id', MatriculationBoard::first()->id)->set('matriculation_mark', '92.4')
            ->set('higher_secondary_board_id', HigherSecondaryBoard::first()->id)->call('choose', 'higher_secondary_subject', 'PCB')
            ->call('choose', 'higher_secondary_mark_type', 'cgpa')->set('higher_secondary_mark', '9.4')
            ->call('save')->assertHasNoErrors();

        $docs = Livewire::test(DocumentsPage::class);
        $docs->set('upload_kyc_photo', UploadedFile::fake()->image('photo.jpg'));
        foreach (['medical_certificate', 'marksheet_10', 'marksheet_12'] as $type) {
            $docs->set("upload_{$type}", UploadedFile::fake()->create("{$type}.pdf", 100, 'application/pdf'));
        }

        return $student->fresh();
    }

    protected function enableGpay(): InstitutePaymentGateway
    {
        return InstitutePaymentGateway::create(['institute_id' => $this->inst->id, 'payment_gateway_id' => $this->gpay->id, 'upi_id' => 'portalaviation@okaxis', 'payee_name' => 'Portal Aviation', 'status' => true]);
    }

    // ------------------------------------------------------------------ registration & steps

    public function test_public_pages_render()
    {
        $this->get(route('portal.home'))->assertOk()->assertSee('Apply for admission online');
        $this->get(route('portal.login'))->assertOk()->assertSee('Sign in');
        $this->get(route('portal.register'))->assertOk()->assertSee('Create your login');
        $this->get(route('portal.status'))->assertRedirect(route('portal.login'));
    }

    public function test_student_registers_and_completes_the_application()
    {
        $this->register()->assertHasNoErrors()->assertRedirect(route('portal.academic'));

        $user = Auth::user();
        $this->assertTrue($user->hasRole('student'));
        $student = $user->student;
        $this->assertSame('draft', $student->status);
        $this->assertSame($this->inst->id, $student->institute_id);
        $this->assertSame($user->email, $student->email);

        $student = $this->completeApplication(); // second application on a new account
        $this->assertSame(4, $student->documents()->count());

        Livewire::test(DocumentsPage::class)->call('submit')->assertRedirect(route('portal.payment'));
        $student->refresh();
        $this->assertSame('pending_approval', $student->status);
        $this->assertNotNull($student->gate(EnrollmentApproval::DOCUMENTS));
        $this->assertSame(1, $student->dues()->count()); // generated from the fee structure at registration (not duplicated on submit)

        // Details stay editable after submission while no payment is confirmed
        Livewire::test(DetailsPage::class)->set('city', 'Kochi')->call('save')->assertHasNoErrors()->assertRedirect(route('portal.academic'));
        $this->assertSame('Kochi', $student->fresh()->city);

        // Every portal page renders for the signed-in student
        foreach (['portal.status', 'portal.details', 'portal.academic', 'portal.documents', 'portal.payment'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_registration_validation()
    {
        $existing = User::create(['name' => 'X', 'email' => 'taken@mail.test', 'password' => bcrypt('x'), 'status' => true]);
        $otherCourse = Course::create(['name' => 'Not here', 'code' => 'NH-' . uniqid(), 'duration_months' => 12, 'total_semesters' => 2, 'status' => true]);

        $this->register([
            'email' => $existing->email,
            'password_confirmation' => 'different',
            'course_id' => $otherCourse->id,
            'emergency_contact' => '+919447055211',
            'parent_phone' => 'abc',
        ])->assertHasErrors(['email' => 'unique', 'password' => 'confirmed', 'course_id' => 'exists', 'parent_phone' => 'regex']);

        $this->assertFalse(Auth::check());
    }

    public function test_academic_page_accepts_only_fixed_choices_and_mark_ranges()
    {
        $this->register()->assertHasNoErrors();

        Livewire::test(AcademicPage::class)
            ->call('choose', 'higher_secondary_subject', 'HACKED')->assertSet('higher_secondary_subject', null)
            ->call('choose', 'matriculation_mark_type', 'cgpa')
            ->set('matriculation_board_id', MatriculationBoard::first()->id)->set('matriculation_mark', '10.4')
            ->set('higher_secondary_board_id', HigherSecondaryBoard::first()->id)->call('choose', 'higher_secondary_subject', 'PCM')
            ->set('higher_secondary_mark', '101')
            ->call('save')
            ->assertHasErrors(['matriculation_mark' => 'max', 'higher_secondary_mark' => 'max']);
    }

    // ------------------------------------------------------------------ payment

    public function test_student_pays_by_gpay_and_accounts_sees_it()
    {
        $setting = $this->enableGpay();
        $student = $this->completeApplication();
        Livewire::test(DocumentsPage::class)->call('submit');

        $pay = Livewire::test(PaymentPage::class)
            ->assertSet('setting_id', $setting->id)
            ->assertSet('amount', 15000.0)
            ->assertSee('portalaviation@okaxis')
            ->assertSee('upi://pay?pa=portalaviation%40okaxis', false);

        $pay->set('amount', 20000)->call('pay')->assertHasErrors(['amount' => 'max', 'utr' => 'required', 'proof' => 'required']);

        $pay->set('amount', 15000)->set('utr', '627514903318')->set('payer_upi', 'ananya.m@okaxis')
            ->set('proof', UploadedFile::fake()->image('gpay.png'))
            ->call('pay')->assertHasNoErrors()->assertRedirect(route('portal.payment'));

        $payment = StudentPayment::where('student_id', $student->id)->firstOrFail();
        $this->assertSame('pending_verification', $payment->status);
        $this->assertSame('627514903318', $payment->transaction_reference);
        $this->assertSame($this->gpay->id, $payment->payment_gateway_id);

        // Nothing left to pay → no second payment for the same fee
        Livewire::test(PaymentPage::class)->assertSet('due_id', null)->assertSee('Being confirmed');
    }

    public function test_pay_now_appears_only_after_the_documents_are_uploaded()
    {
        $this->enableGpay();

        // Step 1 shows the fees of the chosen course, but no pay option yet
        Livewire::test(DetailsPage::class)
            ->set('institute_id', $this->inst->id)->set('course_id', $this->course->id)
            ->assertSee('Fees for this course')->assertSee('Admission fee')->assertSee('₹15,000')
            ->assertDontSee('Save and pay now');

        $this->register()->assertRedirect(route('portal.academic'));
        $student = Auth::user()->student;
        $this->assertSame(1, $student->dues()->count());

        // Before documents: fees are listed, but no "Pay now" on any tab and the Payment step is closed
        foreach ([DetailsPage::class, AcademicPage::class, DocumentsPage::class] as $page) {
            Livewire::test($page)->assertSee('You can pay once all required documents are uploaded')
                ->assertDontSee('Pay now')->assertDontSee('Save and pay now');
        }
        $this->get(route('portal.details'))->assertSee('After documents');
        Livewire::test(PaymentPage::class)->assertRedirect(route('portal.documents'));
        Livewire::test(AcademicPage::class)
            ->set('matriculation_board_id', MatriculationBoard::first()->id)->set('matriculation_mark', '92')
            ->set('higher_secondary_board_id', HigherSecondaryBoard::first()->id)->call('choose', 'higher_secondary_subject', 'PCM')
            ->set('higher_secondary_mark', '90')
            ->call('saveAndPay')->assertRedirect(route('portal.documents'));

        // Upload the required documents → "Pay now" on every tab, Payment step open (even before submitting)
        $docs = Livewire::test(DocumentsPage::class);
        $docs->set('upload_kyc_photo', UploadedFile::fake()->image('photo.jpg'));
        foreach (['medical_certificate', 'marksheet_10', 'marksheet_12'] as $type) {
            $docs->set("upload_{$type}", UploadedFile::fake()->create("{$type}.pdf", 100, 'application/pdf'));
        }

        foreach ([DetailsPage::class, AcademicPage::class, DocumentsPage::class] as $page) {
            Livewire::test($page)->assertSee('Pay now')->assertDontSee('You can pay once all required documents are uploaded');
        }
        $this->assertSame('draft', $student->fresh()->status);

        $due = $student->dues()->first();
        Livewire::test(PaymentPage::class)->assertSet('due_id', $due->id)->assertSee('You can pay now')
            ->set('utr', '627514903318')->set('proof', UploadedFile::fake()->image('gpay.png'))
            ->call('pay')->assertHasNoErrors();
        $this->assertSame('pending_verification', $student->payments()->first()->status);

        // Once a payment exists, payment stays open even if a document is removed / rejected later
        Livewire::test(DocumentsPage::class)->call('remove', $student->documents()->where('document_type', 'marksheet_12')->value('id'));
        $this->assertTrue(\App\Support\PortalProgress::paymentUnlocked($student->fresh()));
    }

    public function test_office_only_institute_shows_no_online_transfer()
    {
        $this->completeApplication();
        Livewire::test(PaymentPage::class)->assertSet('setting_id', null)->assertSee('Online transfer is not set up for your institute yet');
    }

    public function test_details_are_editable_until_a_payment_is_confirmed()
    {
        $this->enableGpay();
        $student = $this->completeApplication();
        Livewire::test(DocumentsPage::class)->call('submit');

        // Course change is refused once a payment is submitted
        $otherCourse = Course::create(['name' => 'Other course', 'code' => 'OC-' . uniqid(), 'duration_months' => 12, 'total_semesters' => 2, 'status' => true]);
        InstituteCourse::create(['institute_id' => $this->inst->id, 'course_id' => $otherCourse->id, 'status' => true]);

        Livewire::test(PaymentPage::class)->set('utr', '627514903318')->set('proof', UploadedFile::fake()->image('gpay.png'))->call('pay');
        Livewire::test(DetailsPage::class)->set('course_id', $otherCourse->id)->call('save')->assertHasErrors(['course_id']);

        // Accounts confirms the payment → details are locked
        $payment = $student->payments()->first();
        $accounts = User::create(['name' => 'Acc', 'email' => uniqid() . '@staff.test', 'password' => bcrypt('x'), 'institute_id' => $this->inst->id, 'status' => true]);
        $accounts->assignRole('accounts');
        $studentUser = Auth::user();
        $this->actingAs($accounts);
        app(\App\Services\FeeService::class)->approvePayment($payment);

        $this->actingAs($studentUser);
        Livewire::test(DetailsPage::class)->assertSee('Your fee payment is confirmed')->set('city', 'Kochi')->call('save');
        $this->assertSame('Thiruvananthapuram', $student->fresh()->city);
        Livewire::test(AcademicPage::class)->assertSee('Your fee payment is confirmed');
    }

    public function test_changing_course_before_paying_replaces_the_fees()
    {
        $this->register()->assertHasNoErrors();
        $student = Auth::user()->student;
        $this->assertSame(['Admission fee'], $student->dues()->pluck('fee_head')->all());

        $otherCourse = Course::create(['name' => 'Other course', 'code' => 'OC-' . uniqid(), 'duration_months' => 12, 'total_semesters' => 2, 'status' => true]);
        InstituteCourse::create(['institute_id' => $this->inst->id, 'course_id' => $otherCourse->id, 'status' => true]);
        CourseFee::create(['institute_id' => $this->inst->id, 'course_id' => $otherCourse->id, 'fee_head' => 'Course fee', 'amount' => 9000, 'due_days' => 10, 'status' => true]);

        Livewire::test(DetailsPage::class)->set('course_id', $otherCourse->id)->call('save')->assertHasNoErrors();
        $this->assertSame(['Course fee'], $student->dues()->pluck('fee_head')->all());
    }

    public function test_unsaved_entries_are_kept_and_login_resumes_where_the_student_left_off()
    {
        $this->register(['email' => 'resume@mail.test'])->assertHasNoErrors();
        $student = Auth::user()->student;

        // On the academic step the student types a mark but signs out without saving
        Livewire::test(AcademicPage::class)
            ->set('matriculation_mark', '88.5')
            ->call('choose', 'higher_secondary_subject', 'PCM');
        $this->assertSame('academic', $student->fresh()->portal_last_step);

        $this->get(route('portal.logout'));
        $this->assertFalse(Auth::check());

        // Signing in again goes back to the academic step, with the typed values restored
        Livewire::test(LoginPage::class)->set('email', 'resume@mail.test')->set('password', 'secret123')->call('login')
            ->assertRedirect(route('portal.academic'));

        Livewire::test(AcademicPage::class)
            ->assertSet('matriculation_mark', '88.5')
            ->assertSet('higher_secondary_subject', 'PCM')
            ->assertSet('draftRestored', true)
            ->assertSee('We restored the changes you had not saved yet');

        // Saving clears the draft
        Livewire::test(AcademicPage::class)
            ->set('matriculation_board_id', MatriculationBoard::first()->id)
            ->set('higher_secondary_board_id', HigherSecondaryBoard::first()->id)->set('higher_secondary_mark', '90')
            ->call('save')->assertHasNoErrors();
        $this->assertSame([], \App\Support\PortalProgress::draft($student->fresh(), 'academic'));

        // Every step can be opened directly, back and forward
        foreach (['portal.details', 'portal.academic', 'portal.documents', 'portal.details'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('portal.payment'))->assertRedirect(route('portal.documents')); // no documents yet
        $this->assertSame('details', $student->fresh()->portal_last_step);
    }

    public function test_status_page_counts_documents_verified_by_the_admin()
    {
        $student = $this->completeApplication();
        Livewire::test(DocumentsPage::class)->call('submit');
        $studentUser = Auth::user();
        $this->get(route('portal.status'))->assertSee('0 of 4 checked');

        $admin = User::create(['name' => 'Admin', 'email' => uniqid() . '@staff.test', 'password' => bcrypt('x'), 'institute_id' => $this->inst->id, 'status' => true]);
        $admin->assignRole('institute-admin');
        $this->actingAs($admin);
        $verify = Livewire::test(DocumentVerificationComponent::class)->call('select', $student->id);
        foreach ($student->documents()->whereIn('document_type', ['kyc_photo', 'medical_certificate'])->get() as $doc) {
            $verify->call('verify', $doc->id);
        }

        $this->actingAs($studentUser);
        $this->get(route('portal.status'))->assertSee('2 of 4 checked')->assertSee('Approved');
    }

    public function test_detail_change_after_gate_one_approval_reopens_it()
    {
        $student = $this->completeApplication();
        Livewire::test(DocumentsPage::class)->call('submit');
        $studentUser = Auth::user();

        $admin = User::create(['name' => 'Admin', 'email' => uniqid() . '@staff.test', 'password' => bcrypt('x'), 'institute_id' => $this->inst->id, 'status' => true]);
        $admin->assignRole('institute-admin');
        $this->actingAs($admin);
        $verify = Livewire::test(DocumentVerificationComponent::class)->call('select', $student->id);
        foreach ($student->documents as $doc) {
            $verify->call('verify', $doc->id);
        }
        $verify->call('approveGate');
        $this->assertTrue($student->fresh()->gateApproved(EnrollmentApproval::DOCUMENTS));

        $this->actingAs($studentUser);
        Livewire::test(DetailsPage::class)->set('last_name', 'Menon Nair')->call('save')->assertHasNoErrors();
        $this->assertFalse($student->fresh()->gateApproved(EnrollmentApproval::DOCUMENTS));
    }

    // ------------------------------------------------------------------ access

    public function test_students_and_staff_stay_on_their_side()
    {
        $this->register()->assertHasNoErrors();
        $student = Auth::user()->student;

        $this->get(route('admin.dashboard'))->assertRedirect(route('portal.status'));
        $this->get(route('admin.students'))->assertRedirect(route('portal.status'));

        // Another student's files are not reachable
        $otherUser = User::create(['name' => 'Other', 'email' => uniqid() . '@mail.test', 'password' => bcrypt('x'), 'institute_id' => $this->inst->id, 'status' => true]);
        $other = Student::create(array_merge($student->only(['institute_id', 'course_id', 'first_name', 'last_name', 'dob', 'gender', 'qualification_id', 'phone', 'emergency_contact', 'religion_id', 'category_id', 'joining_date', 'onboarding_deadline']), [
            'email' => uniqid() . '@mail.test', 'user_id' => $otherUser->id, 'status' => 'draft',
        ]));
        $path = UploadedFile::fake()->image('x.jpg')->store("students/{$other->id}", 'local');
        $doc = $other->documents()->create(['institute_id' => $this->inst->id, 'document_type' => 'kyc_photo', 'file_path' => $path, 'original_name' => 'x.jpg', 'mime_type' => 'image/jpeg', 'size' => 10, 'uploaded_at' => now()]);
        $this->get(route('portal.document', $doc->id))->assertNotFound();

        // Staff are sent to the admin panel
        $admin = User::create(['name' => 'Admin', 'email' => uniqid() . '@staff.test', 'password' => bcrypt('x'), 'institute_id' => $this->inst->id, 'status' => true]);
        $admin->assignRole('institute-admin');
        $this->actingAs($admin);
        $this->get(route('portal.status'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_portal_login_rules()
    {
        $this->register(['email' => 'login-test@mail.test'])->assertHasNoErrors();
        Auth::logout();

        Livewire::test(LoginPage::class)->set('email', 'login-test@mail.test')->set('password', 'wrong')->call('login')
            ->assertSet('errorMessage', 'The email or password is not correct.');
        $this->assertFalse(Auth::check());

        // Registered but academic details not saved yet → resumes at the academic step
        Livewire::test(LoginPage::class)->set('email', 'login-test@mail.test')->set('password', 'secret123')->call('login')
            ->assertRedirect(route('portal.academic'));
        $this->assertTrue(Auth::check());
        Auth::logout();

        $staff = User::create(['name' => 'Acc', 'email' => 'acc-' . uniqid() . '@staff.test', 'password' => bcrypt('secret123'), 'institute_id' => $this->inst->id, 'status' => true]);
        $staff->assignRole('accounts');
        Livewire::test(LoginPage::class)->set('email', $staff->email)->set('password', 'secret123')->call('login')
            ->assertSet('errorMessage', 'This is the student admissions portal. Staff sign in at the admin panel.');
        $this->assertFalse(Auth::check());
    }

    // ------------------------------------------------------------------ re-review of rejected documents

    public function test_admin_re_reviews_a_wrongly_rejected_document()
    {
        $student = $this->completeApplication();
        Livewire::test(DocumentsPage::class)->call('submit');
        Auth::logout();

        $admin = User::create(['name' => 'Admin', 'email' => uniqid() . '@staff.test', 'password' => bcrypt('x'), 'institute_id' => $this->inst->id, 'status' => true]);
        $admin->assignRole('institute-admin');
        $this->actingAs($admin);

        $marksheet = $student->documents()->where('document_type', 'marksheet_10')->first();
        $queue = Livewire::test(DocumentVerificationComponent::class)->call('select', $student->id)
            ->set("remarks.{$marksheet->id}", 'Page is cut off')->call('reject', $marksheet->id);
        $this->assertSame('pending_docs', $student->fresh()->status);

        // Rejected tab shows the student with the re-review actions
        Livewire::test(DocumentVerificationComponent::class)->set('tab', 'rejected')->call('select', $student->id)
            ->assertSee('Approve after re-review')->assertSee('Move back to pending');

        // Move back to pending, then approve after re-review
        Livewire::test(DocumentVerificationComponent::class)->call('select', $student->id)->call('reopen', $marksheet->id);
        $this->assertSame('pending', $marksheet->fresh()->verification_status);
        $this->assertSame('pending_approval', $student->fresh()->status);

        Livewire::test(DocumentVerificationComponent::class)->call('select', $student->id)
            ->set("remarks.{$marksheet->id}", 'Wrong call')->call('reject', $marksheet->id)
            ->set("remarks.{$marksheet->id}", 'Checked the original — totals are visible')->call('approveRejected', $marksheet->id);

        $marksheet->refresh();
        $this->assertSame('verified', $marksheet->verification_status);
        $this->assertSame('Checked the original — totals are visible', $marksheet->remarks);
        $this->assertSame('pending_approval', $student->fresh()->status);
        $this->assertTrue(NotificationLog::where('event_type', 'document_accepted')->where('notifiable_id', $student->id)->exists());
        $this->assertTrue(\App\Models\Admin\AuditTrail::where('action', 're_review_approve')->exists());

        // The student no longer sees an "action needed" banner for it
        $this->actingAs($student->user);
        $this->get(route('portal.status'))->assertOk()->assertDontSee('re-upload your 10th Marksheet');
    }

    // ------------------------------------------------------------------ payment settings (admin)

    public function test_payment_settings_screen()
    {
        $admin = User::create(['name' => 'Admin', 'email' => uniqid() . '@staff.test', 'password' => bcrypt('x'), 'institute_id' => $this->inst->id, 'status' => true]);
        $admin->assignRole('institute-admin');
        $this->actingAs($admin);

        $this->get(route('admin.institute-payment-settings'))->assertOk();

        Livewire::test(PaymentSettingsComponent::class)
            ->assertSet('instituteId', $this->inst->id)
            ->call('edit', $this->gpay->id)
            ->set('upi_id', 'not a upi id')->call('save')->assertHasErrors(['upi_id' => 'regex'])
            ->set('upi_id', 'portal.aviation@okaxis')->set('payee_name', 'Portal Aviation')
            ->set('qr_upload', UploadedFile::fake()->image('qr.png', 300, 300))
            ->call('save')->assertHasNoErrors();

        $setting = InstitutePaymentGateway::where('institute_id', $this->inst->id)->where('payment_gateway_id', $this->gpay->id)->firstOrFail();
        $this->assertSame('portal.aviation@okaxis', $setting->upi_id);
        Storage::disk('local')->assertExists($setting->qr_code_path);
        $this->get(route('admin.institute-payment-settings.qr', $setting->id))->assertOk();

        // Online gateways cannot be set up by hand
        $razorpay = PaymentGateway::where('code', 'razorpay')->firstOrFail();
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::test(PaymentSettingsComponent::class)->call('edit', $razorpay->id);
    }
}
