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
            'code' => Institute::generateCode('Gamma Aviation', 2005),
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

    public function test_only_super_admin_can_open_master_data_and_institute_courses()
    {
        $routes = ['admin.masters.qualifications', 'admin.masters.courses', 'admin.masters.matriculation-boards',
            'admin.masters.higher-secondary-boards', 'admin.masters.religions', 'admin.masters.categories',
            'admin.masters.countries', 'admin.masters.states', 'admin.masters.payment-gateways', 'admin.institute-courses'];

        $this->actingAs($this->super);
        foreach ($routes as $route) {
            $this->get(route($route))->assertOk();
        }
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

        Livewire::test(InstituteCoursesComponent::class, ['institute_id' => $this->institute->id])
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

    public function test_master_data_menu_is_multi_level_and_super_admin_only()
    {
        $sections = collect(MenuService::for($this->super))->keyBy('title');
        $master = $sections['Configuration']['items'][0];

        $this->assertSame('Master Data', $master['label']);
        $this->assertSame(['Academic', 'Personal', 'Location', 'Finance'], array_column($master['children'], 'label'));
        $this->assertSame(
            ['Courses', 'Qualifications', 'Matriculation Boards', 'Higher Secondary Boards'],
            array_column($master['children'][0]['children'], 'label')
        );

        $instAdminSections = array_column(MenuService::for($this->makeUser('institute-admin', $this->institute)), 'title');
        $this->assertNotContains('Configuration', $instAdminSections);
        $this->assertNotContains('Organization', $instAdminSections);

        // Rendered sidebar contains the nested markup
        $this->actingAs($this->super);
        $this->get(route('admin.masters.courses'))
            ->assertSee('submenu-two', false)
            ->assertSee('inside-submenu', false)
            ->assertSee('Higher Secondary Boards');
    }
}
