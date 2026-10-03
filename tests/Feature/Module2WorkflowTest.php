<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Onboarding\DocumentVerificationComponent;
use App\Http\Livewire\Admin\Onboarding\EnrollmentQueueComponent;
use App\Http\Livewire\Admin\Onboarding\FeeStructureComponent;
use App\Http\Livewire\Admin\Onboarding\PaymentVerificationComponent;
use App\Http\Livewire\Admin\Students\StudentEnrollmentComponent;
use App\Http\Livewire\Admin\Students\StudentFeesComponent;
use App\Http\Livewire\Admin\Students\StudentOnboardingComponent;
use App\Models\Admin\Category;
use App\Models\Admin\Country;
use App\Models\Admin\Course;
use App\Models\Admin\CourseFee;
use App\Models\Admin\EnrollmentApproval;
use App\Models\Admin\HigherSecondaryBoard;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteCourse;
use App\Models\Admin\MatriculationBoard;
use App\Models\Admin\NotificationLog;
use App\Models\Admin\PaymentGateway;
use App\Models\Admin\Qualification;
use App\Models\Admin\Religion;
use App\Models\Admin\State;
use App\Models\Admin\Student;
use App\Models\Admin\StudentAcademicDetail;
use App\Models\Admin\StudentDocument;
use App\Models\Admin\StudentPayment;
use App\Models\User;
use App\Services\OnboardingService;
use Database\Seeders\admin\MasterDataSeeder;
use Database\Seeders\admin\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module 2.2–2.5: document verification (Gate 1) → fees & payments (Gate 2) → ER number → ER form & ID card.
 * Runs inside a transaction (rolled back); files go to a fake disk.
 */
