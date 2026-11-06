<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Settings\SystemSettingsComponent;
use App\Models\Admin\AuditTrail;
use App\Models\Admin\Category;
use App\Models\Admin\Course;
use App\Models\Admin\Institute;
use App\Models\Admin\NotificationLog;
use App\Models\Admin\Qualification;
use App\Models\Admin\Religion;
use App\Models\Admin\Student;
use App\Models\Admin\SystemSetting;
use App\Models\User;
use App\Services\AppNotifier;
use App\Services\MenuService;
use App\Services\NotificationService;
use App\Support\ArtisanRunner;
use App\Support\Settings;
use Database\Seeders\admin\MasterDataSeeder;
use Database\Seeders\admin\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * System Settings (Super Admin). Runs inside a transaction on the *_testing database (rolled back).
 */
class Module1SystemSettingsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $super;
    protected User $admin;
    protected Institute $inst;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(MasterDataSeeder::class);
        Settings::flush();

        $this->inst = Institute::create([
            'name' => 'Settings Aviation ' . uniqid(), 'established_year' => 2004, 'code' => Institute::generateCode('SET', 2004),
            'email' => uniqid() . '@inst.test', 'phone' => (string) random_int(1000000000, 9999999999), 'status' => true,
        ]);
        $this->super = $this->makeUser('super-admin');
        $this->admin = $this->makeUser('institute-admin', $this->inst);
    }

    protected function tearDown(): void
    {
        Settings::flush();
        parent::tearDown();
    }

    protected function makeUser(string $role, ?Institute $institute = null): User
    {
        $user = User::create(['name' => $role, 'email' => uniqid($role) . '@user.test', 'password' => bcrypt('password'), 'institute_id' => $institute?->id, 'status' => true]);
        $user->assignRole($role);

        return $user;
    }

    protected function student(): Student
    {
        $religion = Religion::where('name', 'Hindu')->firstOrFail();
        $course = Course::create(['name' => 'AME', 'code' => 'ST-' . substr(uniqid(), -5), 'duration_months' => 24, 'total_semesters' => 4, 'period_months' => 6, 'status' => true]);

        return Student::create([
            'institute_id' => $this->inst->id, 'course_id' => $course->id, 'first_name' => 'Ria', 'last_name' => 'Settings', 'dob' => '2005-01-01', 'gender' => 'female',
            'qualification_id' => Qualification::first()->id, 'email' => uniqid('st') . '@mail.test', 'phone' => '+919800000031', 'emergency_contact' => '+919800000032',
            'religion_id' => $religion->id, 'category_id' => Category::where('religion_id', $religion->id)->first()->id,
            'joining_date' => '2025-06-01', 'onboarding_deadline' => '2025-07-01', 'status' => 'draft',
        ]);
    }

    public function test_only_super_admin_opens_settings_and_sees_menu(): void
    {
        $this->actingAs($this->super)->get(route('admin.settings'))->assertOk()->assertSee('System Settings')->assertSee('Appearance');
        $this->actingAs($this->admin)->get(route('admin.settings'))->assertForbidden();

        $labels = fn (User $u) => collect(MenuService::flatFor($u))->pluck('items')->flatten(1)->pluck('label')->all();
        $this->assertContains('System Settings', $labels($this->super));
        $this->assertNotContains('System Settings', $labels($this->admin));
    }

    public function test_defaults_apply_without_saved_rows(): void
    {
        $this->assertSame('Sha-Shib CAMPUS', Settings::get('general.site_name'));
        $this->assertFalse(Settings::emailEnabled());
        $this->assertTrue(Settings::bool('portal.registration_open'));
        $this->assertTrue(Settings::notificationAllowed('payment_approved', 'student'));
    }

    public function test_general_settings_save_validate_and_audit(): void
    {
        Livewire::actingAs($this->super)->test(SystemSettingsComponent::class)
            ->set('s.general.site_name', '')->call('save')->assertHasErrors(['s.general.site_name' => 'required'])
            ->set('s.general.site_name', 'CAMP Live')->set('s.general.support_email', 'not-an-email')->call('save')->assertHasErrors(['s.general.support_email'])
            ->set('s.general.support_email', 'help@camp.test')->call('save')->assertHasNoErrors();

        $this->assertSame('CAMP Live', Settings::get('general.site_name'));
        $this->assertSame('help@camp.test', setting('general.support_email'));
        $audit = AuditTrail::where('module', 'System Settings')->latest('id')->first();
        $this->assertSame('CAMP Live', $audit->meta['new']['general.site_name']);
    }

    public function test_appearance_theme_is_saved_and_rendered_in_layout(): void
    {
        Livewire::actingAs($this->super)->test(SystemSettingsComponent::class, ['tab' => 'appearance'])
            ->set('tab', 'appearance')
            ->set('s.theme.color', 'custom')->set('s.theme.custom_color', 'not-a-colour')->call('save')->assertHasErrors(['s.theme.custom_color'])
            ->set('s.theme.custom_color', '#1a2b3c')->set('s.theme.sidebar', 'indigo')->set('s.theme.mode', 'dark')->call('save')
            ->assertHasNoErrors()->assertDispatchedBrowserEvent('camp-theme-saved');

        $theme = Settings::theme();
        $this->assertNull($theme['color']);
        $this->assertSame('26, 43, 60', $theme['rgb']);
        $this->assertSame('indigo', $theme['sidebar']);
        $this->assertSame('dark', $theme['mode']);

        // Layout carries the theme and hides the per-user customiser
        $this->actingAs($this->super)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('"sidebar":"indigo"', false)->assertSee('.sidebar-contact, #theme-setting { display: none !important; }', false);
    }

    /** What the faked SMTP2GO API answers: [body, HTTP status]. */
    protected array $smtpReply = [];

    protected function fakeSmtp2Go(): void
    {
        config(['services.smtp2go.api_key' => 'api-TEST00001234', 'services.smtp2go.sender_email' => 'no-reply@camp.test']);
        $this->smtpReply = [['request_id' => 'r1', 'data' => ['succeeded' => 1, 'failed' => 0, 'failures' => [], 'email_id' => '1abcDE-TEST']], 200];
        Http::preventStrayRequests();
        Http::fake(['api.smtp2go.com/*' => fn () => Http::response($this->smtpReply[0], $this->smtpReply[1])]);
    }

    public function test_email_can_be_switched_on_only_when_smtp2go_is_configured(): void
    {
        config(['services.smtp2go.api_key' => null, 'services.smtp2go.sender_email' => null]);
        $c = Livewire::actingAs($this->super)->test(SystemSettingsComponent::class)->set('tab', 'email')
            ->assertSee('SMTP2GO_API_KEY')
            ->set('s.mail.enabled', true)->call('save')->assertHasErrors(['s.mail.enabled']);
        $this->assertFalse(Settings::emailEnabled());

        $this->fakeSmtp2Go();
        $c->set('s.mail.enabled', true)->call('save')->assertHasNoErrors()->assertSee('api-…1234')->assertDontSee('api-TEST00001234');
        $this->assertTrue(Settings::emailEnabled());
    }

    public function test_email_is_sent_only_when_switched_on(): void
    {
        $this->fakeSmtp2Go();
        $service = app(NotificationService::class);

        $service->send($this->super, 'test_event', 'Hello', 'Body');
        $log = NotificationLog::latest('id')->first();
        $this->assertSame('pending', $log->status);
        $this->assertStringContainsString('switched off', $log->error);
        Http::assertNothingSent();

        Settings::set(['mail.enabled' => true]);
        $service->send($this->super, 'test_event', 'Hello', 'Body');
        $log = NotificationLog::latest('id')->first();
        $this->assertSame('pending', $log->status); // sent in the background, after the response
        $this->app->terminate();
        $this->assertSame('sent', $log->fresh()->status);
        $this->assertSame('1abcDE-TEST', $log->fresh()->provider_message_id);
    }

    public function test_test_email_reports_success_and_failure(): void
    {
        $this->fakeSmtp2Go();

        Livewire::actingAs($this->super)->test(SystemSettingsComponent::class)->set('tab', 'email')
            ->set('testEmail', 'me@camp.test')->call('sendTest')
            ->assertHasNoErrors()->assertSet('testResult.ok', true);
        Http::assertSent(fn ($r) => $r->hasHeader('X-Smtp2go-Api-Key', 'api-TEST00001234') && $r['to'] === ['"super-admin" <me@camp.test>']);

        $this->smtpReply = [['data' => ['error' => 'Invalid API key', 'error_code' => 'E_ApiResponseCodes.API_KEY_INVALID']], 401];
        Livewire::actingAs($this->super)->test(SystemSettingsComponent::class)->set('tab', 'email')
            ->set('testEmail', 'me@camp.test')->call('sendTest')
            ->assertSet('testResult.ok', false)->assertSee('Invalid API key');
    }

    public function test_notification_switches_stop_in_app_notifications(): void
    {
        $student = $this->student();
        $notifier = app(AppNotifier::class);

        $notifier->notify('registration_started', $student);
        $this->assertSame(1, $this->admin->notifications()->count());

        // One event off
        Livewire::actingAs($this->super)->test(SystemSettingsComponent::class)->set('tab', 'notifications')
            ->set('events.registration_started', false)->call('save')->assertHasNoErrors();
        $this->assertFalse(Settings::notificationAllowed('registration_started', 'staff'));
        $this->assertTrue(Settings::notificationAllowed('documents_complete', 'staff'));
        auth()->logout();
        $notifier->notify('registration_started', $student);
        $this->assertSame(1, $this->admin->notifications()->count());

        // Master switch off
        Settings::set(['notify.disabled_events' => [], 'notify.in_app' => false]);
        $notifier->notify('registration_started', $student);
        $this->assertSame(1, $this->admin->notifications()->count());

        // Staff off, master on
        Settings::set(['notify.in_app' => true, 'notify.staff' => false]);
        $notifier->notify('registration_started', $student);
        $this->assertSame(1, $this->admin->notifications()->count());
    }

    public function test_closing_admissions_hides_registration(): void
    {
        $this->get(route('portal.register'))->assertOk();

        Settings::set(['portal.registration_open' => false, 'portal.closed_message' => 'Applications reopen in June.']);

        $this->get(route('portal.register'))->assertRedirect(route('portal.home'));
        $this->get(route('portal.home'))->assertOk()->assertSee('Applications reopen in June.')->assertDontSee('Start your application');
    }

    public function test_session_lifetime_is_applied_and_system_tab_renders(): void
    {
        Livewire::actingAs($this->super)->test(SystemSettingsComponent::class)->set('tab', 'system')
            ->set('s.security.session_lifetime', 2)->call('save')->assertHasErrors(['s.security.session_lifetime'])
            ->set('s.security.session_lifetime', 45)->call('save')->assertHasNoErrors()
            ->assertSee('Server health')->assertSee('Disk space');

        Settings::applyRuntimeConfig();
        $this->assertSame(45, config('session.lifetime'));
    }

    public function test_optimize_buttons_run_artisan_and_show_output(): void
    {
        // Mocked: a real optimize would write bootstrap/cache/config.php during the test run.
        $runner = $this->mock(ArtisanRunner::class);
        $runner->shouldReceive('run')->once()->with('optimize:clear')->andReturn([0, 'config … DONE'])->ordered();
        $runner->shouldReceive('run')->once()->with('optimize')->andReturn([1, 'Route [x] already in use.'])->ordered();

        $page = Livewire::actingAs($this->super)->test(SystemSettingsComponent::class)->set('tab', 'system')
            ->assertSee('Clear all caches')->assertSee('Clear &amp; optimise', false)->assertSee('waiting for a command')
            ->assertSee('Deploy checklist')->assertSee('to review below')->assertSee('Set APP_ENV=production on the live server')
            ->call('optimizeApp')
            ->assertSet('artisanResult.ok', false)->assertDontSee('waiting for a command')
            ->assertSee('php artisan optimize:clear')->assertSee('<span class="t-ok">DONE</span>', false)
            ->assertSee('Route [x] already in use.')->assertSee('ended with code 1')
            ->assertDispatchedBrowserEvent('show-toast', fn ($e, $d) => $d['type'] === 'danger');

        $audit = AuditTrail::where('module', 'System Settings')->latest('id')->first();
        $this->assertSame('Cleared and rebuilt caches (optimize) (failed)', $audit->meta['description']);
        $this->assertSame($this->super->id, $audit->user_id);

        $runner->shouldReceive('run')->once()->with('optimize:clear')->andReturn([0, ''])->ordered();
        $page->call('optimizeClear')->assertSet('artisanResult.ok', true)->assertSee('Done.');

        // A user who loses Super Admin can't keep using an open page.
        $this->super->removeRole('super-admin');
        $page->call('optimizeClear')->assertForbidden();
    }

    public function test_build_commands_run_as_a_separate_process(): void
    {
        $this->assertSame(['optimize', 'config:cache', 'route:cache', 'event:cache'], ArtisanRunner::SEPARATE_PROCESS);
        $this->assertNotContains('optimize:clear', ArtisanRunner::SEPARATE_PROCESS);
    }
}
