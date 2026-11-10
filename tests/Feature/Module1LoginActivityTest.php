<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Auth\Login;
use App\Http\Livewire\Admin\Monitoring\LoginActivityComponent;
use App\Http\Livewire\Portal\LoginPage;
use App\Models\Admin\Institute;
use App\Models\Admin\LoginLog;
use App\Models\User;
use App\Services\LoginLogger;
use Database\Seeders\admin\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Monitoring ▸ Login Activity: sign-ins, failed / blocked attempts and sign-outs of the admin panel and the
 * student portal are logged, and only the Super Admin sees them. Runs on the *_testing database, rolled back.
 */
class Module1LoginActivityTest extends TestCase
{
    use DatabaseTransactions;

    protected Institute $inst;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        LoginLog::query()->delete(); // rolled back after the test
        $this->inst = Institute::create([
            'name' => 'Log Aviation ' . uniqid(), 'established_year' => 2005, 'code' => Institute::generateCode('LOG', 2005),
            'email' => uniqid() . '@inst.test', 'phone' => (string) random_int(1000000000, 9999999999), 'status' => true,
        ]);
    }

    protected function makeUser(string $role, ?Institute $institute = null, array $extra = []): User
    {
        $user = User::create($extra + ['name' => ucfirst($role) . ' Person', 'email' => uniqid($role) . '@user.test', 'password' => bcrypt('password'), 'institute_id' => $institute?->id, 'status' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_sign_in_failed_blocked_and_sign_out_are_logged()
    {
        $admin = $this->makeUser('institute-admin', $this->inst);

        Livewire::test(Login::class)->set('email', $admin->email)->set('password', 'wrong-pass')->call('login');
        Livewire::test(Login::class)->set('email', 'nobody@user.test')->set('password', 'whatever')->call('login');
        Livewire::test(Login::class)->set('email', $admin->email)->set('password', 'password')->call('login')->assertRedirect(route('admin.dashboard'));

        $logs = LoginLog::orderBy('id')->get();
        $this->assertSame(['failed', 'failed', 'login'], $logs->pluck('event')->all());
        $this->assertSame('Wrong password', $logs[0]->reason);
        $this->assertSame($admin->id, $logs[0]->user_id);
        $this->assertNull($logs[1]->user_id);
        $this->assertSame('No account with this email', $logs[1]->reason);
        $this->assertSame('nobody@user.test', $logs[1]->email);
        $this->assertSame('admin', $logs[2]->panel);
        $this->assertSame($this->inst->id, $logs[2]->institute_id);
        $this->assertNotNull($logs[2]->ip);

        // Sign-out
        $this->get(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertSame('logout', LoginLog::latest('id')->first()->event);

        // Inactive account: correct password but blocked
        $inactive = $this->makeUser('accounts', $this->inst, ['status' => false]);
        Livewire::test(Login::class)->set('email', $inactive->email)->set('password', 'password')->call('login');
        $last = LoginLog::latest('id')->first();
        $this->assertSame(['blocked', $inactive->id], [$last->event, $last->user_id]);
        $this->assertStringContainsString('inactive', $last->reason);
        $this->assertFalse(Auth::check());
    }

    public function test_portal_attempts_are_logged_as_portal()
    {
        $staff = $this->makeUser('accounts', $this->inst);

        Livewire::test(LoginPage::class)->set('email', $staff->email)->set('password', 'nope-nope')->call('login');
        Livewire::test(LoginPage::class)->set('email', $staff->email)->set('password', 'password')->call('login')->assertSet('errorMessage', 'This is the student admissions portal. Staff sign in at the admin panel.');

        $logs = LoginLog::orderBy('id')->get();
        $this->assertSame(['portal', 'portal'], $logs->pluck('panel')->all());
        $this->assertSame(['failed', 'blocked'], $logs->pluck('event')->all());
        $this->assertSame('Staff account used the student portal', $logs[1]->reason);
    }

    public function test_logging_never_breaks_sign_in()
    {
        // A log write that fails (here: a value too long for the column) is reported, not thrown
        $handler = \Mockery::spy(\Illuminate\Contracts\Debug\ExceptionHandler::class);
        $this->app->instance(\Illuminate\Contracts\Debug\ExceptionHandler::class, $handler);

        LoginLogger::login(null, str_repeat('x', 50));

        $handler->shouldHaveReceived('report')->once();
        $this->assertSame(0, LoginLog::count());
    }

    public function test_only_super_admin_sees_login_activity()
    {
        $this->actingAs($this->makeUser('institute-admin', $this->inst));
        $this->get(route('admin.login-activity'))->assertForbidden();

        $super = $this->makeUser('super-admin');
        $this->actingAs($super);
        $this->get(route('admin.login-activity'))->assertOk()->assertSee('Login Activity')->assertSee('No sign-ins recorded yet');
    }

    public function test_page_figures_filters_and_watch_list()
    {
        $super = $this->makeUser('super-admin');
        $admin = $this->makeUser('institute-admin', $this->inst, ['name' => 'Asha Admin']);
        $student = $this->makeUser('student', $this->inst, ['name' => 'Ravi Student']);

        $row = fn ($event, ?User $u, $panel, $extra = []) => LoginLog::create($extra + [
            'user_id' => $u?->id, 'institute_id' => $u?->institute_id, 'email' => $u ? $u->email : 'ghost@user.test', 'panel' => $panel,
            'event' => $event, 'ip' => '10.0.0.5', 'browser' => 'Chrome', 'platform' => 'Windows', 'device' => 'desktop', 'created_at' => now(),
        ]);
        $row('login', $admin, 'admin');
        $row('login', $student, 'portal', ['ip' => '10.0.0.9', 'browser' => 'Safari', 'platform' => 'iOS', 'device' => 'mobile']);
        foreach (range(1, 3) as $i) {
            $row('failed', null, 'admin', ['ip' => '203.0.113.7', 'reason' => 'No account with this email']);
        }
        $row('login', $admin, 'admin', ['created_at' => now()->subDays(40)]);

        $this->actingAs($super);
        $c = Livewire::test(LoginActivityComponent::class)
            ->assertViewHas('stats', fn ($s) => $s['logins'] === 2 && $s['admin'] === 1 && $s['portal'] === 1 && $s['failed'] === 3 && $s['people'] === 2)
            ->assertViewHas('watch', fn ($w) => $w->contains(fn ($x) => $x['term'] === '203.0.113.7' && (int) $x['total'] === 3))
            ->assertSee('Asha Admin')->assertSee('Ravi Student')->assertSee('203.0.113.7');

        $c->set('filterPanel', 'portal')->assertViewHas('logs', fn ($l) => $l->total() === 1)->assertSee('Ravi Student')->assertDontSee('Asha Admin');
        $c->call('clearFilters')->set('filterWho', 'staff')->assertViewHas('logs', fn ($l) => $l->total() === 2);
        $c->call('clearFilters')->set('filterEvent', 'problems')->assertViewHas('logs', fn ($l) => $l->total() === 3);
        $c->call('clearFilters')->call('lookFor', '203.0.113.7')->assertViewHas('logs', fn ($l) => $l->total() === 3);
        $c->call('clearFilters')->call('range', '7d')->assertViewHas('logs', fn ($l) => $l->total() === 5);
        $c->call('clearFilters')->call('person', $admin->id)->assertViewHas('logs', fn ($l) => $l->total() === 2)
            ->assertViewHas('focus', fn ($f) => $f['user']->is($admin) && $f['logins30'] === 1);

        // Rows older than a year are pruned
        $row('login', $admin, 'admin', ['created_at' => now()->subDays(LoginLog::KEEP_DAYS + 5)]);
        $this->artisan('model:prune', ['--model' => [LoginLog::class]])->assertSuccessful();
        $this->assertSame(0, LoginLog::where('created_at', '<', now()->subDays(LoginLog::KEEP_DAYS))->count());
        $this->assertSame(6, LoginLog::count());
    }
}
