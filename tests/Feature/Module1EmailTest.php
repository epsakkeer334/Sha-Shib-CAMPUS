<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Notifications\NotificationLogComponent;
use App\Http\Livewire\Admin\Settings\EmailTemplatesComponent;
use App\Jobs\SendEmailJob;
use App\Models\Admin\Category;
use App\Models\Admin\Course;
use App\Models\Admin\EmailTemplate;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteEmailSetting;
use App\Models\Admin\NotificationLog;
use App\Models\Admin\Qualification;
use App\Models\Admin\Religion;
use App\Models\Admin\Student;
use App\Models\User;
use App\Services\EmailService;
use App\Support\BackgroundJobs;
use App\Support\Settings;
use Database\Seeders\admin\MasterDataSeeder;
use Database\Seeders\admin\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SMTP2GO email service: templates, background sending, log, retry, permissions.
 * SMTP2GO is always faked (no real request leaves the test). Runs on the *_testing database, rolled back.
 */
class Module1EmailTest extends TestCase
{
    use DatabaseTransactions;

    protected User $super;
    protected Institute $instA;
    protected Institute $instB;
    protected User $adminA;

    /** What the faked SMTP2GO API answers: [body, HTTP status]. */
    protected array $smtpReply = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(MasterDataSeeder::class);
        Settings::flush();

        config(['services.smtp2go.api_key' => 'api-TEST00001234', 'services.smtp2go.sender_email' => 'no-reply@camp.test', 'services.smtp2go.sender_name' => 'Sha-Shib CAMPUS', 'queue.default' => 'sync']);
        Http::preventStrayRequests();
        Http::fake(['api.smtp2go.com/*' => fn () => Http::response($this->smtpReply[0], $this->smtpReply[1])]);
        $this->smtp2goAccepts();
        Settings::set(['mail.enabled' => true]);

