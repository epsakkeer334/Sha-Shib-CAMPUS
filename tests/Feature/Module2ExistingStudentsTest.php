<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Students\BulkUpdateComponent;
use App\Http\Livewire\Admin\Students\StudentEnrollmentComponent;
use App\Http\Livewire\Admin\Students\StudentsComponent;
use App\Models\Admin\AcademicYear;
use App\Models\Admin\AuditTrail;
use App\Models\Admin\Batch;
use App\Models\Admin\Category;
use App\Models\Admin\Country;
use App\Models\Admin\Course;
use App\Models\Admin\EnrollmentApproval;
use App\Models\Admin\HigherSecondaryBoard;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteCourse;
use App\Models\Admin\MatriculationBoard;
use App\Models\Admin\Qualification;
use App\Models\Admin\Religion;
use App\Models\Admin\SerialCounter;
use App\Models\Admin\State;
use App\Models\Admin\Student;
use App\Models\Admin\StudentErNumber;
use App\Models\Admin\StudentImport;
use App\Models\User;
use App\Services\ErNumberService;
use App\Services\PeriodService;
use App\Services\StudentBulkService;
use Database\Seeders\admin\MasterDataSeeder;
use Database\Seeders\admin\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module 2 steps 2.8 – 2.9 — existing students: ER number management and bulk update.
 * Runs inside a transaction on the *_testing database (rolled back).
 */
