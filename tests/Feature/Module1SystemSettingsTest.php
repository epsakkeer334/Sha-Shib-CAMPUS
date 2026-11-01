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
use App\Support\Settings;
use Database\Seeders\admin\MasterDataSeeder;
use Database\Seeders\admin\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
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

    public function test_smtp_password_is_encrypted_and_kept_when_left_empty(): void
    {
        $c = Livewire::actingAs($this->super)->test(SystemSettingsComponent::class)->set('tab', 'email')
            ->set('s.mail.enabled', true)->set('s.mail.host', '')->call('save')->assertHasErrors(['s.mail.host'])
            ->set('s.mail.host', 'mail.smtp2go.com')->set('s.mail.from_address', 'no-reply@camp.test')->set('mailPassword', 'Secret#123')->call('save')->assertHasNoErrors();

        $raw = SystemSetting::where('key', 'mail.password')->value('value');
        $this->assertStringNotContainsString('Secret#123', $raw);
        $this->assertSame('Secret#123', Settings::get('mail.password'));
        $this->assertTrue(Settings::emailEnabled());

        // Saving again with an empty password field keeps it; the audit never shows it
        $c->set('s.mail.port', 587)->call('save');
        $this->assertSame('Secret#123', Settings::get('mail.password'));
        $this->assertStringNotContainsString('Secret#123', json_encode(AuditTrail::where('module', 'System Settings')->pluck('meta')));

        $c->call('removePassword');
        $this->assertSame('', Settings::get('mail.password'));
    }

    public function test_email_is_sent_only_when_switched_on(): void
    {
        Mail::fake();
        $service = app(NotificationService::class);

        $service->send($this->super, 'test_event', 'Hello', 'Body');
        $log = NotificationLog::latest('id')->first();
        $this->assertSame('pending', $log->status);
        $this->assertStringContainsString('switched off', $log->error);

        Settings::set(['mail.enabled' => true, 'mail.host' => 'smtp.test', 'mail.port' => 2525, 'mail.from_address' => 'no-reply@camp.test']);
        $service->send($this->super, 'test_event', 'Hello', 'Body');
        $this->assertSame('sent', NotificationLog::latest('id')->first()->status);
    }

    public function test_test_email_reports_success(): void
    {
        Mail::fake();

        Livewire::actingAs($this->super)->test(SystemSettingsComponent::class)->set('tab', 'email')
            ->call('applyPreset', 'godaddy')->assertSet('s.mail.host', 'smtpout.secureserver.net')->assertSet('s.mail.port', 465)
            ->set('s.mail.from_address', 'no-reply@camp.test')->set('testEmail', 'me@camp.test')->call('sendTest')
            ->assertHasNoErrors()->assertSet('testResult.ok', true);
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
}
