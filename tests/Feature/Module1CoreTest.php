<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Roles\RolesComponent;
use App\Http\Livewire\Admin\Users\UsersComponent;
use App\Models\Admin\AuditTrail;
use App\Models\Admin\Institute;
use App\Models\User;
use App\Services\MenuService;
use App\Services\SerialNumberService;
use Database\Seeders\admin\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module 1 — Core / Foundation: user creation rules, institute scoping, menu matrix,
 * active-user checks, serial numbers. Runs inside a transaction (rolled back).
 */
class Module1CoreTest extends TestCase
{
    use DatabaseTransactions;

    protected Institute $instA;
    protected Institute $instB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->instA = $this->makeInstitute('Alpha Aviation', 2001);
        $this->instB = $this->makeInstitute('Beta Aero', 2010);
    }

    protected function makeInstitute(string $name, int $year): Institute
    {
        return Institute::create([
            'name' => $name . ' ' . uniqid(),
            'established_year' => $year,
            'code' => Institute::generateCode(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3), $year),
            'email' => uniqid() . '@inst.test',
            'phone' => (string) random_int(1000000000, 9999999999),
            'status' => true,
        ]);
    }

    protected function makeUser(string $role, ?Institute $institute = null, bool $active = true): User
    {
        $user = User::create([
            'name' => ucfirst($role) . ' User',
            'email' => uniqid($role) . '@user.test',
            'password' => bcrypt('password'),
            'institute_id' => $institute?->id,
            'status' => $active,
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_super_admin_creates_institute_admin_and_staff_for_selected_institute()
    {
        $this->actingAs($this->makeUser('super-admin'));

        foreach (['institute-admin', 'accounts', 'training-manager', 'bic', 'examination-manager', 'hot', 'faculty'] as $role) {
            Livewire::test(UsersComponent::class)
                ->call('openModal')
                ->set('role', $role)
                ->set('institute_id', $this->instA->id)
                ->set('name', "New {$role}")
                ->set('email', "{$role}-" . uniqid() . '@new.test')
                ->set('password', 'secret123')
                ->set('password_confirmation', 'secret123')
                ->call('save')
                ->assertHasNoErrors();

            $created = User::where('name', "New {$role}")->latest('id')->first();
            $this->assertTrue($created->hasRole($role));
            $this->assertEquals($this->instA->id, $created->institute_id);
        }

        $this->assertTrue(AuditTrail::where('module', 'users')->where('action', 'create')->exists());
    }

    public function test_super_admin_must_select_institute_for_institute_roles_but_not_for_super_admin()
    {
        $this->actingAs($this->makeUser('super-admin'));

        Livewire::test(UsersComponent::class)
            ->set('role', 'accounts')
            ->set('name', 'No Institute')
            ->set('email', uniqid() . '@new.test')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('save')
            ->assertHasErrors(['institute_id' => 'required']);

        Livewire::test(UsersComponent::class)
            ->set('role', 'super-admin')
            ->set('institute_id', $this->instA->id)
            ->set('name', 'Second Super Admin')
            ->set('email', uniqid() . '@new.test')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(User::where('name', 'Second Super Admin')->first()->institute_id);
    }

    public function test_institute_code_uses_the_chosen_prefix()
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->actingAs($this->makeUser('super-admin'));
        $country = \App\Models\Admin\Country::firstOrCreate(['code' => 'TST'], ['name' => 'Testland', 'status' => true]);
        $state = \App\Models\Admin\State::firstOrCreate(['country_id' => $country->id, 'name' => 'Test State'], ['status' => true]);

        $page = Livewire::test(\App\Http\Livewire\Admin\Institutes\InstitutesComponent::class)
            ->set('name', 'Sha Shib Aviation Academy ' . uniqid())
            ->set('established_year', 2005)
            ->set('country_id', $country->id)->set('state_id', $state->id)
            ->set('email', uniqid() . '@inst.test')->set('phone', (string) random_int(1000000000, 9999999999))
            ->set('logo', \Illuminate\Http\UploadedFile::fake()->image('logo.png'));

        // Prefix is required and must be 2–6 letters
        $page->call('save')->assertHasErrors(['code_prefix' => 'required']);
        $page->set('code_prefix', 'S1')->call('save')->assertHasErrors(['code_prefix' => 'regex']);
        $page->set('code_prefix', 'TOOLONGX')->call('save')->assertHasErrors(['code_prefix' => 'regex']);

        // Typed in lower case → upper case; the preview follows prefix and year, not the name
        $page->set('code_prefix', 'ssa')->assertSet('code_prefix', 'SSA');
        $this->assertMatchesRegularExpression('~^SSA/2005/\d+$~', $page->get('code'));

        $page->call('save')->assertHasNoErrors();
        $institute = Institute::where('code_prefix', 'SSA')->latest('id')->firstOrFail();
        $this->assertMatchesRegularExpression('~^SSA/2005/\d{4,}$~', $institute->code);

        // Running number is group-wide: the next institute (different prefix) gets the next number
        $next = Institute::generateCode('KAP', 2010);
        [, , $seq] = explode('/', $institute->code);
        $this->assertSame('KAP/2010/' . ((int) $seq + 1), $next);

        // Duplicate prefix: refused while typing (any case) and on save; a deleted institute's prefix stays taken
        $fresh = fn () => Livewire::test(\App\Http\Livewire\Admin\Institutes\InstitutesComponent::class)
            ->set('name', 'Second Academy ' . uniqid())->set('established_year', 2011)
            ->set('country_id', $country->id)->set('state_id', $state->id)
            ->set('email', uniqid() . '@inst.test')->set('phone', (string) random_int(1000000000, 9999999999))
            ->set('logo', \Illuminate\Http\UploadedFile::fake()->image('logo.png'));
        $fresh()->set('code_prefix', 'ssa')->assertHasErrors(['code_prefix' => 'unique'])
            ->assertSee('This prefix is already used by another institute')
            ->call('save')->assertHasErrors(['code_prefix' => 'unique']);
        $this->assertSame(1, Institute::where('code_prefix', 'SSA')->count());

        $gone = Institute::create(['name' => 'Gone Institute ' . uniqid(), 'established_year' => 2000, 'code_prefix' => 'GON',
            'code' => Institute::generateCode('GON', 2000), 'email' => uniqid() . '@inst.test', 'phone' => (string) random_int(1000000000, 9999999999), 'status' => true]);
        $gone->delete();
        $fresh()->set('code_prefix', 'GON')->assertHasErrors(['code_prefix' => 'unique']);

        // A free prefix is shown as available
        $fresh()->set('code_prefix', 'FRE')->assertHasNoErrors()->assertSee('Available');

        // Code (and its prefix) cannot be changed on edit
        Livewire::test(\App\Http\Livewire\Admin\Institutes\InstitutesComponent::class)->call('edit', $institute->id)
            ->assertSet('code_prefix', 'SSA')->set('code_prefix', 'XYZ')->set('name', $institute->name . ' Updated')
            ->call('update')->assertHasNoErrors();
        $this->assertSame($institute->code, $institute->fresh()->code);
        $this->assertSame('SSA', $institute->fresh()->code_prefix);
    }

    public function test_institutes_list_highlights_code_and_toggles_status()
    {
        $this->actingAs($this->makeUser('super-admin'));
        $this->makeUser('accounts', $this->instA);

        [$prefix, $year, $number] = explode('/', $this->instA->code);
        $this->get(route('admin.institutes'))->assertOk()
            ->assertSee('Institutes Management')
            ->assertSee('<span class="in-code-prefix">' . $prefix . '</span>', false)
            ->assertSee('<span class="in-code-num">' . $number . '</span>', false);

        // Search, status filter and quick on/off
        Livewire::test(\App\Http\Livewire\Admin\Institutes\InstitutesComponent::class)
            ->set('search', $this->instA->code)->assertSee($this->instA->name)->assertDontSee($this->instB->name);
        Livewire::test(\App\Http\Livewire\Admin\Institutes\InstitutesComponent::class)
            ->call('toggleStatus', $this->instB->id)
            ->set('filter', 'Inactive')->assertSee($this->instB->name)->assertDontSee($this->instA->name);
        $this->assertFalse($this->instB->fresh()->status);
        $this->assertTrue(AuditTrail::where('module', 'institutes')->where('reference_id', $this->instB->id)->where('meta', 'like', '%Deactivated institute%')->exists());

        // Staff count excludes student logins
        $page = Livewire::test(\App\Http\Livewire\Admin\Institutes\InstitutesComponent::class)->set('search', $this->instA->code);
        $row = $page->viewData('institutes')->firstWhere('id', $this->instA->id);
        $this->assertSame(\App\Models\User::where('institute_id', $this->instA->id)->whereDoesntHave('roles', fn ($r) => $r->where('name', 'student'))->count(), (int) $row->staff_count);
    }

    public function test_institute_admin_creates_staff_only_in_own_institute()
    {
        $this->actingAs($this->makeUser('institute-admin', $this->instA));

        // Tries to put the user in another institute: forced back to own institute.
        Livewire::test(UsersComponent::class)
            ->set('role', 'faculty')
            ->set('institute_id', $this->instB->id)
            ->set('name', 'Faculty By Inst Admin')
            ->set('email', uniqid() . '@new.test')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals($this->instA->id, User::where('name', 'Faculty By Inst Admin')->first()->institute_id);

        foreach (['institute-admin', 'super-admin', 'student'] as $forbidden) {
            Livewire::test(UsersComponent::class)
                ->set('role', $forbidden)
                ->set('name', 'Forbidden')
                ->set('email', uniqid() . '@new.test')
                ->set('password', 'secret123')
                ->set('password_confirmation', 'secret123')
                ->call('save')
                ->assertHasErrors(['role' => 'in']);
        }
    }

    public function test_institute_admin_cannot_see_or_edit_other_institutes_users()
    {
        $adminA = $this->makeUser('institute-admin', $this->instA);
        $staffA = $this->makeUser('accounts', $this->instA);
        $staffB = $this->makeUser('accounts', $this->instB);

        $visible = User::visibleTo($adminA)->pluck('id');
        $this->assertTrue($visible->contains($staffA->id));
        $this->assertFalse($visible->contains($staffB->id));

        $this->actingAs($adminA);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::test(UsersComponent::class)->call('edit', $staffB->id);
    }

    public function test_institute_admin_cannot_edit_another_institute_admin()
    {
        $adminA = $this->makeUser('institute-admin', $this->instA);
        $otherAdminA = $this->makeUser('institute-admin', $this->instA);

        $this->actingAs($adminA);
        Livewire::test(UsersComponent::class)
            ->call('edit', $otherAdminA->id)
            ->assertSet('recordId', null)
            ->assertDispatchedBrowserEvent('show-toast');
    }

    public function test_page_access_by_role()
    {
        $this->actingAs($this->makeUser('institute-admin', $this->instA));
        $this->get(route('admin.users'))->assertOk();
        $this->get(route('admin.audit-trail'))->assertOk();
        $this->get(route('admin.institutes'))->assertForbidden();
        $this->get(route('admin.roles'))->assertForbidden();
        $this->get(route('admin.institute-users.institute', $this->instB->id))->assertNotFound();

        $this->actingAs($this->makeUser('accounts', $this->instA));
        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.users'))->assertForbidden();

        $this->actingAs($this->makeUser('super-admin'));
        foreach (['admin.dashboard', 'admin.institutes', 'admin.users', 'admin.roles', 'admin.audit-trail', 'admin.notifications'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('admin.institute-users.institute', $this->instB->id))->assertOk();
    }

    public function test_side_menu_follows_role_matrix()
    {
        $labels = fn (User $u) => collect(MenuService::flatFor($u))->flatMap(fn ($s) => array_column($s['items'], 'label'))->all();

        $super = $labels($this->makeUser('super-admin'));
        foreach (['Dashboard', 'Institutes', 'Users', 'Roles & Permissions', 'Audit Trail', 'Notification Log'] as $label) {
            $this->assertContains($label, $super);
        }

        $instAdmin = $labels($this->makeUser('institute-admin', $this->instA));
        $this->assertContains('Users', $instAdmin);
        $this->assertContains('Audit Trail', $instAdmin);
        $this->assertNotContains('Institutes', $instAdmin);
        $this->assertNotContains('Roles & Permissions', $instAdmin);

        // Sections in order; every module lives in a collapsible group of its section.
        $groups = collect(MenuService::for($this->makeUser('super-admin')))->keyBy('title');
        $this->assertSame(['Main', 'Institute', 'Admissions', 'Administration'], $groups->keys()->all()); // sections without built pages stay hidden
        $instituteGroup = $groups['Institute']['items'][0];
        $this->assertSame('Institute Management', $instituteGroup['label']);
        $this->assertSame(['Institutes', 'Institute Courses', 'Batches', 'Academic Periods', 'Fee Structure', 'Payment Settings'], array_column($instituteGroup['children'], 'label'));
        $this->assertSame(['Students', 'Onboarding'], array_column($groups['Admissions']['items'], 'label'));
        $this->assertSame(['Document Verification', 'Payment Verification', 'ER & ID Cards'], array_column($groups['Admissions']['items'][1]['children'], 'label'));
        $this->assertSame(['Dashboard', 'Master Data'], array_column($groups['Main']['items'], 'label')); // Master Data at the top
        $this->assertSame(['Users & Permissions', 'Monitoring'], array_column($groups['Administration']['items'], 'label')); // System: no page built yet
        $group = $groups['Administration']['items'][0];
        $this->assertSame(['Users', 'Roles & Permissions'], array_column($group['children'], 'label'));

        // Institute Admin's groups only contain what they may open.
        $instGroups = collect(MenuService::for($this->makeUser('institute-admin', $this->instA)))->keyBy('title');
        $this->assertSame(['Users'], array_column($instGroups['Administration']['items'][0]['children'], 'label'));

        // Accounts: only what its permissions allow (Module 2 fees & payments); modules not built yet stay hidden.
        $this->assertSame(['Dashboard', 'Fee Structure', 'Payment Settings', 'All Students', 'Payment Verification'], $labels($this->makeUser('accounts', $this->instA)));
        $this->assertSame(['Dashboard'], $labels($this->makeUser('faculty', $this->instA)));
    }

    public function test_inactive_user_or_inactive_institute_is_logged_out()
    {
        $this->actingAs($this->makeUser('accounts', $this->instA, false));
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));

        $user = $this->makeUser('accounts', $this->instB);
        $this->instB->update(['status' => false]);
        $this->actingAs($user);
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_users_list_filters_by_institute_role_status_and_login()
    {
        $super = $this->makeUser('super-admin');
        $adminA = $this->makeUser('institute-admin', $this->instA);
        $accountsA = $this->makeUser('accounts', $this->instA);
        $accountsA->update(['name' => 'Anitha Accounts', 'last_login_at' => now()->subMinutes(5), 'employee_code' => 'EMP-' . uniqid()]);
        $tmB = $this->makeUser('training-manager', $this->instB);
        $tmB->update(['name' => 'Bala Trainer', 'status' => false, 'last_login_at' => now()->subDays(45)]);
        $facultyB = $this->makeUser('faculty', $this->instB);
        $facultyB->update(['name' => 'Chitra Faculty']); // never logged in

        $this->actingAs($super);
        $page = Livewire::test(UsersComponent::class)
            ->assertSee('Anitha Accounts')->assertSee('Bala Trainer')->assertSee('Never logged in');

        $page->set('filterInstitute', $this->instB->id)->assertSee('Bala Trainer')->assertSee('Chitra Faculty')->assertDontSee('Anitha Accounts');
        $page->set('filterInstitute', 'central')->assertSee($super->email)->assertDontSee('Bala Trainer');
        $page->call('clearFilters')
            ->set('filterRole', 'accounts')->assertSee('Anitha Accounts')->assertDontSee('Bala Trainer')
            ->call('clearFilters')->set('filterStatus', 'inactive')->assertSee('Bala Trainer')->assertDontSee('Anitha Accounts')
            ->call('clearFilters')->set('filterLogin', 'online')->assertSee('Anitha Accounts')->assertDontSee('Chitra Faculty')
            ->call('clearFilters')->set('filterLogin', 'never')->assertSee('Chitra Faculty')->assertDontSee('Anitha Accounts')
            ->call('clearFilters')->set('filterLogin', 'idle')->assertSee('Bala Trainer')->assertDontSee('Anitha Accounts')
            ->call('clearFilters')->set('search', $accountsA->employee_code)->assertSee('Anitha Accounts')->assertDontSee('Bala Trainer');

        // Institute Admin: own institute only, no institute filter
        $this->actingAs($adminA);
        Livewire::test(UsersComponent::class)
            ->assertSee('Anitha Accounts')->assertDontSee('Bala Trainer')->assertDontSee('Central users')
            ->set('filterInstitute', $this->instB->id)->assertDontSee('Bala Trainer'); // ignored for institute users
    }

    public function test_users_status_switch_follows_the_management_rules()
    {
        $super = $this->makeUser('super-admin');
        $adminA = $this->makeUser('institute-admin', $this->instA);
        $accountsA = $this->makeUser('accounts', $this->instA);
        $otherAdminA = $this->makeUser('institute-admin', $this->instA);
        $accountsB = $this->makeUser('accounts', $this->instB);

        $this->actingAs($adminA);
        $page = Livewire::test(UsersComponent::class);
        $page->call('toggleStatus', $accountsA->id);
        $this->assertFalse((bool) $accountsA->fresh()->status);
        $this->assertTrue(AuditTrail::where('module', 'users')->where('reference_id', $accountsA->id)->where('meta', 'like', '%Deactivated user%')->exists());

        $page->call('toggleStatus', $adminA->id)->assertDispatchedBrowserEvent('show-toast', fn ($n, $d) => $d['type'] === 'warning'); // self
        $page->call('toggleStatus', $otherAdminA->id)->assertDispatchedBrowserEvent('show-toast', fn ($n, $d) => $d['type'] === 'danger'); // not assignable
        $this->assertTrue((bool) $otherAdminA->fresh()->status);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $page->call('toggleStatus', $accountsB->id); // other institute: not visible
    }

    public function test_cannot_delete_self()
    {
        $super = $this->makeUser('super-admin');
        $this->actingAs($super);

        Livewire::test(UsersComponent::class)
            ->call('confirmDelete', $super->id)
            ->call('delete');

        $this->assertNotSoftDeleted($super);
    }

    public function test_roles_matrix_saves_permissions_and_audits()
    {
        $this->actingAs($this->makeUser('super-admin'));

        Livewire::test(RolesComponent::class)
            ->set('matrix.hot.audit__view', true)
            ->call('save');

        $this->assertTrue(\Spatie\Permission\Models\Role::findByName('hot')->hasPermissionTo('audit.view'));
        $this->assertTrue(AuditTrail::where('module', 'roles')->exists());
    }

    public function test_roles_page_is_grouped_per_role_with_group_switches_search_and_discard()
    {
        $this->actingAs($this->makeUser('super-admin'));
        $hot = \Spatie\Permission\Models\Role::findByName('hot');
        $hot->syncPermissions([]);

        $page = Livewire::test(RolesComponent::class)
            ->call('selectRole', 'hot')->assertSet('selectedRole', 'hot')
            ->assertSee('Head of Training (HoT)')->assertSee('Fees &amp; Payments', false)
            ->assertDontSee('masters.manage'); // Super Admin only, never listed

        // group switch: grants the whole group, a second click revokes it
        $page->call('toggleGroup', 'Students')
            ->assertSet('matrix.hot.students__view', true)->assertSet('matrix.hot.students__delete', true)
            ->assertSee('6 unsaved changes'); // Students: view, create, update, delete, manage_er, bulk_update
        $page->call('toggleGroup', 'Students')->assertSet('matrix.hot.students__view', false)->assertSee('All changes saved');

        // search narrows the list
        $page->set('search', 'receipts')->assertSee('payments.verify')->assertDontSee('students.view')->set('search', '');

        // grant all, then discard: nothing stored
        $page->call('setAll', 1)->assertSet('matrix.hot.audit__view', true)->call('discard')->assertSet('matrix.hot.audit__view', false);
        $this->assertCount(0, $hot->fresh()->permissions);

        // group switch per role from the overview, then save
        $page->call('setView', 'overview')->assertSee('All roles')
            ->call('toggleGroup', 'Audit Trail', 'hot')->call('save');
        $this->assertTrue($hot->fresh()->hasPermissionTo('audit.view'));
        $this->assertSame(1, $hot->fresh()->permissions->count());
    }

    public function test_audit_trail_filters_and_detail_popup()
    {
        $super = $this->makeUser('super-admin');
        $adminA = $this->makeUser('institute-admin', $this->instA);
        $mk = fn ($attrs) => AuditTrail::create($attrs + ['ip_address' => '10.0.0.7']);
        $a = $mk(['user_id' => $adminA->id, 'institute_id' => $this->instA->id, 'action' => 'approve', 'module' => 'enrollment',
            'meta' => ['description' => 'Gate 1 approved for Alpha Student', 'reason' => 'All documents fine']]);
        $b = $mk(['user_id' => null, 'institute_id' => $this->instB->id, 'action' => 'update', 'module' => 'students',
            'meta' => ['description' => 'Updated Beta Student', 'old' => ['phone' => '111'], 'new' => ['phone' => '222']]]);
        AuditTrail::whereKey($b->id)->update(['created_at' => now()->subDays(20)]);

        $this->actingAs($super);
        Livewire::test(\App\Http\Livewire\Admin\AuditTrail\AuditTrailComponent::class)
            ->assertSee('Gate 1 approved for Alpha Student')->assertSee('Updated Beta Student')
            ->set('filterInstitute', $this->instA->id)->assertSee('Alpha Student')->assertDontSee('Beta Student')
            ->call('clearFilters')->set('filterUser', 'system')->assertSee('Beta Student')->assertDontSee('Alpha Student')
            ->call('clearFilters')->set('filterModule', 'enrollment')->set('filterAction', 'approve')->assertSee('Alpha Student')->assertDontSee('Beta Student')
            ->call('clearFilters')->call('range', '7d')->assertSee('Alpha Student')->assertDontSee('Beta Student')
            ->call('clearFilters')->set('search', 'Beta')->assertSee('Updated Beta Student')->assertDontSee('Gate 1 approved')
            ->call('view', $b->id)->assertDispatchedBrowserEvent('open-audit-modal')
            ->assertSet('selected.old', ['phone' => '111'])->assertSet('selected.new', ['phone' => '222'])->assertSet('selected.user', 'System');

        // Institute Admin: own institute only, no institute filter
        $this->actingAs($adminA);
        Livewire::test(\App\Http\Livewire\Admin\AuditTrail\AuditTrailComponent::class)
            ->assertSee('Alpha Student')->assertDontSee('Beta Student')->assertDontSee('All institutes')
            ->set('filterInstitute', $this->instB->id)->assertDontSee('Beta Student');
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::test(\App\Http\Livewire\Admin\AuditTrail\AuditTrailComponent::class)->call('view', $b->id);
    }

    public function test_notification_log_filters_and_full_message_popup()
    {
        $super = $this->makeUser('super-admin');
        $staffA = $this->makeUser('accounts', $this->instA);
        $mk = fn ($attrs) => \App\Models\Admin\NotificationLog::create($attrs + ['notifiable_type' => User::class, 'notifiable_id' => $staffA->id]);
        $sent = $mk(['institute_id' => $this->instA->id, 'channel' => 'email', 'event_type' => 'er_issued', 'recipient' => 'alpha@mail.test', 'status' => 'sent', 'sent_at' => now(),
            'payload' => ['subject' => 'Your ER number: ER-2026-00001', 'message' => "Dear Alpha,\n\nYour ER number is ER-2026-00001.", 'er' => 'ER-2026-00001']]);
        $pending = $mk(['institute_id' => $this->instB->id, 'channel' => 'sms', 'event_type' => 'document_rejected', 'recipient' => '9847000000', 'status' => 'pending',
            'error' => 'SMS gateway not configured yet.', 'payload' => ['subject' => 'Document needs attention', 'message' => 'Please re-upload your photo.']]);

        $this->actingAs($super);
        Livewire::test(\App\Http\Livewire\Admin\Notifications\NotificationLogComponent::class)
            ->assertSee('Your ER number: ER-2026-00001')->assertSee('Document needs attention')->assertSee('not sent yet')
            ->set('filterInstitute', $this->instB->id)->assertSee('Document needs attention')->assertDontSee('Your ER number')
            ->call('clearFilters')->set('filterChannel', 'email')->assertSee('Your ER number')->assertDontSee('Document needs attention')
            ->call('clearFilters')->set('filterEvent', 'document_rejected')->assertSee('Document needs attention')->assertDontSee('Your ER number')
            ->call('clearFilters')->set('filterStatus', 'sent')->assertSee('Your ER number')->assertDontSee('Document needs attention')
            ->call('clearFilters')->set('search', 're-upload')->assertSee('Document needs attention')->assertDontSee('Your ER number')
            ->call('clearFilters')->call('view', $sent->id)->assertDispatchedBrowserEvent('open-notification-log-modal')
            ->assertSet('selected.message', "Dear Alpha,\n\nYour ER number is ER-2026-00001.")
            ->assertSet('selected.extra', ['er' => 'ER-2026-00001'])->assertSet('selected.event', 'ER Issued')
            ->assertSee('Your ER number is ER-2026-00001.');

        $this->actingAs($this->makeUser('institute-admin', $this->instA));
        Livewire::test(\App\Http\Livewire\Admin\Notifications\NotificationLogComponent::class)->assertSee('Your ER number')->assertDontSee('Document needs attention');
    }

    public function test_each_role_gets_its_own_dashboard_with_real_data()
    {
        $super = $this->makeUser('super-admin');
        $adminA = $this->makeUser('institute-admin', $this->instA);
        $accountsA = $this->makeUser('accounts', $this->instA);
        $tmA = $this->makeUser('training-manager', $this->instA);
        $facultyA = $this->makeUser('faculty', $this->instA);
        $neverA = $this->makeUser('hot', $this->instA);
        $adminA->update(['name' => 'Alpha Admin Person', 'last_login_at' => now()->subMinutes(3)]);
        $this->makeUser('institute-admin', $this->instB)->update(['name' => 'Beta Admin Person']);
        AuditTrail::create(['user_id' => $adminA->id, 'institute_id' => $this->instA->id, 'action' => 'create', 'module' => 'users', 'meta' => ['description' => 'Created user: Alpha Clerk']]);
        AuditTrail::create(['user_id' => null, 'institute_id' => $this->instB->id, 'action' => 'create', 'module' => 'users', 'meta' => ['description' => 'Created user: Beta Clerk']]);

        $service = app(\App\Services\DashboardService::class);
        $this->assertSame('super-admin', $service->dashboardFor($super));
        $this->assertSame('institute-admin', $service->dashboardFor($adminA));
        $this->assertSame('accounts', $service->dashboardFor($accountsA));
        $this->assertSame('training-manager', $service->dashboardFor($tmA));
        $this->assertSame('staff', $service->dashboardFor($facultyA));

        // Super Admin: system-wide figures, both institutes, master data, set-up checklist
        $data = $service->superAdmin($super);
        $this->assertGreaterThanOrEqual(2, $data['institutes']->count());
        $this->assertGreaterThanOrEqual(7, $data['logins']['total']);
        $this->assertGreaterThanOrEqual(1, $data['logins']['online']);
        $this->assertSame(10, $data['masters']->count());
        $this->assertCount(12, $data['activity']);
        $this->actingAs($super);
        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Super Admin Dashboard')->assertSee('Users by Role')->assertSee('Master Data')->assertSee('System Set-up')
            ->assertSee($this->instA->name)->assertSee($this->instB->name)->assertSee('Created user: Alpha Clerk')
            ->assertDontSee('Employees By Department')->assertDontSee('Job Applicants'); // template sample data is gone

        // Institute Admin: own institute only
        $data = $service->instituteAdmin($adminA);
        $this->assertSame(5, $data['logins']['total']);   // admin, accounts, TM, faculty, HoT of institute A
        $this->assertSame(4, $data['logins']['never']);
        $this->actingAs($adminA);
        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Institute Dashboard')->assertSee('Institute Set-up')->assertSee($this->instA->name)
            ->assertSee('Created user: Alpha Clerk')->assertDontSee('Created user: Beta Clerk')->assertDontSee('Beta Admin Person');

        $this->actingAs($accountsA);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Accounts Dashboard')->assertSee('Payments to Verify')->assertSee('Collections');

        $this->actingAs($tmA);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Training Manager Dashboard')->assertSee('ID Cards to Sign');

        $this->actingAs($facultyA);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Faculty Dashboard')->assertSee('Attendance to upload per subject')->assertSee('Institute Snapshot');
    }

    public function test_serial_numbers_are_sequential_per_series_and_year()
    {
        $service = app(SerialNumberService::class);
        $year = 2099; // a year no real data uses

        // Group-wide series (ER, receipts): one running number across institutes
        $this->assertSame("ER-{$year}-00001", $service->next('ER', $this->instA, $year));
        $this->assertSame("ER-{$year}-00002", $service->next('ER', $this->instB, $year));
        $this->assertSame("RCPT/{$year}/00001", $service->next('RECEIPT', $this->instA, $year));
        $this->assertSame("MS-{$year}-000001", $service->next('MARKSHEET', null, $year));

        // Per-institute series: separate counters
        $this->assertSame("AC-{$this->instA->code}-{$year}-00001", $service->next('ADMIT_CARD', $this->instA, $year));
        $this->assertSame("AC-{$this->instA->code}-{$year}-00002", $service->next('ADMIT_CARD', $this->instA, $year));
        $this->assertSame("AC-{$this->instB->code}-{$year}-00001", $service->next('ADMIT_CARD', $this->instB, $year));

        // A new year starts again at 1
        $this->assertSame('ER-' . ($year - 1) . '-00001', $service->next('ER', $this->instA, $year - 1));
    }
}