        $this->instA = $this->institute('Alpha');
        $this->instB = $this->institute('Beta');
        $this->super = $this->makeUser('super-admin');
        $this->adminA = $this->makeUser('institute-admin', $this->instA);
    }

    protected function tearDown(): void
    {
        Settings::flush();
        parent::tearDown();
    }

    protected function smtp2goAccepts(): void
    {
        $this->smtpReply = [['request_id' => 'r1', 'data' => ['succeeded' => 1, 'failed' => 0, 'failures' => [], 'email_id' => '1abcDE-OK']], 200];
    }

    protected function institute(string $name): Institute
    {
        return Institute::create([
            'name' => "{$name} Aviation " . uniqid(), 'established_year' => 2004, 'code' => Institute::generateCode(strtoupper(substr($name, 0, 3)), 2004),
            'email' => strtolower($name) . uniqid() . '@inst.test', 'phone' => (string) random_int(1000000000, 9999999999), 'status' => true,
        ]);
    }

    protected function makeUser(string $role, ?Institute $institute = null): User
    {
        $user = User::create(['name' => ucfirst($role) . ' User', 'email' => uniqid($role) . '@user.test', 'password' => bcrypt('password'), 'institute_id' => $institute?->id, 'status' => true]);
        $user->assignRole($role);

        return $user;
    }

    protected function student(Institute $institute, array $attrs = []): Student
    {
        $religion = Religion::where('name', 'Hindu')->firstOrFail();
        $course = Course::create(['name' => 'Aircraft Maintenance', 'code' => 'EM-' . substr(uniqid(), -5), 'duration_months' => 24, 'total_semesters' => 4, 'period_months' => 6, 'status' => true]);

        return Student::create($attrs + [
            'institute_id' => $institute->id, 'course_id' => $course->id, 'first_name' => 'Ria', 'last_name' => 'Mailer', 'dob' => '2005-01-01', 'gender' => 'female',
            'qualification_id' => Qualification::first()->id, 'email' => uniqid('st') . '@mail.test', 'phone' => '+919800000041', 'emergency_contact' => '+919800000042',
            'religion_id' => $religion->id, 'category_id' => Category::where('religion_id', $religion->id)->first()->id,
            'joining_date' => '2025-06-01', 'onboarding_deadline' => '2025-07-01', 'status' => 'draft',
        ]);
    }

    protected function emails(): EmailService
    {
        return app(EmailService::class);
    }

    public function test_template_email_is_logged_then_sent_in_the_background(): void
    {
        $student = $this->student($this->instA);
        InstituteEmailSetting::create(['institute_id' => $this->instA->id, 'sender_name' => 'Alpha Admissions', 'reply_to' => 'admissions@alpha.test']);

        $log = $this->emails()->toStudent('document_rejected', $student, ['document' => '10th Marksheet', 'reason' => 'Scan is blurred']);

        // Logged straight away, not sent yet: the user's request does not wait for SMTP2GO
        $this->assertSame('pending', $log->status);
        $this->assertSame('document_rejected', $log->template_key);
        $this->assertSame('Action needed: re-upload your 10th Marksheet', $log->subject);
        $this->assertSame($student->email, $log->recipient);
        Http::assertNothingSent();

        $this->app->terminate(); // the response has gone out → the background job runs

        $log->refresh();
        $this->assertSame('sent', $log->status);
        $this->assertSame('1abcDE-OK', $log->provider_message_id);
        $this->assertSame(1, $log->attempts);
        Http::assertSent(function ($request) use ($student) {
            return $request->url() === 'https://api.smtp2go.com/v3/email/send'
                && $request->hasHeader('X-Smtp2go-Api-Key', 'api-TEST00001234')
                && $request['sender'] === '"Alpha Admissions" <no-reply@camp.test>'
                && $request['to'] === ['"Ria Mailer" <' . $student->email . '>']
                && $request['custom_headers'] === [['header' => 'Reply-To', 'value' => 'admissions@alpha.test']]
                && str_contains($request['html_body'], 'Scan is blurred')
                && str_contains($request['html_body'], 'brand/logo-white.png')
                && str_contains($request['text_body'], 'Dear Ria');
        });
    }

    public function test_jobs_run_after_the_response_or_on_the_emails_queue(): void
    {
        $student = $this->student($this->instA);

        Bus::fake();
        $this->emails()->toStudent('document_accepted', $student, ['document' => 'Photo']);
        Bus::assertDispatchedAfterResponse(SendEmailJob::class);

        // Queue worker switched on and the scheduler running: on the database "emails" queue instead
        Settings::set(['queue.worker_enabled' => true]);
        BackgroundJobs::beat(BackgroundJobs::SCHEDULER);
        $this->emails()->toStudent('document_accepted', $student, ['document' => 'Photo']);
        Bus::assertDispatched(SendEmailJob::class, fn ($job) => $job->queue === 'emails' && $job->connection === 'database');
    }

    public function test_institute_version_overrides_group_version_overrides_default(): void
    {
        $a = $this->student($this->instA);
        $b = $this->student($this->instB);
        $emails = $this->emails();

        $this->assertSame('default', $emails->template('payment_received', $this->instA->id)['source']);

        EmailTemplate::create(['institute_id' => null, 'key' => 'payment_received', 'subject' => 'Group: thanks {first_name}', 'body' => 'Group body', 'status' => true]);
        EmailTemplate::create(['institute_id' => $this->instA->id, 'key' => 'payment_received', 'subject' => 'Alpha: thanks {first_name}', 'body' => 'Alpha body', 'status' => true]);

        $this->assertSame('Alpha: thanks Ria', $emails->toStudent('payment_received', $a, ['amount' => '₹1', 'fee' => 'Fee', 'receipt' => 'R1'])->subject);
        $this->assertSame('Group: thanks Ria', $emails->toStudent('payment_received', $b, ['amount' => '₹1', 'fee' => 'Fee', 'receipt' => 'R1'])->subject);

        // Switched off for Alpha: logged with the reason, never sent
        EmailTemplate::where('institute_id', $this->instA->id)->update(['status' => false]);
        $log = $emails->toStudent('payment_received', $a, ['amount' => '₹1', 'fee' => 'Fee', 'receipt' => 'R1']);
        $this->app->terminate();
        $this->assertSame('pending', $log->fresh()->status);
        $this->assertStringContainsString('switched off in Email Templates', $log->fresh()->error);
    }

    public function test_values_are_escaped_and_simple_formatting_works(): void
    {
        $student = $this->student($this->instA, ['first_name' => '<b>Evil</b>']);
        $log = $this->emails()->toStudent('document_reminder', $student, ['documents' => ['Medical <script>', '12th Marksheet']]);
        $html = $log->payload['html'];

        $this->assertStringContainsString('&lt;b&gt;Evil&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('<li style="margin:0 0 6px;">12th Marksheet</li>', $html);
        $this->assertStringContainsString(route('portal.login'), $html); // the button
    }

    public function test_failure_is_logged_and_super_admin_can_retry(): void
    {
        $student = $this->student($this->instA);
        $this->smtpReply = [['data' => ['error' => 'Sender domain not verified', 'error_code' => 'E_ApiResponseCodes.NON_VALIDATED_SENDER']], 400];

        $log = $this->emails()->toStudent('er_issued', $student);
        $this->app->terminate();
        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('Sender domain not verified', $log->error);

        // An institute admin sees it but cannot retry
        Livewire::actingAs($this->adminA)->test(NotificationLogComponent::class)->assertSee('Sender domain not verified')
            ->call('retry', $log->id)->assertForbidden();

        // Super Admin: Retry (in tests every request re-runs earlier after-response jobs, so only count up)
        $c = Livewire::actingAs($this->super)->test(NotificationLogComponent::class)->call('view', $log->id)
            ->assertSet('selected.status', 'failed')->assertSet('selected.can_retry', true);
        $this->smtp2goAccepts();
        $c->call('retry', $log->id)->assertHasNoErrors();
        $this->app->terminate();
        $this->assertSame('sent', $log->fresh()->status);
        $this->assertGreaterThanOrEqual(2, $log->fresh()->attempts);
        $this->assertNull($log->fresh()->error);
    }

    public function test_switched_off_keeps_emails_pending_until_retried(): void
    {
        Settings::set(['mail.enabled' => false]);
        $log = $this->emails()->toStudent('er_issued', $this->student($this->instA));
        $this->app->terminate();
        $this->assertSame('pending', $log->fresh()->status);
        $this->assertStringContainsString('switched off in System Settings', $log->fresh()->error);
        Http::assertNothingSent();

        Settings::set(['mail.enabled' => true]);
        $this->emails()->retry($log->fresh());
        $this->app->terminate();
        $this->assertSame('sent', $log->fresh()->status);
    }

    public function test_institute_admin_edits_only_own_institute_templates(): void
    {
        $c = Livewire::actingAs($this->adminA)->test(EmailTemplatesComponent::class)
            ->assertSet('scope', (string) $this->instA->id)
            ->set('scope', (string) $this->instB->id) // ignored: own institute only
            ->call('select', 'payment_received')
            ->set('subject', 'Alpha receipt {receipt}')->set('body', "Dear {first_name},\n\nThanks!")
            ->call('save')->assertHasNoErrors();

        $row = EmailTemplate::where('key', 'payment_received')->firstOrFail();
        $this->assertSame($this->instA->id, $row->institute_id);
        $this->assertFalse(EmailTemplate::where('institute_id', $this->instB->id)->exists());

        // Bad button link and unknown placeholder warning
        $c->set('buttonText', 'Pay')->set('buttonUrl', 'javascript:alert(1)')->call('save')->assertHasErrors(['buttonUrl'])
            ->set('buttonUrl', '{portal_url}')->set('body', 'Hi {nickname}')->assertSee('{nickname}');

        // Reset → back to the group / default wording
        $c->call('resetToDefault');
        $this->assertFalse(EmailTemplate::where('key', 'payment_received')->exists());

        // Sender details for the own institute
        $c->set('senderName', 'Alpha Admissions')->set('replyTo', 'not-an-email')->call('saveSettings')->assertHasErrors(['replyTo'])
            ->set('replyTo', 'office@alpha.test')->call('saveSettings')->assertHasNoErrors();
        $this->assertSame('office@alpha.test', InstituteEmailSetting::where('institute_id', $this->instA->id)->value('reply_to'));
    }

    public function test_super_admin_saves_group_version_and_accounts_cannot_open(): void
    {
        Livewire::actingAs($this->super)->test(EmailTemplatesComponent::class)
            ->assertSet('scope', '')->call('select', 'er_issued')
            ->set('subject', 'Welcome — {er_number}')->call('save')->assertHasNoErrors()
            ->assertSee('Group version');
        $this->assertTrue(EmailTemplate::whereNull('institute_id')->where('key', 'er_issued')->exists());

        $this->actingAs($this->super)->get(route('admin.email-templates'))->assertOk()->assertSee('Email Templates');
        $this->actingAs($this->adminA)->get(route('admin.email-templates'))->assertOk();
        $this->actingAs($this->makeUser('accounts', $this->instA))->get(route('admin.email-templates'))->assertForbidden();
    }

    public function test_template_test_send_waits_for_the_answer(): void
    {
        Livewire::actingAs($this->adminA)->test(EmailTemplatesComponent::class)
            ->call('select', 'document_rejected')->set('testEmail', 'me@alpha.test')->call('sendTest')
            ->assertSet('testResult.ok', true);

        $log = NotificationLog::latest('id')->first();
        $this->assertSame('template_test', $log->event_type);
        $this->assertSame('sent', $log->status);
        $this->assertStringStartsWith('[Test] ', $log->subject);
    }

    public function test_workflow_emails_use_the_central_service(): void
    {
        $student = $this->student($this->instA);
        app(\App\Services\OnboardingService::class)->remindMissingDocuments($student);

        $log = NotificationLog::where('notifiable_id', $student->id)->where('event_type', 'document_reminder')->latest('id')->firstOrFail();
        $this->assertSame('document_reminder', $log->template_key);
        $this->assertStringContainsString('Medical Certificate', $log->payload['html']);
    }
}
