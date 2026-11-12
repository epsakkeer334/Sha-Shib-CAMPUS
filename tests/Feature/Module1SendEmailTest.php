<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Emails\SendEmailComponent;
use App\Http\Livewire\Admin\Emails\SentEmailsComponent;
use App\Http\Livewire\Admin\Settings\EmailTemplatesComponent;
use App\Models\Admin\AuditTrail;
use App\Models\Admin\Batch;
use App\Models\Admin\Category;
use App\Models\Admin\Course;
use App\Models\Admin\EmailCampaign;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteCourse;
use App\Models\Admin\NotificationLog;
use App\Models\Admin\Qualification;
use App\Models\Admin\Religion;
use App\Models\Admin\Student;
use App\Models\Admin\StudentDue;
use App\Models\User;
use App\Services\BulkEmailService;
use App\Support\Settings;
use Database\Seeders\admin\MasterDataSeeder;
use Database\Seeders\admin\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Communication ▸ Send Email / Sent Emails: one email to chosen students, a batch, a course or an institute
 * (student, parent or both), Super Admin also to institute staff; every email logged and linked to the send.
 * SMTP2GO is faked and nothing is delivered. Runs on the *_testing database, rolled back.
 */
class Module1SendEmailTest extends TestCase
{
    use DatabaseTransactions;

    protected Institute $inst;
    protected Institute $other;
    protected Course $course;
    protected Batch $batchA;
    protected Batch $batchB;
    protected User $super;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(MasterDataSeeder::class);
        Settings::flush();

        config(['services.smtp2go.api_key' => 'api-TEST00001234', 'services.smtp2go.sender_email' => 'no-reply@camp.test']);
        Http::preventStrayRequests();
        Http::fake(['api.smtp2go.com/*' => Http::response(['data' => ['succeeded' => 1, 'failed' => 0, 'failures' => [], 'email_id' => 'BULK-OK']], 200)]);
        Settings::set(['mail.enabled' => true]);
        Bus::fake();

        $this->inst = $this->institute('SND');
        $this->other = $this->institute('OTR');
        $this->course = Course::create(['name' => 'AME B1.1', 'code' => 'SB1-' . uniqid(), 'duration_months' => 48, 'total_semesters' => 8, 'status' => true]);
        foreach ([$this->inst, $this->other] as $i) {
            InstituteCourse::create(['institute_id' => $i->id, 'course_id' => $this->course->id, 'status' => true]);
        }
        $this->batchA = Batch::create(['institute_id' => $this->inst->id, 'course_id' => $this->course->id, 'name' => 'June 2026', 'code' => 'SND-A-' . random_int(100, 999), 'status' => true]);
        $this->batchB = Batch::create(['institute_id' => $this->inst->id, 'course_id' => $this->course->id, 'name' => 'Jan 2027', 'code' => 'SND-B-' . random_int(100, 999), 'status' => true]);

