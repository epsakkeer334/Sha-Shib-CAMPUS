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
            'code' => Institute::generateCode($name, $year),
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
        $this->assertSame(['Institutes', 'Institute Courses', 'Fee Structure', 'Payment Settings'], array_column($instituteGroup['children'], 'label'));
        $this->assertSame(['Students', 'Onboarding'], array_column($groups['Admissions']['items'], 'label'));
        $this->assertSame(['Document Verification', 'Payment Verification', 'ER & ID Cards'], array_column($groups['Admissions']['items'][1]['children'], 'label'));
        $this->assertSame(['Users & Permissions', 'Master Data', 'Monitoring'], array_column($groups['Administration']['items'], 'label')); // System: no page built yet
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