class Module2WorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected Institute $inst;
    protected Institute $otherInst;
    protected Course $course;
    protected User $admin;
    protected User $accounts;
    protected User $tm;
    protected PaymentGateway $gpay;
    protected PaymentGateway $cash;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
        $this->seed(MasterDataSeeder::class);

        $this->inst = $this->makeInstitute('Kappa Aviation');
        $this->otherInst = $this->makeInstitute('Lambda Aero');
        $this->course = Course::create(['name' => 'AME B1.1', 'code' => 'K11-' . uniqid(), 'duration_months' => 48, 'total_semesters' => 8, 'status' => true]);
        InstituteCourse::create(['institute_id' => $this->inst->id, 'course_id' => $this->course->id, 'status' => true]);
        InstituteCourse::create(['institute_id' => $this->otherInst->id, 'course_id' => $this->course->id, 'status' => true]);

        $this->admin = $this->makeUser('institute-admin', $this->inst);
        $this->accounts = $this->makeUser('accounts', $this->inst);
        $this->tm = $this->makeUser('training-manager', $this->inst);

        $this->gpay = PaymentGateway::where('code', 'gpay')->firstOrFail();
        $this->cash = PaymentGateway::where('code', 'cash')->firstOrFail();
    }

    protected function makeInstitute(string $name): Institute
    {
        return Institute::create([
            'name' => $name . ' ' . uniqid(), 'established_year' => 2004, 'code' => Institute::generateCode($name, 2004),
            'email' => uniqid() . '@inst.test', 'phone' => (string) random_int(1000000000, 9999999999), 'status' => true,
        ]);
    }

    protected function makeUser(string $role, ?Institute $institute = null): User
    {
        $user = User::create(['name' => ucfirst($role), 'email' => uniqid($role) . '@user.test', 'password' => bcrypt('x'), 'institute_id' => $institute?->id, 'status' => true]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * A student who completed onboarding and submitted it with all required documents.
     */
    protected function submittedStudent(?Institute $institute = null): Student
    {
        $institute = $institute ?? $this->inst;
        $religion = Religion::where('name', 'Christian')->firstOrFail();
        $country = Country::firstOrCreate(['code' => 'TST'], ['name' => 'Testland', 'status' => true]);

        $student = Student::create([
            'institute_id' => $institute->id, 'course_id' => $this->course->id, 'first_name' => 'Ananya', 'last_name' => 'Menon',
            'dob' => '2008-03-14', 'gender' => 'female', 'qualification_id' => Qualification::first()->id,
            'email' => uniqid('ananya') . '@mail.test', 'phone' => '+919847012345', 'emergency_contact' => '+919447055210',
            'religion_id' => $religion->id, 'category_id' => Category::where('religion_id', $religion->id)->first()->id,
            'joining_date' => '2026-10-01', 'onboarding_deadline' => '2026-10-31',
            'address' => 'TC 14/221', 'country_id' => $country->id,
            'state_id' => State::firstOrCreate(['country_id' => $country->id, 'name' => 'Test State'], ['status' => true])->id,
            'city' => 'Thiruvananthapuram', 'pincode' => '695010', 'parent_name' => 'Suresh Menon', 'parent_phone' => '+919447055210',
            'status' => 'pending_approval', 'submitted_at' => now(),
        ]);
        StudentAcademicDetail::create([
            'student_id' => $student->id, 'matriculation_board_id' => MatriculationBoard::first()->id, 'matriculation_mark_type' => 'percentage',
            'matriculation_mark' => 92.4, 'higher_secondary_board_id' => HigherSecondaryBoard::first()->id, 'higher_secondary_subject' => 'PCB',
            'higher_secondary_mark_type' => 'cgpa', 'higher_secondary_mark' => 9.4,
        ]);
        foreach (['kyc_photo' => 'image/jpeg', 'medical_certificate' => 'application/pdf', 'marksheet_10' => 'application/pdf', 'marksheet_12' => 'application/pdf'] as $type => $mime) {
            $path = UploadedFile::fake()->create("{$type}.bin", 50)->store("students/{$student->id}", 'local');
            StudentDocument::create([
                'student_id' => $student->id, 'institute_id' => $institute->id, 'document_type' => $type, 'file_path' => $path,
                'original_name' => "{$type}.pdf", 'mime_type' => $mime, 'size' => 50000, 'uploaded_at' => now(),
            ]);
        }

        app(OnboardingService::class)->openGates($student);

        return $student->fresh();
    }

    protected function verifyAllDocuments(Student $student): void
    {
        $this->actingAs($this->admin);
        $component = Livewire::test(DocumentVerificationComponent::class)->call('select', $student->id);
        foreach ($student->documents as $doc) {
            $component->call('verify', $doc->id);
        }
    }

    protected function approvedPayment(Student $student, float $amount): StudentPayment
    {
        $due = $student->dues()->firstOrFail();
        $this->actingAs($this->accounts);
        Livewire::test(StudentFeesComponent::class, ['student' => $student->id])
            ->set('pay_due_id', $due->id)
            ->set('pay_amount', $amount)
            ->set('pay_gateway_id', $this->cash->id)
            ->call('recordPayment')
            ->assertHasNoErrors();

        $payment = StudentPayment::where('student_id', $student->id)->latest('id')->firstOrFail();
        Livewire::test(PaymentVerificationComponent::class)->call('select', $payment->id)->call('approve');

        return $payment->fresh();
    }

    // ------------------------------------------------------------------ the whole flow

    public function test_complete_onboarding_workflow_to_active_student()
    {
        $student = $this->submittedStudent();

        // Gate 1 — admin verifies the 4 documents and approves the gate
        $this->actingAs($this->admin);
        $docs = Livewire::test(DocumentVerificationComponent::class)->assertSee($student->full_name)->call('select', $student->id);
        $docs->call('approveGate'); // blocked: nothing verified yet
        $this->assertFalse($student->fresh()->gateApproved(EnrollmentApproval::DOCUMENTS));
        foreach ($student->documents as $doc) {
            $docs->call('verify', $doc->id);
        }
        $docs->call('approveGate');
        $this->assertTrue($student->fresh()->gateApproved(EnrollmentApproval::DOCUMENTS));

        // Fee structure → dues
        $this->actingAs($this->accounts);
        Livewire::test(FeeStructureComponent::class)
            ->set('courseId', $this->course->id)
            ->call('create')
            ->set('fee_head', 'Admission fee')->set('amount', 15000)->set('due_days', 0)
            ->call('save')->assertHasNoErrors()
            ->call('create')
            ->set('fee_head', 'Lab & library fee')->set('amount', 6500)->set('due_days', 14)
            ->call('save')->assertHasNoErrors();

        $fees = Livewire::test(StudentFeesComponent::class, ['student' => $student->id])->assertSee('Generate from fee structure')->call('generateDues');
        $this->assertSame(2, $student->dues()->count());
        $this->assertSame('2026-10-15', $student->dues()->where('fee_head', 'Lab & library fee')->first()->due_date->toDateString());

        // GPay payment for the lab fee: UTR + screenshot, waits for verification
        $labDue = $student->dues()->where('fee_head', 'Lab & library fee')->first();
        $fees->set('pay_due_id', $labDue->id)
            ->assertSet('pay_amount', 6500.0)
            ->set('pay_gateway_id', $this->gpay->id)
            ->set('pay_reference', '627514903318')
            ->set('pay_upi', 'ananya.m@okaxis')
            ->set('pay_proof', UploadedFile::fake()->image('gpay.png'))
            ->call('recordPayment')
            ->assertHasNoErrors();
        $gpayPayment = StudentPayment::where('student_due_id', $labDue->id)->firstOrFail();
        $this->assertSame('pending_verification', $gpayPayment->status);
        Storage::disk('local')->assertExists($gpayPayment->proof_file_path);

        // Cash for the admission fee
        $admissionDue = $student->dues()->where('fee_head', 'Admission fee')->first();
        $fees->set('pay_due_id', $admissionDue->id)->set('pay_gateway_id', $this->cash->id)->call('recordPayment')->assertHasNoErrors();

        // Gate 2 is blocked while payments wait for verification
        Livewire::test(PaymentVerificationComponent::class)->set('tab', 'gate')->call('approveGate', $student->id);
        $this->assertFalse($student->fresh()->gateApproved(EnrollmentApproval::FEES));

        // Accounts approves both payments → receipts, dues cleared
        foreach (StudentPayment::where('student_id', $student->id)->get() as $payment) {
            Livewire::test(PaymentVerificationComponent::class)->call('select', $payment->id)->call('approve');
        }
        $gpayPayment->refresh();
        $this->assertSame('success', $gpayPayment->status);
        $this->assertMatchesRegularExpression('#^RCPT/\d{4}/\d{5}$#', $gpayPayment->receipt_number);
        $this->assertSame(0.0, $student->fresh()->outstandingAmount());
        $this->assertSame('cleared', $labDue->fresh()->status);

        // Gate 2 → ER number issued automatically
        Livewire::test(PaymentVerificationComponent::class)->set('tab', 'gate')->call('approveGate', $student->id);
        $student->refresh();
        $this->assertSame('er_issued', $student->status);
        $this->assertMatchesRegularExpression('/^ER-\d{4}-\d{5}$/', $student->er_number);
        $this->assertNotNull($student->erRequest);
        $this->assertNotNull($student->idCard);
        $this->assertTrue(NotificationLog::where('event_type', 'er_issued')->where('notifiable_id', $student->id)->exists());
        $this->assertTrue(NotificationLog::where('event_type', 'payment_received')->where('notifiable_id', $student->id)->exists());

        // TM: ER form printed → signed → archived; ID card printed → signed & issued
        $this->actingAs($this->tm);
        $enrollment = Livewire::test(StudentEnrollmentComponent::class, ['student' => $student->id]);
        $enrollment->call('formSigned'); // not printed yet → refused
        $this->assertSame('pending', $student->erRequest->fresh()->tm_signature_status);
        $enrollment->call('formPrinted')->call('formSigned')->call('formArchived');
        $this->assertSame('archived', $student->erRequest->fresh()->status);

        $enrollment->call('cardIssued'); // not printed yet → refused
        $this->assertSame('pending', $student->idCard->fresh()->tm_signature_status);
        $enrollment->call('cardPrinted')->call('cardIssued');
        $this->assertSame('issued', $student->idCard->fresh()->status);
        $this->assertSame('active', $student->fresh()->status);

        // Printables
        $this->get(route('admin.students.er-form', $student->id))->assertOk()->assertSee($student->er_number);
        $this->get(route('admin.students.id-card', $student->id))->assertOk()->assertSee($student->er_number);
        $this->actingAs($this->accounts);
        $this->get(route('admin.students.payments.receipt', $gpayPayment->id))->assertOk()->assertSee($gpayPayment->receipt_number)->assertSee('₹6,500.00');
        $this->get(route('admin.students.payments.proof', $gpayPayment->id))->assertOk();
    }

    public function test_students_list_shows_fee_and_gate_statuses_and_portal_payments_reach_the_fees_page()
    {
        CourseFee::create(['institute_id' => $this->inst->id, 'course_id' => $this->course->id, 'fee_head' => 'Admission fee', 'amount' => 15000, 'due_days' => 0, 'status' => true]);
        $student = $this->submittedStudent();

        // Accounts adds the course fees with "Generate from fee structure"
        $this->actingAs($this->accounts);
        Livewire::test(StudentFeesComponent::class, ['student' => $student->id])->call('generateDues')->assertSee('Admission fee');
        $this->assertSame(1, $student->dues()->count());

        // A payment submitted by the student on the portal appears on the admin Fees page
        $due = $student->dues()->first();
        $payment = app(\App\Services\FeeService::class)->recordPayment($due, $this->gpay, 15000, now(), '627514903318', null, null, 'Portal');
        Livewire::test(StudentFeesComponent::class, ['student' => $student->id])
            ->assertSee('627514903318')->assertSee('Pending verification');

        // Students list: fee status + both gates
        $html = $this->get(route('admin.students'))->assertOk()
            ->assertSee('Student')->assertSee('ER / Dates')->assertSee('Approvals')->assertDontSee('First Name')
            ->assertSee($student->email)->assertSee('Docs')->getContent();
        $this->assertStringContainsString('Unpaid', $html);
        $this->assertStringContainsString('to verify', $html);
        $this->assertStringContainsString('Pending', $html);

        Livewire::test(PaymentVerificationComponent::class)->call('select', $payment->id)->call('approve');
        $this->assertSame('Paid', strip_tags($student->fresh()->fee_status_html));
        $this->verifyAllDocuments($student);
        Livewire::test(DocumentVerificationComponent::class)->call('select', $student->id)->call('approveGate');
        $this->assertStringContainsString('Approved', $student->fresh()->documents_gate_html);
    }

    public function test_students_list_filters_search_and_sort()
    {
        $fees = app(\App\Services\FeeService::class);
        $onboarding = app(OnboardingService::class);

        $a = $this->submittedStudent();                     // Kappa institute, docs gate approved, fees paid
        $a->update(['first_name' => 'Aadhya', 'er_number' => 'ER-2099-00042']);
        $b = $this->submittedStudent();                     // Kappa institute, unpaid
        $b->update(['first_name' => 'Bala', 'status' => 'pending_docs']);
        $c = $this->submittedStudent($this->otherInst);     // other institute, no fees
        $c->update(['first_name' => 'Chitra']);

        $this->actingAs($this->accounts);
        $fees->addDue($a, 'Admission fee', 1000, '2026-10-05');
        $fees->addDue($b, 'Admission fee', 1000, '2026-10-05');
        $fees->approvePayment($fees->recordPayment($a->dues()->first(), $this->cash, 1000, now(), null, null, null, null));

        $this->actingAs($this->admin);
        foreach ($a->documents as $doc) {
            $onboarding->verifyDocument($doc);
        }
        $onboarding->approveGate($a->fresh(), EnrollmentApproval::DOCUMENTS);

        $super = User::create(['name' => 'Super', 'email' => uniqid() . '@staff.test', 'password' => bcrypt('x'), 'status' => true]);
        $super->assignRole('super-admin');
        $this->actingAs($super);

        $list = fn () => Livewire::test(\App\Http\Livewire\Admin\Students\StudentsComponent::class);
        $sees = function ($component, array $in, array $out) {
            foreach ($in as $s) { $component->assertSee($s->email); }
            foreach ($out as $s) { $component->assertDontSee($s->email); }
        };

        // Institute column + filter
        $sees($list()->assertSee('Institute')->set('institute', $this->otherInst->id), [$c], [$a, $b]);
        // Course, status
        $sees($list()->set('course', $this->course->id), [$a, $b, $c], []);
        $sees($list()->set('status', 'pending_docs'), [$b], [$a, $c]);
        // Approvals
        $sees($list()->set('docsGate', 'approved'), [$a], [$b, $c]);
        $sees($list()->set('docsGate', 'pending'), [$b, $c], [$a]);
        $sees($list()->set('feesGate', 'not_submitted'), [], [$a, $b, $c]);
        // Payment
        $sees($list()->set('payment', 'paid'), [$a], [$b, $c]);
        $sees($list()->set('payment', 'unpaid'), [$b], [$a, $c]);
        $sees($list()->set('payment', 'none'), [$c], [$a, $b]);
        // Search (name, ER, course) + chips + clear all
        $sees($list()->set('search', '00042')->assertSee('Search: “00042”'), [$a], [$b, $c]);
        $sees($list()->set('search', 'Chitra')->set('status', 'pending_docs')->call('clearAll'), [$a, $b, $c], []);
        // Summary card filter
        $sees($list()->call('quickFilter', 'unpaid'), [$b], [$a, $c]);
        // Sort by name
        $list()->call('sortBy', 'first_name')->assertSeeInOrder([$a->email, $b->email, $c->email]);
        $list()->call('sortBy', 'not_a_column')->assertSet('sortField', 'id');

        // Institute users: no institute column/filter, only their own students
        $this->actingAs($this->admin);
        $own = $list();
        $sees($own, [$a, $b], [$c]);
        $own->assertDontSee('All institutes');
    }

    public function test_admin_created_student_gets_course_fees_automatically()
    {
        CourseFee::create(['institute_id' => $this->inst->id, 'course_id' => $this->course->id, 'fee_head' => 'Admission fee', 'amount' => 15000, 'due_days' => 0, 'status' => true]);
        $religion = Religion::where('name', 'Hindu')->firstOrFail();
        $this->actingAs($this->admin);

        Livewire::test(StudentOnboardingComponent::class)
            ->set('course_id', $this->course->id)->set('joining_date', now()->toDateString())
            ->set('first_name', 'Auto')->set('last_name', 'Fees')->set('dob', '2007-01-01')->set('gender', 'male')
            ->set('qualification_id', Qualification::first()->id)->set('religion_id', $religion->id)
            ->set('category_id', Category::where('religion_id', $religion->id)->first()->id)
            ->set('email', uniqid() . '@mail.test')->set('phone', '+919800011111')->set('emergency_contact', '+919800022222')
            ->call('saveBasic')->assertHasNoErrors();

        $this->assertSame(['Admission fee'], Student::where('first_name', 'Auto')->first()->dues()->pluck('fee_head')->all());
    }

    public function test_document_queue_keeps_verified_students_and_highlights_pending_ones()
    {
        $done = $this->submittedStudent();
        $done->update(['first_name' => 'Verified', 'onboarding_deadline' => now()->addDays(2)]);
        $waiting = $this->submittedStudent();
        $waiting->update(['first_name' => 'Waiting', 'onboarding_deadline' => now()->addDays(20)]);

        $this->verifyAllDocuments($done);
        Livewire::test(DocumentVerificationComponent::class)->call('select', $done->id)->call('approveGate');

        // Default "All students": both shown; the one needing review is highlighted and listed first
        $html = Livewire::test(DocumentVerificationComponent::class)
            ->assertSet('tab', 'all')
            ->assertSee('All students')
            ->assertSeeInOrder(['Waiting Menon', 'Verified Menon'])
            ->payload['effects']['html'];

        $this->assertStringContainsString('needs-review', $html);   // highlighted row
        $this->assertStringContainsString('4 to review', $html);
        $this->assertStringContainsString('dv-state-ok', $html);    // verified student shown with a normal row + green chip

        // "Pending review" still lists only the students waiting for a decision
        Livewire::test(DocumentVerificationComponent::class)->set('tab', 'pending')->assertSee('Waiting Menon')->assertDontSee('Verified Menon');
    }

    public function test_document_queue_is_paged_with_students_needing_review_first()
    {
        // 21 students already verified (gate approved), earliest deadlines
        for ($i = 1; $i <= 21; $i++) {
            $s = $this->submittedStudent();
            $s->update(['first_name' => 'Done' . str_pad($i, 2, '0', STR_PAD_LEFT), 'onboarding_deadline' => now()->addDays($i)]);
            $s->documents()->update(['verification_status' => 'verified']);
            $s->approvals()->where('gate', EnrollmentApproval::DOCUMENTS)->update(['status' => 'approved']);
        }
        // 1 student needing review, latest deadline
        $waiting = $this->submittedStudent();
        $waiting->update(['first_name' => 'Waiting', 'onboarding_deadline' => now()->addDays(60)]);

        $this->actingAs($this->admin);
        $page = DocumentVerificationComponent::PAGE_NAME;

        // Page 1: 20 rows, the student needing review comes first despite the latest deadline
        $queue = Livewire::test(DocumentVerificationComponent::class)
            ->assertSee('1–20 of 22')->assertSee('Page 1 of 2')
            ->assertSeeInOrder(['Waiting Menon', 'Done01 Menon'])
            ->assertDontSee('Done20 Menon');

        // Next / previous
        $queue->call('nextPage', $page)->assertSee('21–22 of 22')->assertSee('Done20 Menon')->assertSee('Done21 Menon')->assertDontSee('Waiting Menon');
        $queue->call('previousPage', $page)->assertSee('Waiting Menon');

        // Search and changing the status card go back to page 1
        $queue->call('nextPage', $page)->set('search', 'Done2')->assertSee('Done20 Menon')->assertDontSee('Page 2 of');
        Livewire::test(DocumentVerificationComponent::class)->call('nextPage', $page)->set('tab', 'pending')
            ->assertSee('Waiting Menon')->assertDontSee('Done01 Menon');
    }

    // ------------------------------------------------------------------ 2.2 document rules

    public function test_rejected_document_must_be_reuploaded_and_keeps_the_reason()
    {
        $student = $this->submittedStudent();
        $marksheet = $student->documents()->where('document_type', 'marksheet_10')->first();

        $this->actingAs($this->admin);
        Livewire::test(DocumentVerificationComponent::class)->call('select', $student->id)
            ->call('reject', $marksheet->id)
            ->assertHasErrors(['remarks.' . $marksheet->id => 'required'])
            ->set("remarks.{$marksheet->id}", 'The bottom of the page is cut off.')
            ->call('reject', $marksheet->id)
            ->assertHasNoErrors();

        $this->assertSame('rejected', $marksheet->fresh()->verification_status);
        $this->assertSame('pending_docs', $student->fresh()->status);
        $this->assertTrue(NotificationLog::where('event_type', 'document_rejected')->exists());

        // Re-upload from the onboarding page
        Livewire::test(StudentOnboardingComponent::class, ['student' => $student->id])
            ->set('upload_marksheet_10', UploadedFile::fake()->create('full.pdf', 100, 'application/pdf'))
            ->call('uploadDocument', 'marksheet_10')
            ->assertHasNoErrors();

        $new = $student->documents()->where('document_type', 'marksheet_10')->first();
        $this->assertSame('pending', $new->verification_status);
        $this->assertSame('The bottom of the page is cut off.', $new->previous_rejection);
        $this->assertSame('pending_approval', $student->fresh()->status);
    }

    public function test_gate_rejection_returns_application_and_resubmission_reopens_gate()
    {
        $student = $this->submittedStudent();
        $this->actingAs($this->admin);

        Livewire::test(DocumentVerificationComponent::class)->call('select', $student->id)
            ->call('rejectGate')->assertHasErrors(['gateRemarks' => 'required'])
            ->set('gateRemarks', 'Photo does not match the ID proof.')
            ->call('rejectGate');

        $this->assertSame('rejected', $student->fresh()->status);
        $this->assertSame('rejected', $student->gate(EnrollmentApproval::DOCUMENTS)->status);

        Livewire::test(StudentOnboardingComponent::class, ['student' => $student->id])->call('submit');
        $this->assertSame('pending_approval', $student->fresh()->status);
        $this->assertSame('pending', $student->fresh()->gate(EnrollmentApproval::DOCUMENTS)->status);
    }

    public function test_document_change_after_gate_one_reopens_it()
    {
        $student = $this->submittedStudent();
        $this->verifyAllDocuments($student);
        Livewire::test(DocumentVerificationComponent::class)->call('select', $student->id)->call('approveGate');
        $this->assertTrue($student->fresh()->gateApproved(EnrollmentApproval::DOCUMENTS));

        Livewire::test(StudentOnboardingComponent::class, ['student' => $student->id])
            ->set('upload_kyc_photo', UploadedFile::fake()->image('new.jpg'))
            ->call('uploadDocument', 'kyc_photo');

        $this->assertFalse($student->fresh()->gateApproved(EnrollmentApproval::DOCUMENTS));
    }

    // ------------------------------------------------------------------ 2.3 payment rules

    public function test_payment_validation_rejection_and_part_payment()
    {
        $student = $this->submittedStudent();
        $this->actingAs($this->accounts);
        app(\App\Services\FeeService::class)->addDue($student, 'Semester 1 fee', 42000, '2026-10-15');
        $due = $student->dues()->first();

        $fees = Livewire::test(StudentFeesComponent::class, ['student' => $student->id]);

        // More than the balance; GPay without UTR / screenshot
        $fees->set('pay_due_id', $due->id)->set('pay_amount', 50000)->set('pay_gateway_id', $this->gpay->id)
            ->call('recordPayment')
            ->assertHasErrors(['pay_amount' => 'max', 'pay_reference' => 'required', 'pay_proof' => 'required']);

        // Part payment by cash (no reference needed)
        $fees->set('pay_amount', 20000)->set('pay_gateway_id', $this->cash->id)->call('recordPayment')->assertHasNoErrors();
        $payment = StudentPayment::where('student_id', $student->id)->first();

        // Reject needs a reason; rejected payment leaves the due untouched
        $queue = Livewire::test(PaymentVerificationComponent::class)->call('select', $payment->id);
        $queue->call('reject')->assertHasErrors(['reason' => 'required']);
        $queue->set('reason', 'Cash not received at the counter.')->call('reject')->assertHasNoErrors();
        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('pending', $due->fresh()->status);

        // Approve a new part payment → partial
        $this->approvedPayment($student->fresh(), 20000);
        $due->refresh();
        $this->assertSame('partial', $due->status);
        $this->assertSame(22000.0, $due->balance);
    }

    public function test_waive_and_delete_rules_and_gate_two_blockers()
    {
        $student = $this->submittedStudent();
        $this->verifyAllDocuments($student);
        Livewire::test(DocumentVerificationComponent::class)->call('select', $student->id)->call('approveGate');

        $this->actingAs($this->accounts);
        $service = app(OnboardingService::class);
        $this->assertSame('No fees are set up for this student.', $service->gateBlocker($student->fresh(), EnrollmentApproval::FEES));

        $fees = app(\App\Services\FeeService::class);
        $fees->addDue($student, 'Admission fee', 15000, '2026-10-05');
        $fees->addDue($student, 'Scholarship-covered fee', 5000, '2026-10-05');
        $this->assertStringStartsWith('Outstanding dues', $service->gateBlocker($student->fresh(), EnrollmentApproval::FEES));

        $this->approvedPayment($student->fresh(), 15000);
        $scholarship = $student->dues()->where('fee_head', 'Scholarship-covered fee')->first();

        // Waive needs a reason; then the gate can be approved
        Livewire::test(StudentFeesComponent::class, ['student' => $student->id])
            ->call('confirmWaive', $scholarship->id)->call('waive')->assertHasErrors(['waiveReason' => 'required'])
            ->set('waiveReason', 'Covered by merit scholarship')->call('waive');
        $this->assertSame('waived', $scholarship->fresh()->status);

        // A paid due cannot be deleted
        $paid = $student->dues()->where('fee_head', 'Admission fee')->first();
        Livewire::test(StudentFeesComponent::class, ['student' => $student->id])->call('deleteDue', $paid->id);
        $this->assertNotSoftDeleted($paid);

        $this->assertNull($service->gateBlocker($student->fresh(), EnrollmentApproval::FEES));
    }

    // ------------------------------------------------------------------ access

    public function test_role_access_to_queues_and_pages()
    {
        $student = $this->submittedStudent();
        $other = $this->submittedStudent($this->otherInst);

        // Institute Admin: documents + ER/ID; not the Accounts queue
        $this->actingAs($this->admin);
        $this->get(route('admin.onboarding.documents'))->assertOk();
        $this->get(route('admin.onboarding.documents', ['student' => $student->id]))->assertOk()->assertSee('Gate 1');
        $this->get(route('admin.onboarding.enrollment'))->assertOk();
        $this->get(route('admin.onboarding.fee-structure'))->assertOk();
        $this->get(route('admin.onboarding.payments'))->assertForbidden();
        $this->get(route('admin.students.fees', $student->id))->assertOk();
        $this->get(route('admin.students.enrollment', $student->id))->assertOk();
        $this->get(route('admin.students.fees', $other->id))->assertNotFound();

        // Accounts: payments + fees; not document verification or ER/ID actions
        $this->actingAs($this->accounts);
        $this->get(route('admin.onboarding.payments'))->assertOk();
        $this->get(route('admin.onboarding.payments', ['tab' => 'gate']))->assertOk();
        $this->get(route('admin.onboarding.documents'))->assertForbidden();
        $this->get(route('admin.onboarding.enrollment'))->assertForbidden();
        $this->get(route('admin.students.edit', $student->id))->assertOk(); // view only

        // TM: ER & ID cards only
        $this->actingAs($this->tm);
        $this->get(route('admin.onboarding.enrollment'))->assertOk();
        $this->get(route('admin.onboarding.payments'))->assertForbidden();
        $this->get(route('admin.students.fees', $student->id))->assertForbidden();

        // Accounts cannot act on the ER form
        $this->actingAs($this->accounts);
        Livewire::test(StudentEnrollmentComponent::class, ['student' => $student->id])->call('formPrinted')->assertForbidden();

        // Printables are only there when issued
        $this->get(route('admin.students.er-form', $student->id))->assertNotFound();
    }

    public function test_queue_pages_render_with_data()
    {
        $student = $this->submittedStudent();
        $this->verifyAllDocuments($student);
        Livewire::test(DocumentVerificationComponent::class)->call('select', $student->id)->call('approveGate');
        app(\App\Services\FeeService::class)->addDue($student, 'Admission fee', 1000, '2026-10-05');
        $this->approvedPayment($student->fresh(), 1000);

        $this->actingAs($this->accounts);
        foreach (['pending', 'approved', 'rejected', 'gate'] as $tab) {
            Livewire::test(PaymentVerificationComponent::class)->set('tab', $tab)->assertOk();
        }
        $payment = StudentPayment::where('student_id', $student->id)->where('status', 'success')->first();
        Livewire::test(PaymentVerificationComponent::class)->set('tab', 'approved')->call('select', $payment->id)
            ->assertSee($payment->receipt_number)->assertSee('Print receipt');

        Livewire::test(PaymentVerificationComponent::class)->set('tab', 'gate')->call('approveGate', $student->id);
        $this->actingAs($this->tm);
        foreach (['form', 'card', 'done'] as $tab) {
            Livewire::test(EnrollmentQueueComponent::class)->set('tab', $tab)->assertOk();
        }
        Livewire::test(EnrollmentQueueComponent::class)->assertSee($student->fresh()->er_number);
    }
}