        $this->super = $this->makeUser('super-admin');
        $this->admin = $this->makeUser('institute-admin', $this->inst);
    }

    protected function tearDown(): void
    {
        Settings::flush();
        parent::tearDown();
    }

    protected function institute(string $prefix): Institute
    {
        return Institute::create([
            'name' => "{$prefix} Aviation " . uniqid(), 'established_year' => 2005, 'code' => Institute::generateCode($prefix, 2005),
            'email' => uniqid() . '@inst.test', 'phone' => (string) random_int(1000000000, 9999999999), 'status' => true,
        ]);
    }

    protected function makeUser(string $role, ?Institute $institute = null, array $extra = []): User
    {
        $user = User::create($extra + ['name' => ucfirst($role) . ' User', 'email' => uniqid($role) . '@user.test', 'password' => bcrypt('password'), 'institute_id' => $institute?->id, 'status' => true]);
        $user->assignRole($role);

        return $user;
    }

    protected function student(array $override = []): Student
    {
        $religion = Religion::where('name', 'Hindu')->firstOrFail();

        return Student::create(array_merge([
            'institute_id' => $this->inst->id, 'course_id' => $this->course->id, 'batch_id' => $this->batchA->id,
            'first_name' => 'Ravi', 'last_name' => 'Kumar', 'dob' => '2006-01-01', 'gender' => 'male', 'qualification_id' => Qualification::first()->id,
            'email' => uniqid('st') . '@mail.test', 'phone' => '+9198' . random_int(10000000, 99999999), 'emergency_contact' => '+919800000002',
            'religion_id' => $religion->id, 'category_id' => Category::where('religion_id', $religion->id)->first()->id,
            'joining_date' => '2026-06-01', 'onboarding_deadline' => '2026-07-01', 'status' => 'active',
        ], $override));
    }

    protected function compose(User $as)
    {
        return Livewire::actingAs($as)->test(SendEmailComponent::class);
    }

    public function test_institute_admin_sends_to_a_batch_with_parents_and_everything_is_logged(): void
    {
        $anu = $this->student(['first_name' => 'Anu', 'parent_name' => 'Mohan Nair', 'parent_email' => 'mohan@parent.test']);
        $this->student(['first_name' => 'Binu', 'parent_email' => null]); // parent has no email
        $this->student(['first_name' => 'Chinnu', 'email' => 'shared@mail.test', 'parent_email' => 'SHARED@mail.test']); // same address twice
        $this->student(['first_name' => 'Other', 'batch_id' => $this->batchB->id]); // other batch
        $this->student(['first_name' => 'Elsewhere', 'institute_id' => $this->other->id, 'batch_id' => null]); // other institute

        $component = $this->compose($this->admin)
            ->assertSee('Announcement to students')->assertSee('Fee payment reminder')->assertDontSee('Announcement to staff')
            ->call('toggle', 'batches', $this->batchA->id)
            ->call('$set', 'sendTo', 'both')
            ->set('subject', 'Hello {first_name}')
            ->set('body', "Dear {recipient_name},\n\nClasses for batch {batch} start Monday.")
            ->assertSet('info.matched', 3)
            ->assertSet('info.count', 4)        // Anu + parent, Binu, Chinnu once
            ->assertSet('info.no_email', 1)     // Binu's parent
            ->assertSet('info.duplicate', 1)    // Chinnu's parent = same address
            ->call('review')->assertHasNoErrors()->assertSet('confirming', true)
            ->call('send')->assertRedirect();

        $campaign = EmailCampaign::latest('id')->first();
        $this->assertSame($this->inst->id, $campaign->institute_id);
        $this->assertSame('students', $campaign->audience);
        $this->assertSame('both', $campaign->send_to);
        $this->assertSame(4, $campaign->recipients_total);
        $this->assertSame(1, $campaign->skipped_no_email);
        $this->assertSame(1, $campaign->skipped_duplicate);
        $this->assertStringContainsString('Batch ' . $this->batchA->code, $campaign->filters_summary);

        $logs = NotificationLog::where('email_campaign_id', $campaign->id)->get();
        $this->assertCount(4, $logs);
        $this->assertTrue($logs->every(fn ($l) => $l->event_type === BulkEmailService::EVENT && $l->institute_id === $this->inst->id && $l->status === 'pending'));
        Bus::assertDispatchedAfterResponseTimes(\App\Jobs\SendEmailJob::class, 4);

        // Each copy is personal: student, and the parent addressed by name about the student
        $toAnu = $logs->firstWhere('recipient', $anu->email);
        $this->assertSame('Hello Anu', $toAnu->subject);
        $this->assertStringContainsString($this->batchA->code, $toAnu->payload['message']);
        $toParent = $logs->firstWhere('recipient', 'mohan@parent.test');
        $this->assertSame('Hello Mohan', $toParent->subject);
        $this->assertSame('Mohan Nair', $toParent->payload['to_name']);
        $this->assertSame($anu->id, (int) $toParent->notifiable_id);

        $audit = AuditTrail::where('module', 'Emails')->latest('id')->first();
        $this->assertStringStartsWith('Sent email "Hello {first_name}" to 4 receivers', data_get($audit->meta, 'description'));
    }

    public function test_who_receives_it_folds_to_a_summary(): void
    {
        $this->student(['first_name' => 'Anu']);

        $this->compose($this->admin)
            ->call('toggle', 'batches', $this->batchA->id)
            ->set('receiversOpen', false)
            ->assertSee($this->batchA->code)->assertSee('Any status')->assertSee('Email to student')
            ->assertDontSee('Done, collapse')
            ->set('receiversOpen', true)
            ->assertSee('Done, collapse')
            ->assertSet('batches', [$this->batchA->id]); // folding keeps the choices
    }

    public function test_institute_admin_cannot_reach_other_institutes_or_staff(): void
    {
        $mine = $this->student(['first_name' => 'Mine']);
        $theirs = $this->student(['first_name' => 'Theirs', 'institute_id' => $this->other->id, 'batch_id' => null]);
        $this->makeUser('accounts', $this->inst);

        $this->compose($this->admin)
            ->set('audience', 'staff')->assertSet('audience', 'students')
            ->set('institutes', [$this->other->id])->assertSet('institutes', [])
            ->assertSet('info.matched', 1)
            ->set('mode', 'selected')
            ->call('pick', $theirs->id)->assertSet('selected', [])
            ->call('pick', $mine->id)->assertSet('selected', [$mine->id])
            ->set('search', 'Theirs')->assertDontSee($theirs->email)
            ->set('search', 'Mine')->assertSee($mine->email);

        // Not allowed for roles without emails.send
        $this->actingAs($this->makeUser('accounts', $this->inst))->get(route('admin.emails.send'))->assertForbidden();
    }

    public function test_super_admin_sends_to_institute_staff_by_role_with_a_template(): void
    {
        $tm = $this->makeUser('training-manager', $this->inst, ['name' => 'Tara Manager']);
        $this->makeUser('accounts', $this->inst);
        $this->makeUser('training-manager', $this->inst, ['status' => false]); // inactive
        $otherTm = $this->makeUser('training-manager', $this->other);

        $this->compose($this->super)
            ->set('audience', 'staff')
            ->assertSet('templateKey', 'announcement_staff')
            ->call('useTemplate', 'meeting_staff')->assertSee('Staff meeting')
            ->call('toggle', 'institutes', $this->inst->id)
            ->call('toggle', 'roles', 'training-manager')
            ->assertSet('info.count', 1)
            ->set('subject', 'Meeting for {role}s')
            ->set('body', "Dear {first_name},\n\nMeeting at 10 AM.")
            ->call('review')->call('send')->assertRedirect();

        $campaign = EmailCampaign::latest('id')->first();
        $this->assertSame('staff', $campaign->audience);
        $this->assertSame('meeting_staff', $campaign->template_key);
        $log = $campaign->logs()->first();
        $this->assertSame($tm->email, $log->recipient);
        $this->assertSame('Meeting for Training Manager (TM)s', $log->subject);
        $this->assertSame('meeting_staff', $log->template_key);
        $this->assertSame(User::class, $log->notifiable_type);
        $this->assertFalse($campaign->logs()->where('recipient', $otherTm->email)->exists());
    }

    public function test_unpaid_fees_filter_fills_in_the_outstanding_amount(): void
    {
        $owes = $this->student(['first_name' => 'Owes']);
        $this->student(['first_name' => 'Paid']);
        StudentDue::create(['institute_id' => $this->inst->id, 'student_id' => $owes->id, 'fee_head' => 'Tuition', 'amount_due' => 18500, 'amount_paid' => 0, 'due_date' => '2026-07-01', 'status' => 'pending']);

        $this->compose($this->admin)
            ->call('useTemplate', 'fee_reminder_students')
            ->set('fees', 'unpaid')
            ->assertSet('info.count', 1)
            ->assertSee('Owes Kumar')
            ->call('review')->call('send');

        $log = EmailCampaign::latest('id')->first()->logs()->first();
        $this->assertSame($owes->email, $log->recipient);
        $this->assertStringContainsString(money_inr(18500), $log->payload['message']);
        $this->assertSame('Fee reminder — Owes Kumar', $log->subject);
    }

    public function test_nothing_is_sent_without_confirming_or_without_receivers(): void
    {
        $this->compose($this->admin)
            ->set('subject', 'Hi')->set('body', 'Text')
            ->assertSet('info.count', 0)
            ->call('review')->assertHasErrors('audience')->assertSet('confirming', false);

        $this->student();
        $this->compose($this->admin)
            ->set('subject', 'Hi')->set('body', 'Text')
            ->call('send')->assertSet('confirming', true)   // first click only asks
            ->set('body', 'Changed')->assertSet('confirming', false); // editing cancels the question

        $this->assertSame(0, EmailCampaign::count());
    }

    public function test_test_copy_uses_the_first_receivers_details_and_waits_for_smtp2go(): void
    {
        $this->student(['first_name' => 'Zara']);

        $this->compose($this->admin)
            ->set('subject', 'Note for {first_name}')->set('body', 'Hello {student_name}')
            ->set('testEmail', 'me@test.test')
            ->call('sendTest')
            ->assertSet('testResult.ok', true);

        $log = NotificationLog::where('event_type', BulkEmailService::TEST_EVENT)->latest('id')->first();
        $this->assertSame('sent', $log->status);
        $this->assertSame('me@test.test', $log->recipient);
        $this->assertSame('[Test] Note for Zara', $log->subject);
        $this->assertNull($log->email_campaign_id);
        $this->assertSame(0, EmailCampaign::count());
    }

    public function test_sent_emails_shows_own_sends_with_receiver_status_and_resends_failed(): void
    {
        $s1 = $this->student(['first_name' => 'One']);
        $this->student(['first_name' => 'Two']);
        $this->compose($this->admin)->set('subject', 'Mine')->set('body', 'Text')->call('review')->call('send');
        $mine = EmailCampaign::latest('id')->first();

        $this->student(['first_name' => 'Far', 'institute_id' => $this->other->id, 'batch_id' => null]);
        $this->compose($this->super)->call('toggle', 'institutes', $this->other->id)->set('subject', 'Theirs')->set('body', 'Text')->call('review')->call('send');
        $theirs = EmailCampaign::latest('id')->first();
        $this->assertSame($this->other->id, $theirs->institute_id);

        $mine->logs()->where('recipient', $s1->email)->update(['status' => 'failed', 'error' => 'Mailbox full']);

        Livewire::actingAs($this->admin)->test(SentEmailsComponent::class)
            ->assertSee('Mine')->assertDontSee('Theirs')
            ->call('open', $mine->id)
            ->assertSee('One Kumar')->assertSee('Mailbox full')->assertSee('Send failed again')
            ->call('retryFailed')->assertDispatchedBrowserEvent('show-toast');

        $this->assertSame('pending', $mine->logs()->where('recipient', $s1->email)->first()->status);

        // Another institute's send cannot be opened
        Livewire::actingAs($this->admin)->test(SentEmailsComponent::class)->set('campaign', (string) $theirs->id)->assertSet('campaign', '');
        Livewire::actingAs($this->super)->test(SentEmailsComponent::class)->assertSee('Mine')->assertSee('Theirs');

        // "Use again" opens Send Email with the same wording
        $this->actingAs($this->admin)->get(route('admin.emails.send', ['from' => $mine->id]))->assertOk();
        Livewire::withQueryParams(['from' => $mine->id])->actingAs($this->admin)->test(SendEmailComponent::class)
            ->assertSet('subject', 'Mine')->assertSet('info.count', 2);
        Livewire::withQueryParams(['from' => $theirs->id])->actingAs($this->admin)->test(SendEmailComponent::class)
            ->assertNotSet('subject', 'Theirs');
    }

    public function test_staff_templates_are_hidden_from_institute_admins_on_email_templates(): void
    {
        Livewire::actingAs($this->admin)->test(EmailTemplatesComponent::class)
            ->assertSee('Announcement to students')->assertDontSee('Announcement to staff')
            ->call('select', 'announcement_staff')->assertNotSet('selectedKey', 'announcement_staff');

        Livewire::actingAs($this->super)->test(EmailTemplatesComponent::class)
            ->assertSee('Announcement to staff')
            ->call('select', 'announcement_staff')->assertSet('selectedKey', 'announcement_staff')->assertSee('Sent to institute staff');
    }
}