class Module2ExistingStudentsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $super;
    protected User $admin;
    protected Institute $inst;
    protected Institute $other;
    protected Course $course;
    protected Batch $batch;
    protected Religion $religion;
    protected Country $country;
    protected State $state;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
        $this->seed(MasterDataSeeder::class);

        $this->inst = $this->makeInstitute('Exist Aviation', 2002);
        $this->other = $this->makeInstitute('Other Aero', 2011);
        $this->super = $this->makeUser('super-admin');
        $this->admin = $this->makeUser('institute-admin', $this->inst);

        $this->course = Course::create(['name' => 'AME B1.1', 'code' => 'XB1-' . substr(uniqid(), -5), 'duration_months' => 24, 'total_semesters' => 4, 'period_months' => 6, 'status' => true]);
        foreach ([$this->inst, $this->other] as $i) {
            InstituteCourse::create(['institute_id' => $i->id, 'course_id' => $this->course->id, 'status' => true]);
        }
        AcademicYear::firstOrCreate(['name' => '2025-26'], ['start_date' => '2025-06-01', 'end_date' => '2026-05-31', 'status' => true]);
        $this->batch = Batch::create(['institute_id' => $this->inst->id, 'course_id' => $this->course->id, 'name' => 'June 2025', 'code' => 'XB-' . substr(uniqid(), -6), 'start_date' => '2025-06-01', 'status' => true]);
        app(PeriodService::class)->generate($this->inst, $this->course, '2025-06-01', $this->batch); // 4 semesters of 6 months

        $this->religion = Religion::where('name', 'Hindu')->firstOrFail();
        $this->country = Country::create(['name' => 'Existland ' . uniqid(), 'code' => substr(uniqid(), -6), 'status' => true]);
        $this->state = State::create(['country_id' => $this->country->id, 'name' => 'Exist State', 'status' => true]);
    }

    protected function makeInstitute(string $name, int $year): Institute
    {
        return Institute::create([
            'name' => $name . ' ' . uniqid(), 'established_year' => $year, 'code' => Institute::generateCode(strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3)), $year),
            'email' => uniqid() . '@inst.test', 'phone' => (string) random_int(1000000000, 9999999999), 'status' => true,
        ]);
    }

    protected function makeUser(string $role, ?Institute $institute = null): User
    {
        $user = User::create(['name' => $role, 'email' => uniqid($role) . '@user.test', 'password' => bcrypt('password'), 'institute_id' => $institute?->id, 'status' => true]);
        $user->assignRole($role);

        return $user;
    }

    protected function student(array $override = []): Student
    {
        return Student::create(array_merge([
            'institute_id' => $this->inst->id, 'course_id' => $this->course->id, 'batch_id' => $this->batch->id, 'first_name' => 'Old', 'last_name' => 'Timer',
            'dob' => '2005-03-03', 'gender' => 'male', 'qualification_id' => Qualification::first()->id,
            'email' => uniqid('ex') . '@mail.test', 'phone' => '+919800000001', 'emergency_contact' => '+919800000002',
            'religion_id' => $this->religion->id, 'category_id' => Category::where('religion_id', $this->religion->id)->first()->id,
            'joining_date' => '2025-06-01', 'onboarding_deadline' => '2025-07-01', 'status' => 'draft',
        ], $override));
    }

    // ------------------------------------------------------------------ 2.8 ER numbers

    public function test_institute_admin_assigns_an_existing_er_number_and_admits_the_student()
    {
        $this->actingAs($this->admin);
        $student = $this->student();

        $page = Livewire::test(StudentEnrollmentComponent::class, ['student' => $student->id])
            ->assertSee('Assign ER number')->call('openErModal', 'assign')->assertSet('erSemester', '1');

        // format + uniqueness
        $page->set('erValue', 'x')->call('saveEr')->assertHasErrors('erValue');
        $taken = $this->student(['er_number' => 'ER-2024-00111', 'status' => 'er_issued']);
        $page->set('erValue', 'er-2024-00111')->call('saveEr')->assertHasErrors('erValue');

        $page->set('erValue', ' er-2024-00087 ')->set('erSemester', '3')->set('erCardIssued', true)->set('erFormArchived', true)
            ->set('erReason', 'From the ER register')->call('saveEr')->assertHasNoErrors()
            ->assertDispatchedBrowserEvent('show-toast', fn ($n, $d) => str_contains($d['message'], 'ER-2024-00087'));

        $student->refresh();
        $this->assertSame('ER-2024-00087', $student->er_number);
        $this->assertSame('active', $student->status);           // card already issued
        $this->assertSame('existing', $student->admission_source);
        foreach ([EnrollmentApproval::DOCUMENTS, EnrollmentApproval::FEES] as $gate) {
            $approval = $student->gate($gate);
            $this->assertSame('approved', $approval->status);
            $this->assertStringContainsString('Existing student', $approval->remarks);
        }
        $this->assertSame('archived', $student->erRequest->status);
        $this->assertSame('issued', $student->idCard->status);
        $this->assertSame(3, (int) $student->currentPeriod()->first()->period_no);   // semester placed
        $this->assertSame(2, $student->periods()->where('status', 'completed')->count());
        $this->assertSame(0, $student->dues()->count());                              // no automatic fees
        $this->assertSame('assigned', $student->erHistory()->first()->action);

        // the series counter of 2024 moved past 87 → the next number in 2024 can never clash
        $this->assertGreaterThanOrEqual(87, (int) SerialCounter::where('series_key', 'ER')->whereNull('institute_id')->where('year', 2024)->value('last_value'));
        $this->assertNotNull($taken);
    }

    public function test_generate_change_clear_and_permissions()
    {
        $this->actingAs($this->admin);
        $student = $this->student();

        // generate from the series
        Livewire::test(StudentEnrollmentComponent::class, ['student' => $student->id])->call('openErModal', 'assign')
            ->set('erMode', 'generate')->call('saveEr')->assertHasNoErrors();
        $student->refresh();
        $this->assertMatchesRegularExpression('/^ER-' . date('Y') . '-\d{5}$/', $student->er_number);
        $this->assertSame('er_issued', $student->status);
        $this->assertSame('generated', $student->erHistory()->first()->action);
        $first = $student->er_number;

        // the ID card was printed → a change flags it for reprint; reason required
        $student->idCard->update(['print_count' => 1, 'tm_signature_status' => 'physically_signed', 'status' => 'issued']);
        $page = Livewire::test(StudentEnrollmentComponent::class, ['student' => $student->id])->call('openErModal', 'change')
            ->set('erValue', 'ER-OLD-555')->call('saveEr')->assertHasErrors('erReason');
        $page->set('erReason', 'Typing error in the register')->call('saveEr')->assertHasNoErrors();
        $student->refresh();
        $this->assertSame('ER-OLD-555', $student->er_number);
        $this->assertSame('reprinted', $student->idCard->status);
        $this->assertSame('changed', $student->erHistory()->first()->action);
        $this->assertTrue(AuditTrail::where('action', 'change_er')->exists());

        // the old number stays reserved for this student's history
        $another = $this->student();
        $this->assertStringContainsString('used before', app(ErNumberService::class)->problem($first, $another));

        // soft-deleted students keep their number
        $deleted = $this->student(['er_number' => 'ER-DEL-001', 'status' => 'er_issued']);
        $deleted->delete();
        $this->assertNotNull(app(ErNumberService::class)->problem('er-del-001', $another));

        // clear: Super Admin only, and not once printed
        Livewire::test(StudentEnrollmentComponent::class, ['student' => $student->id])->call('openErModal', 'clear')->set('erReason', 'Wrong student')->call('saveEr')->assertForbidden();
        $this->actingAs($this->super);
        Livewire::test(StudentEnrollmentComponent::class, ['student' => $student->id])->call('openErModal', 'clear')->set('erReason', 'Wrong student')->call('saveEr')
            ->assertHasErrors('erReason'); // ID card already printed
        $fresh = $this->student();
        app(ErNumberService::class)->assign($fresh, 'ER-TMP-9');
        Livewire::test(StudentEnrollmentComponent::class, ['student' => $fresh->id])->call('openErModal', 'clear')->set('erReason', 'Given by mistake')->call('saveEr')->assertHasNoErrors();
        $this->assertNull($fresh->fresh()->er_number);
        $this->assertSame('cleared', $fresh->erHistory()->first()->action);

        // Accounts cannot manage ER numbers
        $this->actingAs($this->makeUser('accounts', $this->inst));
        Livewire::test(StudentEnrollmentComponent::class, ['student' => $another->id])->assertDontSee('Assign ER number')->call('openErModal', 'assign')->assertForbidden();
    }

    public function test_gates_issue_writes_the_history()
    {
        $this->actingAs($this->admin);
        $student = $this->student(['status' => 'pending_approval']);
        foreach ([EnrollmentApproval::DOCUMENTS, EnrollmentApproval::FEES] as $gate) {
            EnrollmentApproval::create(['institute_id' => $this->inst->id, 'student_id' => $student->id, 'gate' => $gate, 'status' => 'approved', 'approved_at' => now()]);
        }
        app(\App\Services\OnboardingService::class)->issueErIfReady($student->fresh());
        $this->assertSame('issued', StudentErNumber::where('student_id', $student->id)->value('action'));
    }

    // ------------------------------------------------------------------ 2.9 bulk update

    protected function csv(array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, array_keys(StudentBulkService::COLUMNS));
        foreach ($rows as $row) {
            fputcsv($out, array_map(fn ($col) => $row[$col] ?? '', array_keys(StudentBulkService::COLUMNS)));
        }
        rewind($out);

        return stream_get_contents($out);
    }

    protected function newRow(array $override = []): array
    {
        return array_merge([
            'course_code' => $this->course->code, 'batch_code' => $this->batch->code, 'joining_date' => '01-06-2025',
            'first_name' => 'Bulk', 'last_name' => 'New', 'dob' => '2006-01-15', 'gender' => 'F', 'email' => uniqid('bk') . '@mail.test',
            'phone' => '+919811111111', 'emergency_contact' => '+919822222222', 'qualification' => Qualification::first()->name,
            'religion' => 'hindu', 'category' => 'General',
        ], $override);
    }

    public function test_template_validation_preview_and_apply()
    {
        $this->actingAs($this->admin);
        $service = app(StudentBulkService::class);
        $existing = $this->student(['er_number' => 'ER-2025-04001', 'status' => 'er_issued', 'admission_source' => 'existing']);
        $foreign = $this->student(['institute_id' => $this->other->id]);

        // template: pre-filled with the institute's students only
        $csv = $service->templateCsv($service->studentsQuery(['institute' => $this->inst->id])->get());
        $this->assertStringContainsString('student_id,institute_code,er_number', $csv);
        $this->assertStringContainsString('ER-2025-04001', $csv);
        $this->assertStringNotContainsString((string) $foreign->email, $csv);

        $dupEmail = uniqid('dup') . '@mail.test';
        $rows = [
            2 => $this->newRow(['er_number' => 'ER-2025-04500', 'current_semester' => '2', 'status' => 'active']),         // new, admitted
            3 => $this->newRow(['first_name' => 'Draft', 'email' => uniqid('dr') . '@mail.test']),                          // new, draft
            4 => ['student_id' => (string) $existing->id, 'phone' => '+919833333333', 'batch_code' => $this->batch->code],    // update phone; batch unchanged
            5 => ['student_id' => (string) $existing->id . '9999'],                                                         // unknown id
            6 => ['student_id' => (string) $foreign->id, 'phone' => '+919844444444'],                                       // other institute
            7 => $this->newRow(['religion' => 'Hindhu']),                                                                   // typo → suggestion
            8 => $this->newRow(['email' => $dupEmail]),
            9 => $this->newRow(['email' => $dupEmail]),                                                                     // duplicate in file
            10 => $this->newRow(['status' => 'active']),                                                                    // active needs ER
            11 => ['student_id' => (string) $existing->id],                                                                 // nothing changed (merged)
        ];
        $preview = $service->validateRows($rows, $this->admin);
        $by = collect($preview['rows'])->keyBy('line');

        $this->assertSame('new', $by[2]['action']);
        $this->assertSame('assign', $by[2]['er_action']);
        $this->assertSame('new', $by[3]['action']);
        $this->assertSame('update', $by[4]['action']);
        $this->assertSame(['phone'], array_keys($by[4]['changes']));
        $this->assertArrayHasKey('student_id', $by[5]['errors']);
        $this->assertArrayHasKey('student_id', $by[6]['errors']);
        $this->assertStringContainsString('did you mean "Hindu"', $by[7]['errors']['religion']);
        $this->assertStringContainsString('rows 8, 9', $by[8]['errors']['email']);
        $this->assertArrayHasKey('status', $by[10]['errors']);
        $this->assertSame('unchanged', $by[11]['action']);

        // the error report lists only the error rows with their messages
        $report = $service->errorReportCsv($preview);
        $this->assertStringContainsString('did you mean', $report);
        $this->assertStringNotContainsString('Draft', $report);

        $result = $service->apply($preview['rows'], 99);
        $this->assertSame(2, $result['created']);
        $this->assertSame(1, $result['updated']);

        $admitted = Student::where('er_number', 'ER-2025-04500')->firstOrFail();
        $this->assertSame('active', $admitted->status);
        $this->assertSame('import', $admitted->admission_source);
        $this->assertSame('female', $admitted->gender);
        $this->assertSame('2025-06-01', $admitted->joining_date->toDateString());
        $this->assertSame(2, (int) $admitted->currentPeriod()->first()->period_no);
        $this->assertSame('draft', Student::where('first_name', 'Draft')->firstOrFail()->status);
        $this->assertSame('+919833333333', $existing->fresh()->phone);
        $this->assertSame('Old', $existing->fresh()->first_name); // blank cells kept
        $this->assertTrue(AuditTrail::where('module', 'students')->where('meta', 'like', '%bulk update #99%')->exists());

        // a row changed by someone else after the preview is skipped
        $stale = $service->validateRows([2 => ['student_id' => (string) $existing->id, 'city' => 'Kochi']], $this->admin);
        $existing->fresh()->update(['parent_name' => 'Edited meanwhile']);
        \Illuminate\Support\Facades\DB::table('students')->where('id', $existing->id)->update(['updated_at' => now()->addMinute()]);
        $this->assertCount(1, $service->apply($stale['rows'])['skipped']);
    }

    public function test_bulk_rules_for_course_status_er_permission_and_super_admin()
    {
        $service = app(StudentBulkService::class);
        $admitted = $this->student(['er_number' => 'ER-2025-04777', 'status' => 'active']);
        $draft = $this->student();

        $this->actingAs($this->admin);
        $other = Course::create(['name' => 'Other', 'code' => 'XO-' . substr(uniqid(), -5), 'duration_months' => 12, 'total_semesters' => 2, 'status' => true]);
        InstituteCourse::create(['institute_id' => $this->inst->id, 'course_id' => $other->id, 'status' => true]);
        $by = collect($service->validateRows([
            2 => ['student_id' => (string) $admitted->id, 'course_code' => $other->code],     // admitted → no course change
            3 => ['student_id' => (string) $admitted->id, 'status' => 'er_issued'],          // active → back not allowed
            4 => ['student_id' => (string) $admitted->id, 'status' => 'alumni'],             // Super Admin only
            5 => ['student_id' => (string) $draft->id, 'current_semester' => '2'],           // needs ER
            6 => ['student_id' => (string) $admitted->id, 'current_semester' => '9'],        // > periods
            7 => ['student_id' => (string) $admitted->id, 'er_number' => 'ER-2025-04888'],   // change (has permission)
        ], $this->admin)['rows'])->keyBy('line');
        $this->assertArrayHasKey('course_code', $by[2]['errors']);
        $this->assertArrayHasKey('status', $by[3]['errors']);
        $this->assertArrayHasKey('status', $by[4]['errors']);
        $this->assertArrayHasKey('current_semester', $by[5]['errors']);
        $this->assertArrayHasKey('current_semester', $by[6]['errors']);
        $this->assertSame('change', $by[7]['er_action']);

        // without students.manage_er the ER column is ignored with a warning
        $limited = $this->makeUser('institute-admin', $this->inst);
        \Spatie\Permission\Models\Role::findByName('institute-admin')->revokePermissionTo('students.manage_er');
        $limited->forgetCachedPermissions();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $row = $service->validateRows([2 => ['student_id' => (string) $draft->id, 'er_number' => 'ER-X-1', 'phone' => '+919855555555']], $limited->fresh())['rows'][0];
        $this->assertNull($row['er_action']);
        $this->assertStringContainsString('ER number ignored', implode(' ', $row['warnings']));

        // Super Admin: institute_code required for new rows; academic columns validated as a complete record
        $this->actingAs($this->super);
        $by = collect($service->validateRows([
            2 => $this->newRow(),
            3 => $this->newRow(['institute_code' => $this->inst->code, 'board_10' => MatriculationBoard::first()->name, 'mark_type_10' => 'cgpa', 'mark_10' => '12']),
            4 => $this->newRow(['institute_code' => $this->inst->code, 'board_10' => MatriculationBoard::first()->name, 'mark_type_10' => '%', 'mark_10' => '90',
                'board_12' => HigherSecondaryBoard::first()->name, 'stream_12' => 'pcm', 'mark_type_12' => 'percentage', 'mark_12' => '75']),
        ], $this->super)['rows'])->keyBy('line');
        $this->assertArrayHasKey('institute_code', $by[2]['errors']);
        $this->assertArrayHasKey('mark_10', $by[3]['errors']);
        $this->assertSame('new', $by[4]['action']);
    }

    public function test_bulk_update_page_upload_preview_apply_and_permissions()
    {
        $this->actingAs($this->admin);
        $existing = $this->student(['er_number' => 'ER-2025-05001', 'status' => 'er_issued']);

        Livewire::test(BulkUpdateComponent::class)->assertSee('Download')->assertSee('Blank template')
            ->call('downloadBlank')->assertFileDownloaded('camp-students-template.csv');

        $file = UploadedFile::fake()->createWithContent('students.csv', "\xEF\xBB\xBF" . $this->csv([
            ['student_id' => (string) $existing->id, 'city' => 'Thrissur'],
            $this->newRow(['email' => 'not-an-email']),
            ['student_id' => 'example', 'first_name' => 'Example'], // the template's example row is skipped
        ]));
        $page = Livewire::test(BulkUpdateComponent::class)->set('file', $file)->assertHasNoErrors();
        $import = StudentImport::latest('id')->firstOrFail();
        $this->assertSame(2, $import->rows_total);
        $this->assertSame(1, $import->rows_updated);
        $this->assertSame(1, $import->rows_failed);
        $page->assertSet('importId', $import->id)->assertSet('show', 'error')->assertSee('Error report')->set('show', 'all')->assertSee('Thrissur');
        $page->call('downloadErrors', $import->id)->assertFileDownloaded("camp-import-{$import->id}-errors.csv");

        $page->call('apply');
        $this->assertSame('Thrissur', $existing->fresh()->city);
        $this->assertSame('applied', $import->fresh()->status);
        $page->call('apply')->assertDispatchedBrowserEvent('show-toast', fn ($n, $d) => str_contains($d['message'], 'already applied'));

        // a non-template file
        Livewire::test(BulkUpdateComponent::class)->set('file', UploadedFile::fake()->createWithContent('x.csv', "name,age\nA,1\n"))->assertHasErrors('file');

        // permissions: Accounts cannot open the page
        $this->actingAs($this->makeUser('accounts', $this->inst));
        $this->get(route('admin.students.bulk'))->assertForbidden();
    }

    public function test_inline_grid_on_the_students_list()
    {
        $this->actingAs($this->admin);
        $a = $this->student(['first_name' => 'Gridone']);
        $b = $this->student(['first_name' => 'Gridtwo', 'er_number' => 'ER-2025-06001', 'status' => 'er_issued']);

        $page = Livewire::test(StudentsComponent::class)->assertSee('Edit this page in a grid')->call('enterGrid')->assertSet('grid', true)
            ->assertSee('Save changes');
        $page->set("gridRows.{$a->id}.phone", '+919866666666')
            ->set("gridRows.{$b->id}.current_semester", '2')
            ->set("gridRows.{$b->id}.email", 'bad-email')
            ->call('saveGrid');
        $this->assertSame('+919866666666', $a->fresh()->phone);
        $this->assertArrayHasKey('email', $page->get('gridErrors')[$b->id]);
        $this->assertNull($b->fresh()->currentPeriod()->first()); // row with an error is not saved

        $page->set("gridRows.{$b->id}.email", 'gridtwo@mail.test')->call('saveGrid')->assertSet('grid', false);
        $this->assertSame(2, (int) $b->fresh()->currentPeriod()->first()->period_no);
        $this->assertSame('gridtwo@mail.test', $b->fresh()->email);

        // ER typed in the grid admits a draft student as existing
        Livewire::test(StudentsComponent::class)->call('enterGrid')->set("gridRows.{$a->id}.er_number", 'er-2025-06002')->call('saveGrid')->assertSet('grid', false);
        $this->assertSame('ER-2025-06002', $a->fresh()->er_number);
        $this->assertSame('er_issued', $a->fresh()->status);
    }
}
