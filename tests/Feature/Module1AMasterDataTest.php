<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Institutes\InstituteCoursesComponent;
use App\Http\Livewire\Admin\Masters\CategoriesManager;
use App\Http\Livewire\Admin\Masters\CoursesManager;
use App\Http\Livewire\Admin\Masters\PaymentGatewaysManager;
use App\Http\Livewire\Admin\Masters\QualificationsManager;
use App\Http\Livewire\Admin\Masters\ReligionsManager;
use App\Models\Admin\AuditTrail;
use App\Models\Admin\Category;
use App\Models\Admin\Course;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteCourse;
use App\Models\Admin\Qualification;
use App\Models\Admin\Religion;
use App\Models\User;
use App\Services\MenuService;
use Database\Seeders\admin\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module 1A — Master Data + Institute Courses. Runs inside a transaction (rolled back).
 */
class Module1AMasterDataTest extends TestCase
{
    use DatabaseTransactions;

    protected User $super;
    protected Institute $institute;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->super = $this->makeUser('super-admin');
        $this->institute = Institute::create([
            'name' => 'Gamma Aviation ' . uniqid(),
            'established_year' => 2005,
            'code' => Institute::generateCode('GAM', 2005),
            'email' => uniqid() . '@inst.test',
            'phone' => (string) random_int(1000000000, 9999999999),
            'status' => true,
        ]);
    }

    protected function makeUser(string $role, ?Institute $institute = null): User
    {
        $user = User::create([
            'name' => $role,
            'email' => uniqid($role) . '@user.test',
            'password' => bcrypt('password'),
            'institute_id' => $institute?->id,
            'status' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    protected function makeCourse(string $code, bool $active = true): Course
    {
        return Course::create(['name' => "Course {$code}", 'code' => $code, 'duration_months' => 48, 'total_semesters' => 8, 'status' => $active]);
    }

    public function test_super_admin_creates_edits_and_deactivates_master_rows()
    {
        $this->actingAs($this->super);
        $name = 'Plus Two ' . uniqid();

        Livewire::test(QualificationsManager::class)
            ->call('openModal')
            ->set('form.name', $name)
            ->call('save')
            ->assertHasNoErrors();

        $row = Qualification::where('name', $name)->firstOrFail();
        $this->assertTrue($row->status);

        // Duplicate name rejected
        Livewire::test(QualificationsManager::class)->set('form.name', $name)->call('save')->assertHasErrors(['form.name' => 'unique']);

        // Edit: set Inactive
        Livewire::test(QualificationsManager::class)
            ->call('edit', $row->id)
            ->assertSet('form.name', $name)
            ->set('status', 0)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($row->fresh()->status);
        $this->assertNotContains($row->id, Qualification::active()->pluck('id')->all());
        $this->assertTrue(AuditTrail::where('module', 'qualifications')->where('action', 'update')->exists());
    }

    public function test_course_validation_and_unique_code()
    {
        $this->actingAs($this->super);
        $code = 'B1-' . uniqid();

        Livewire::test(CoursesManager::class)
            ->set('form.name', 'AME B1.1')
            ->set('form.code', $code)
            ->set('form.duration_months', 48)
            ->set('form.total_semesters', 8)
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(CoursesManager::class)
            ->set('form.name', 'Other')
            ->set('form.code', $code)
            ->set('form.duration_months', 0)
            ->call('save')
            ->assertHasErrors(['form.code' => 'unique', 'form.duration_months' => 'min', 'form.total_semesters' => 'required']);
    }

    public function test_category_belongs_to_religion_and_in_use_religion_cannot_be_deleted()
    {
        $this->actingAs($this->super);
        $religion = Religion::create(['name' => 'Religion ' . uniqid(), 'status' => true]);

        Livewire::test(CategoriesManager::class)->set('form.name', 'OBC')->call('save')->assertHasErrors(['form.religion_id' => 'required']);

        Livewire::test(CategoriesManager::class)
            ->set('form.religion_id', $religion->id)
            ->set('form.name', 'OBC')
            ->call('save')
            ->assertHasNoErrors();

        // Same name allowed under another religion, not twice under the same one
        Livewire::test(CategoriesManager::class)
            ->set('form.religion_id', $religion->id)
            ->set('form.name', 'OBC')
            ->call('save')
            ->assertHasErrors(['form.name' => 'unique']);

        // Religion is in use by a category → delete refused, row kept
        Livewire::test(ReligionsManager::class)
            ->call('confirmDelete', $religion->id)
            ->call('delete')
            ->assertDispatchedBrowserEvent('show-toast', fn ($name, $data) => $data['type'] === 'warning');
        $this->assertNotSoftDeleted($religion);

        // Unused category can be deleted
        $category = Category::where('religion_id', $religion->id)->first();
        Livewire::test(CategoriesManager::class)->call('confirmDelete', $category->id)->call('delete');
        $this->assertSoftDeleted($category);
    }

    public function test_payment_gateway_type_must_be_known()
    {
        $this->actingAs($this->super);

        Livewire::test(PaymentGatewaysManager::class)
            ->set('form.name', 'GPay ' . uniqid())
            ->set('form.code', 'gpay_' . substr(uniqid(), -5))
            ->set('form.type', 'crypto')
            ->set('form.sort_order', 1)
            ->call('save')
            ->assertHasErrors(['form.type' => 'in']);

        Livewire::test(PaymentGatewaysManager::class)
            ->set('form.name', 'GPay ' . uniqid())
            ->set('form.code', 'gpay_' . substr(uniqid(), -5))
            ->set('form.type', 'upi')
            ->set('form.sort_order', 1)
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_only_super_admin_can_open_master_data()
    {
        $routes = ['admin.masters.qualifications', 'admin.masters.courses', 'admin.masters.matriculation-boards',
            'admin.masters.higher-secondary-boards', 'admin.masters.religions', 'admin.masters.categories',
            'admin.masters.countries', 'admin.masters.states', 'admin.masters.payment-gateways'];

        $this->actingAs($this->super);
        foreach ($routes as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('admin.institute-courses'))->assertOk()->assertSee('All institutes'); // Super Admin sees (and filters by) every institute
        $this->get(route('admin.institute-courses.institute', $this->institute->id))->assertOk();

        $this->actingAs($this->makeUser('institute-admin', $this->institute));
        foreach ($routes as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }

    public function test_assign_courses_to_institute_and_reassign_after_removal()
    {
        $this->actingAs($this->super);
        $c1 = $this->makeCourse('C1-' . uniqid());
        $c2 = $this->makeCourse('C2-' . uniqid());
        $inactive = $this->makeCourse('CX-' . uniqid(), false);

        Livewire::test(InstituteCoursesComponent::class, ['institute' => $this->institute->id])
            ->call('openModal')
            ->assertSet('institute_id', $this->institute->id)
            ->set('course_ids', [$c1->id, $c2->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEqualsCanonicalizing([$c1->id, $c2->id], $this->institute->offeredCourses()->pluck('courses.id')->all());

        // Inactive master course cannot be assigned
        Livewire::test(InstituteCoursesComponent::class)
            ->set('institute_id', $this->institute->id)
            ->set('course_ids', [$inactive->id])
            ->call('save')
            ->assertHasErrors(['course_ids.0']);

        // Deactivate one offering → no longer offered
        $link = InstituteCourse::where('institute_id', $this->institute->id)->where('course_id', $c2->id)->first();
        Livewire::test(InstituteCoursesComponent::class)->call('edit', $link->id)->set('status', 0)->call('save');
        $this->assertSame([$c1->id], $this->institute->offeredCourses()->pluck('courses.id')->all());

        // Remove, then assign again → the same row is restored (unique institute + course)
        Livewire::test(InstituteCoursesComponent::class)->call('confirmDelete', $link->id)->call('delete');
        $this->assertSoftDeleted($link);

        Livewire::test(InstituteCoursesComponent::class)
            ->set('institute_id', $this->institute->id)
            ->set('course_ids', [$c2->id])
            ->call('save')
            ->assertHasNoErrors();
        $this->assertNotSoftDeleted($link);

        // A course offered by an institute is "in use" → cannot be deleted from Master Data
        Livewire::test(CoursesManager::class)->call('confirmDelete', $c1->id)->call('delete');
        $this->assertNotSoftDeleted($c1);
    }

    public function test_institute_admin_manages_only_own_institute_courses()
    {
        $other = Institute::create([
            'name' => 'Delta Aero ' . uniqid(),
            'established_year' => 2012,
            'code' => Institute::generateCode('DEL', 2012),
            'email' => uniqid() . '@inst.test',
            'phone' => (string) random_int(1000000000, 9999999999),
            'status' => true,
        ]);
        $c1 = $this->makeCourse('D1-' . uniqid());
        $otherLink = InstituteCourse::create(['institute_id' => $other->id, 'course_id' => $c1->id, 'status' => true]);

        $admin = $this->makeUser('institute-admin', $this->institute);
        $this->actingAs($admin);

        // Own institute only → no institute filter or Institute column in the list
        $this->get(route('admin.institute-courses'))->assertOk()->assertDontSee('All institutes')->assertSee('Course Code');
        $this->get(route('admin.institute-courses.institute', $this->institute->id))->assertOk();
        $this->get(route('admin.institute-courses.institute', $other->id))->assertNotFound();

        // Page is locked to own institute; a forged institute_id is ignored on save.
        Livewire::test(InstituteCoursesComponent::class)
            ->assertSet('scopeInstituteId', $this->institute->id)
            ->call('openModal')
            ->set('institute_id', $other->id)
            ->set('course_ids', [$c1->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(InstituteCourse::where('institute_id', $this->institute->id)->where('course_id', $c1->id)->exists());
        $this->assertSame(1, InstituteCourse::where('institute_id', $other->id)->count());

        // Cannot edit or delete another institute's course link.
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::test(InstituteCoursesComponent::class)->call('edit', $otherLink->id);
    }

    public function test_courses_list_filters_and_status_switch()
    {
        $offered = $this->makeCourse('OF-' . uniqid());
        $spare = $this->makeCourse('SP-' . uniqid());
        InstituteCourse::create(['institute_id' => $this->institute->id, 'course_id' => $offered->id, 'status' => true]);

        $this->actingAs($this->super);
        $this->get(route('admin.masters.courses'))->assertOk()->assertSee($offered->code)->assertSee('Not offered');

        Livewire::test(CoursesManager::class)
            ->set('filterStatus', 'unassigned')
            ->assertViewHas('courses', fn ($rows) => $rows->contains('id', $spare->id) && !$rows->contains('id', $offered->id))
            ->call('clearFilters')
            ->set('search', $offered->code)
            ->assertViewHas('courses', fn ($rows) => $rows->pluck('id')->all() === [$offered->id] && $rows->first()->offerings_count === 1)
            ->call('toggleStatus', $spare->id);

        $this->assertFalse($spare->fresh()->status);
        $this->assertTrue(AuditTrail::where('module', 'courses')->where('action', 'update')->where('reference_id', $spare->id)->exists());
    }

    public function test_institute_courses_list_switch_and_needs_fees_filter()
    {
        $withFees = $this->makeCourse('F1-' . uniqid());
        $noFees = $this->makeCourse('N1-' . uniqid());
        $feeLink = InstituteCourse::create(['institute_id' => $this->institute->id, 'course_id' => $withFees->id, 'status' => true]);
        $bareLink = InstituteCourse::create(['institute_id' => $this->institute->id, 'course_id' => $noFees->id, 'status' => true]);
        \App\Models\Admin\CourseFee::create(['institute_id' => $this->institute->id, 'course_id' => $withFees->id, 'fee_head' => 'Admission fee', 'amount' => 1000, 'status' => true]);

        $this->actingAs($this->makeUser('institute-admin', $this->institute));

        // "Needs fees" = active offerings without a fee structure.
        Livewire::test(InstituteCoursesComponent::class)
            ->assertViewHas('stats', fn ($s) => $s['total'] === 2 && $s['setup'] === 1)
            ->set('filterStatus', 'setup')
            ->assertViewHas('offerings', fn ($rows) => $rows->pluck('id')->all() === [$bareLink->id])
            ->set('search', $withFees->code)
            ->assertViewHas('offerings', fn ($rows) => $rows->isEmpty())
            ->call('clearFilters')
            ->assertViewHas('offerings', fn ($rows) => $rows->count() === 2 && $rows->firstWhere('id', $feeLink->id)->fee_heads_count === 1);

        // The list switch deactivates the offering and is audited.
        Livewire::test(InstituteCoursesComponent::class)->call('toggleStatus', $bareLink->id);
        $this->assertFalse($bareLink->fresh()->status);
        $this->assertTrue(AuditTrail::where('module', 'institute_courses')->where('action', 'update')->where('reference_id', $bareLink->id)->exists());

        // View-only users cannot use the switch.
        \Spatie\Permission\Models\Role::findByName('institute-admin')->revokePermissionTo('institute_courses.manage');
        $this->actingAs($this->makeUser('institute-admin', $this->institute));
        Livewire::test(InstituteCoursesComponent::class)->call('toggleStatus', $bareLink->id)->assertForbidden();
        $this->assertFalse($bareLink->fresh()->status);
    }

    public function test_institute_courses_permissions_view_and_manage()
    {
        $admin = $this->makeUser('institute-admin', $this->institute);
        $instAdminRole = \Spatie\Permission\Models\Role::findByName('institute-admin');

        // View only: page opens, but no assign / edit
        $instAdminRole->revokePermissionTo('institute_courses.manage');
        $this->actingAs($admin->fresh());
        $this->get(route('admin.institute-courses'))->assertOk()->assertDontSee('Assign Courses');
        Livewire::test(InstituteCoursesComponent::class)->call('openModal')->assertForbidden();

        // No view permission: page forbidden, menu item hidden
        $instAdminRole->revokePermissionTo('institute_courses.view');
        $this->actingAs($admin->fresh());
        $this->get(route('admin.institute-courses'))->assertForbidden();
        $labels = collect(MenuService::flatFor($admin->fresh()))->flatMap(fn ($s) => array_column($s['items'], 'label'));
        $this->assertFalse($labels->contains('Institute Courses'));

        // Roles without the permission (e.g. Accounts) cannot open it
        $this->actingAs($this->makeUser('accounts', $this->institute));
        $this->get(route('admin.institute-courses'))->assertForbidden();
    }

    /**
     * Regression: Livewire 2 rebuilds the page URL after every action from public properties
     * named like the route parameters. A form field with the same name (e.g. $institute_id on
     * /institutes/{institute_id}/courses) is reset to null after save → "Missing required parameter".
     */
    public function test_livewire_route_parameters_do_not_clash_with_public_properties()
    {
        foreach (app('router')->getRoutes() as $route) {
            $class = $route->getActionName();
            if (!is_string($class) || !is_subclass_of($class, \Livewire\Component::class)) {
                continue;
            }

            $publicProps = collect((new \ReflectionClass($class))->getProperties(\ReflectionProperty::IS_PUBLIC))
                ->map->getName()->all();

            $this->assertEmpty(
                array_intersect($route->parameterNames(), $publicProps),
                "Route [{$route->uri()}] parameter clashes with a public property of {$class}."
            );
        }
    }

    public function test_assign_courses_from_institute_page_keeps_working_after_save()
    {
        $this->actingAs($this->super);
        $course = $this->makeCourse('R1-' . uniqid());

        // Same flow as the reported error: open Institutes → Courses, assign, save.
        $this->get(route('admin.institute-courses.institute', $this->institute->id))->assertOk();

        Livewire::test(InstituteCoursesComponent::class, ['institute' => $this->institute->id])
            ->call('openModal')
            ->set('course_ids', [$course->id])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('scopeInstituteId', $this->institute->id);

        $this->assertTrue($this->institute->offeredCourses()->where('courses.id', $course->id)->exists());
    }

    public function test_master_data_menu_is_multi_level_and_super_admin_only()
    {
        $sections = collect(MenuService::for($this->super))->keyBy('title');
        $master = collect($sections['Main']['items'])->firstWhere('label', 'Master Data');

        $this->assertSame('Master Data', $master['label']);
        $this->assertSame(['Academic', 'Personal', 'Location', 'Finance'], array_column($master['children'], 'label'));
        $this->assertSame(
            ['Academic Years', 'Courses', 'Qualifications', 'Matriculation Boards', 'Higher Secondary Boards', '12th Subjects', '12th Subject Groups', 'Course Eligibility'],
            array_column($master['children'][0]['children'], 'label')
        );

        $instAdminSections = collect(MenuService::for($this->makeUser('institute-admin', $this->institute)))->keyBy('title');
        $this->assertNotContains('Master Data', array_column($instAdminSections['Main']['items'], 'label'));
        // Institute Admin sees Institute Management without Institutes (permission-based).
        $this->assertSame(['Institute Courses', 'Batches', 'Add-on Programs', 'Academic Periods', 'Fee Structure', 'Payment Settings'], array_column($instAdminSections['Institute']['items'][0]['children'], 'label'));

        // Rendered sidebar contains the nested markup
        $this->actingAs($this->super);
        $this->get(route('admin.masters.courses'))
            ->assertSee('submenu-two', false)
            ->assertSee('inside-submenu', false)
            ->assertSee('Higher Secondary Boards');
    }
}
