<?php

namespace App\Services;

use App\Models\Admin\AcademicYear;
use App\Models\Admin\AuditTrail;
use App\Models\Admin\Batch;
use App\Models\Admin\Category;
use App\Models\Admin\Country;
use App\Models\Admin\Course;
use App\Models\Admin\CourseFee;
use App\Models\Admin\CoursePeriod;
use App\Models\Admin\EnrollmentApproval;
use App\Models\Admin\ErRequest;
use App\Models\Admin\HigherSecondaryBoard;
use App\Models\Admin\IdCard;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteCourse;
use App\Models\Admin\InstitutePaymentGateway;
use App\Models\Admin\MatriculationBoard;
use App\Models\Admin\NotificationLog;
use App\Models\Admin\PaymentGateway;
use App\Models\Admin\Qualification;
use App\Models\Admin\Religion;
use App\Models\Admin\SerialCounter;
use App\Models\Admin\State;
use App\Models\Admin\Student;
use App\Models\Admin\StudentDue;
use App\Models\Admin\StudentPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data for the role-based dashboards (resources/views/livewire/admin/dashboard).
 * Phase 1: Module 1 (institutes, users, roles, master data, audit, notifications, serial numbers);
 * Module 2 figures (students, fees, ER & ID cards) where they are the role's daily work.
 * Exams, results, DMS and MoU figures are added as those modules are built.
 */
class DashboardService
{
    /** Roles that get their own dashboard, in priority order (a user with several roles gets the first). */
    const DASHBOARDS = ['super-admin', 'institute-admin', 'accounts', 'training-manager'];

    public function dashboardFor(User $user): string
    {
        foreach (self::DASHBOARDS as $role) {
            if ($user->hasRole($role)) {
                return $role;
            }
        }

        return 'staff';
    }

    // ================================================================== shared pieces

    /** Staff users only (student portal accounts are managed under Students). */
    protected function staffUsers()
    {
        return User::query()->whereDoesntHave('roles', fn ($r) => $r->where('name', 'student'));
    }

    /** Login activity buckets of a user query: online (15 min) · today · week · older · never. */
    protected function loginActivity($query): array
    {
        $count = fn ($callback) => $callback((clone $query))->count();

        $total = (clone $query)->count();
        $week = $count(fn ($q) => $q->where('last_login_at', '>=', now()->subDays(7)));
        $never = $count(fn ($q) => $q->whereNull('last_login_at'));

        return [
            'total' => $total,
            'active' => $count(fn ($q) => $q->where('status', true)),
            'online' => $count(fn ($q) => $q->where('last_login_at', '>=', now()->subMinutes(15))),
            'today' => $count(fn ($q) => $q->where('last_login_at', '>=', today())),
            'week' => $week,
            'older' => max(0, $total - $week - $never),
            'never' => $never,
        ];
    }

    /** Users per role (label => count) for a user query. */
    protected function usersByRole($query): Collection
    {
        $counts = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->whereIn('model_has_roles.model_id', (clone $query)->select('users.id'))
            ->groupBy('roles.name')->selectRaw('roles.name as role, COUNT(*) as total')->pluck('total', 'role');

        return collect(config('camp.roles'))->except('student')
            ->map(fn ($label, $slug) => ['slug' => $slug, 'label' => $label, 'count' => (int) ($counts[$slug] ?? 0)])
            ->values();
    }

    /** Audit entries per month for the last N months: [['label' => 'Oct', 'count' => 12], …]. */
    protected function monthlyActivity(?int $instituteId = null, int $months = 12): array
    {
        $from = now()->startOfMonth()->subMonths($months - 1);
        $rows = AuditTrail::query()
            ->when($instituteId, fn ($q) => $q->where('institute_id', $instituteId))
            ->where('created_at', '>=', $from)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as total")
            ->groupBy('ym')->pluck('total', 'ym');

        return collect(range(0, $months - 1))->map(function ($i) use ($from, $rows) {
            $month = $from->copy()->addMonths($i);

            return ['label' => $month->format('M'), 'count' => (int) ($rows[$month->format('Y-m')] ?? 0)];
        })->all();
    }

    protected function recentActivity(?int $instituteId = null, int $limit = 6): Collection
    {
        return AuditTrail::with(['user', 'institute'])
            ->when($instituteId, fn ($q) => $q->where('institute_id', $instituteId))
            ->latest('id')->limit($limit)->get();
    }

    /** In-app notifications of the user (bell). */
    protected function myNotifications(User $user, int $limit = 5): array
    {
        return [
            'unread' => $user->unreadNotifications()->count(),
            'latest' => $user->notifications()->latest()->limit($limit)->get(),
        ];
    }

    /** Students of one institute (or all) by status. */
    protected function studentsByStatus(?int $instituteId = null): Collection
    {
        $counts = Student::withoutGlobalScopes()->whereNull('deleted_at')
            ->when($instituteId, fn ($q) => $q->where('institute_id', $instituteId))
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(config('camp.student_statuses'))
            ->map(fn ($meta, $status) => ['status' => $status, 'label' => $meta[0], 'colour' => $meta[1], 'count' => (int) ($counts[$status] ?? 0)]);
    }

    /** Admission queue figures (Module 2) for one institute or all. */
    protected function admissions(?int $instituteId = null): array
    {
        $students = fn () => Student::withoutGlobalScopes()->whereNull('deleted_at')->when($instituteId, fn ($q) => $q->where('institute_id', $instituteId));
        $gate = fn ($gate) => EnrollmentApproval::withoutGlobalScopes()->where('gate', $gate)->where('status', 'pending')
            ->when($instituteId, fn ($q) => $q->where('institute_id', $instituteId));

        return [
            'students' => $students()->count(),
            'this_month' => $students()->where('created_at', '>=', now()->startOfMonth())->count(),
            'docs_pending' => $gate(EnrollmentApproval::DOCUMENTS)->whereHas('student', fn ($s) => $s->where('status', 'pending_approval'))->count(),
            'fees_pending' => $gate(EnrollmentApproval::FEES)->count(),
            'payments_to_verify' => StudentPayment::withoutGlobalScopes()->where('status', 'pending_verification')->when($instituteId, fn ($q) => $q->where('institute_id', $instituteId))->count(),
            'er_this_month' => $students()->whereNotNull('er_number')->whereHas('erRequest', fn ($e) => $e->where('generated_at', '>=', now()->startOfMonth()))->count(),
            'past_deadline' => $students()->whereNull('er_number')->where('status', '!=', 'rejected')->whereDate('onboarding_deadline', '<', today())->count(),
        ];
    }

    // ================================================================== approvals waiting for the user

    /**
     * Every pending approval the user can act on (by permission), with counts for their institute
     * (Super Admin: all institutes) and a link to the matching queue/filter:
     *  Gate 1 — documents to verify, applications ready to approve (onboarding.verify_documents)
     *  Gate 2 — payments to verify, fee gates ready to approve (payments.verify)
     *  ER form — to print / sign / archive; ID card — to print / sign / reprints (enrollment.manage)
     * Each: key, label, hint, count, icon, tone, url, group.
     */
    public function pendingActions(User $user): array
    {
        $id = $user->isSuperAdmin() ? null : (int) $user->institute_id;
        $scope = fn ($q) => $q->when($id, fn ($w) => $w->where('institute_id', $id));
        $onboarding = app(OnboardingService::class);
        $items = [];

        if ($user->can('onboarding.verify_documents')) {
            $docs = \App\Models\Admin\StudentDocument::withoutGlobalScopes()->whereNull('deleted_at')->tap($scope)
                ->where('verification_status', 'pending')
                ->whereHas('student', fn ($s) => $s->whereIn('status', ['pending_docs', 'pending_approval']));
            $gate1 = Student::withoutGlobalScopes()->whereNull('deleted_at')->tap($scope)->where('status', 'pending_approval')
                ->whereHas('approvals', fn ($a) => $a->where('gate', EnrollmentApproval::DOCUMENTS)->where('status', 'pending'))
                ->with('approvals', 'documents')->get()
                ->filter(fn ($s) => $onboarding->gateBlocker($s, EnrollmentApproval::DOCUMENTS) === null);
            $items[] = ['key' => 'docs', 'group' => 'Gate 1 · Documents', 'label' => 'Documents to verify', 'hint' => (clone $docs)->distinct('student_id')->count('student_id') . ' applications',
                'count' => (clone $docs)->count(), 'icon' => 'ti ti-file-search', 'tone' => 'warning', 'url' => route('admin.onboarding.documents', ['tab' => 'pending'])];
            $items[] = ['key' => 'gate1', 'group' => 'Gate 1 · Documents', 'label' => 'Gate 1 ready to approve', 'hint' => 'all documents verified',
                'count' => $gate1->count(), 'icon' => 'ti ti-file-check', 'tone' => 'primary', 'url' => route('admin.onboarding.documents', ['tab' => 'pending'])];
        }

        if ($user->can('payments.verify')) {
            $payments = StudentPayment::withoutGlobalScopes()->whereNull('deleted_at')->tap($scope)->where('status', 'pending_verification');
            $gate2 = Student::withoutGlobalScopes()->whereNull('deleted_at')->tap($scope)
                ->whereHas('approvals', fn ($a) => $a->where('gate', EnrollmentApproval::FEES)->where('status', 'pending'))
                ->whereHas('approvals', fn ($a) => $a->where('gate', EnrollmentApproval::DOCUMENTS)->where('status', 'approved'))
                ->with('approvals')->get()
                ->filter(fn ($s) => $onboarding->gateBlocker($s, EnrollmentApproval::FEES) === null);
            $items[] = ['key' => 'payments', 'group' => 'Gate 2 · Fees', 'label' => 'Payments to verify', 'hint' => money_inr((clone $payments)->sum('amount'), false),
                'count' => (clone $payments)->count(), 'icon' => 'ti ti-cash', 'tone' => 'warning', 'url' => route('admin.onboarding.payments', ['tab' => 'pending'])];
            $items[] = ['key' => 'gate2', 'group' => 'Gate 2 · Fees', 'label' => 'Fee gate ready to approve', 'hint' => 'all dues cleared',
                'count' => $gate2->count(), 'icon' => 'ti ti-shield-check', 'tone' => 'primary', 'url' => route('admin.onboarding.payments', ['tab' => 'gate'])];
        }

        if ($user->can('enrollment.manage')) {
            $forms = fn () => ErRequest::withoutGlobalScopes()->whereNull('deleted_at')->tap($scope);
            $cards = fn () => IdCard::withoutGlobalScopes()->whereNull('deleted_at')->tap($scope)->where('tm_signature_status', 'pending');
            $queue = fn ($params) => route('admin.onboarding.enrollment', $params);
            $items[] = ['key' => 'form_print', 'group' => 'ER form', 'label' => 'ER forms to print', 'hint' => 'new ER numbers',
                'count' => $forms()->where('status', 'generated')->count(), 'icon' => 'ti ti-printer', 'tone' => 'info', 'url' => $queue(['formStage' => 'generated'])];
            $items[] = ['key' => 'form_sign', 'group' => 'ER form', 'label' => 'ER forms to sign', 'hint' => 'TM signature',
                'count' => $forms()->where('status', 'printed')->count(), 'icon' => 'ti ti-signature', 'tone' => 'warning', 'url' => $queue(['formStage' => 'printed'])];
            $items[] = ['key' => 'form_archive', 'group' => 'ER form', 'label' => 'ER forms to archive', 'hint' => 'signed',
                'count' => $forms()->where('status', 'signed')->count(), 'icon' => 'ti ti-archive', 'tone' => 'secondary', 'url' => $queue(['formStage' => 'signed'])];
            $items[] = ['key' => 'card_print', 'group' => 'ID card', 'label' => 'ID cards to print', 'hint' => 'not printed yet',
                'count' => $cards()->where('status', '!=', 'reprinted')->where('print_count', 0)->count(), 'icon' => 'ti ti-id', 'tone' => 'info', 'url' => $queue(['cardStage' => 'to_print'])];
            $items[] = ['key' => 'card_sign', 'group' => 'ID card', 'label' => 'ID cards to sign & issue', 'hint' => 'printed',
                'count' => $cards()->where('status', '!=', 'reprinted')->where('print_count', '>', 0)->count(), 'icon' => 'ti ti-id-badge-2', 'tone' => 'danger', 'url' => $queue(['cardStage' => 'to_sign'])];
            $reprints = $cards()->where('status', 'reprinted')->count();
            if ($reprints) {
                $items[] = ['key' => 'card_reprint', 'group' => 'ID card', 'label' => 'Reprinted cards to sign', 'hint' => 'lost / damaged',
                    'count' => $reprints, 'icon' => 'ti ti-copy', 'tone' => 'danger', 'url' => $queue(['cardStage' => 'reprinted'])];
            }
        }

        return $items;
    }

    // ================================================================== Super Admin

    public function superAdmin(User $user): array
    {
        $staff = $this->staffUsers();
        $logins = $this->loginActivity($staff);
        $currentYear = AcademicYear::current();

        $institutes = Institute::withCount([
            'users as staff_count' => fn ($q) => $q->whereDoesntHave('roles', fn ($r) => $r->where('name', 'student')),
            'students as students_count',
            'instituteCourses as courses_count',
        ])->orderBy('name')->get();

        $masters = collect([
            ['Academic Years', AcademicYear::class, 'admin.masters.academic-years', 'ti ti-calendar-stats', 'primary'],
            ['Courses', Course::class, 'admin.masters.courses', 'ti ti-books', 'secondary'],
            ['Qualifications', Qualification::class, 'admin.masters.qualifications', 'ti ti-certificate-2', 'info'],
            ['Matriculation Boards', MatriculationBoard::class, 'admin.masters.matriculation-boards', 'ti ti-building-bank', 'purple'],
            ['Higher Secondary Boards', HigherSecondaryBoard::class, 'admin.masters.higher-secondary-boards', 'ti ti-building-bank', 'pink'],
            ['Religions', Religion::class, 'admin.masters.religions', 'ti ti-users', 'success'],
            ['Categories', Category::class, 'admin.masters.categories', 'ti ti-category', 'warning'],
            ['Countries', Country::class, 'admin.masters.countries', 'ti ti-world', 'dark'],
            ['States', State::class, 'admin.masters.states', 'ti ti-map-pin', 'danger'],
            ['Payment Gateways', PaymentGateway::class, 'admin.masters.payment-gateways', 'ti ti-credit-card', 'primary'],
        ])->map(fn ($m) => [
            'label' => $m[0], 'route' => $m[2], 'icon' => $m[3], 'tone' => $m[4],
            'total' => $m[1]::count(), 'active' => $m[1]::where('status', true)->count(),
        ]);

        $notifications = NotificationLog::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $mostActive = AuditTrail::where('created_at', '>=', now()->startOfMonth())->whereNotNull('institute_id')
            ->selectRaw('institute_id, COUNT(*) as total')->groupBy('institute_id')->orderByDesc('total')->first();

        return [
            'user' => $user,
            'institutes' => $institutes,
            'institutesActive' => $institutes->where('status', true)->count(),
            'logins' => $logins,
            'byRole' => $this->usersByRole($staff),
            'recentUsers' => (clone $staff)->with(['roles', 'institute'])->latest('id')->limit(5)->get(),
            'recentLogins' => (clone $staff)->with(['roles', 'institute'])->whereNotNull('last_login_at')->orderByDesc('last_login_at')->limit(4)->get(),
            'neverLoggedIn' => (clone $staff)->with(['roles', 'institute'])->whereNull('last_login_at')->latest('id')->limit(3)->get(),
            'masters' => $masters,
            'masterTotal' => $masters->sum('total'),
            'courses' => ['total' => Course::count(), 'active' => Course::where('status', true)->count(), 'offerings' => InstituteCourse::count()],
            'academicYears' => AcademicYear::orderBy('start_date')->get(),
            'currentYear' => $currentYear,
            'activity' => $this->monthlyActivity(),
            'activityToday' => AuditTrail::where('created_at', '>=', today())->count(),
            'activityMonth' => AuditTrail::where('created_at', '>=', now()->startOfMonth())->count(),
            'recent' => $this->recentActivity(),
            'mostActive' => $mostActive ? ['institute' => Institute::find($mostActive->institute_id), 'count' => (int) $mostActive->total] : null,
            'notifications' => [
                'sent' => (int) ($notifications['sent'] ?? 0), 'pending' => (int) ($notifications['pending'] ?? 0), 'failed' => (int) ($notifications['failed'] ?? 0),
                'email' => NotificationLog::where('channel', 'email')->count(), 'sms' => NotificationLog::where('channel', 'sms')->count(),
            ],
            'serials' => SerialCounter::with('institute')->where('year', now()->year)->orderBy('series_key')->get(),
            'admissions' => $this->admissions(),
            'checklist' => $this->superAdminChecklist($institutes, $currentYear),
        ];
    }

    /** System set-up checks: [done, text, hint, route]. */
    protected function superAdminChecklist(Collection $institutes, ?AcademicYear $currentYear): array
    {
        $active = $institutes->where('status', true);
        $withoutAdmin = $active->filter(fn ($i) => !User::where('institute_id', $i->id)->whereHas('roles', fn ($r) => $r->where('name', 'institute-admin'))->exists());
        $withoutCourses = $active->where('courses_count', 0);
        $offeringsWithoutFees = InstituteCourse::query()->whereNotExists(function ($q) {
            $q->select(DB::raw(1))->from('course_fees')->whereColumn('course_fees.institute_id', 'institute_courses.institute_id')
                ->whereColumn('course_fees.course_id', 'institute_courses.course_id')->whereNull('course_fees.deleted_at');
        })->count();
        $withoutPayments = $active->filter(fn ($i) => !InstitutePaymentGateway::enabled()->where('institute_id', $i->id)->exists());

        return [
            [$active->isNotEmpty(), $active->count() . ' active ' . ($active->count() === 1 ? 'institute' : 'institutes'), 'Add institutes under Institute Management', 'admin.institutes'],
            [$withoutAdmin->isEmpty(), $withoutAdmin->isEmpty() ? 'Every institute has an Institute Admin' : $withoutAdmin->count() . ' without an Institute Admin', $withoutAdmin->pluck('name')->take(3)->implode(', '), 'admin.users'],
            [$withoutCourses->isEmpty(), $withoutCourses->isEmpty() ? 'Every institute offers courses' : $withoutCourses->count() . ' without courses', $withoutCourses->pluck('name')->take(3)->implode(', '), 'admin.institute-courses'],
            [$offeringsWithoutFees === 0, $offeringsWithoutFees === 0 ? 'Every course has a fee structure' : $offeringsWithoutFees . ' course offerings without fees', 'Set them up under Fee Structure', 'admin.onboarding.fee-structure'],
            [$withoutPayments->isEmpty(), $withoutPayments->isEmpty() ? 'Payment methods set for every institute' : $withoutPayments->count() . ' without payment methods', $withoutPayments->pluck('name')->take(3)->implode(', '), 'admin.institute-payment-settings'],
            [(bool) $currentYear, $currentYear ? 'Academic year ' . $currentYear->name . ' is set up' : 'No academic year covers today', 'Master Data → Academic Years', 'admin.masters.academic-years'],
            [false, 'Email sending is switched off', 'Pending the SMTP2GO mail service', 'admin.notifications'],
        ];
    }

    // ================================================================== Institute Admin

    public function instituteAdmin(User $user): array
    {
        $institute = $user->institute;
        $id = (int) $user->institute_id;
        $staff = $this->staffUsers()->where('institute_id', $id);
        $students = $this->studentsByStatus($id);

        $offerings = InstituteCourse::with('course')->where('institute_id', $id)->get();
        $courseIds = $offerings->pluck('course_id');
        $feeCourses = CourseFee::withoutGlobalScopes()->whereNull('deleted_at')->where('institute_id', $id)->distinct()->pluck('course_id');
        $currentPeriods = CoursePeriod::withoutGlobalScopes()->whereNull('deleted_at')->where('institute_id', $id)
            ->whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today())->count();
        $roleCount = $this->usersByRole($staff)->keyBy('slug');

        return [
            'user' => $user,
            'institute' => $institute,
            'logins' => $this->loginActivity($staff),
            'byRole' => $this->usersByRole($staff)->reject(fn ($r) => $r['slug'] === 'super-admin')->values(),
            'staffList' => (clone $staff)->with('roles')->orderByDesc('last_login_at')->limit(5)->get(),
            'students' => $students,
            'studentTotal' => $students->sum('count'),
            'admissions' => $this->admissions($id),
            'offerings' => $offerings,
            'batchesOpen' => Batch::withoutGlobalScopes()->whereNull('deleted_at')->where('institute_id', $id)->where('status', true)->count(),
            'batchesTotal' => Batch::withoutGlobalScopes()->whereNull('deleted_at')->where('institute_id', $id)->count(),
            'periodsRunning' => $currentPeriods,
            'fees' => $this->feeFigures($id),
            'activity' => $this->monthlyActivity($id, 6),
            'recent' => $this->recentActivity($id),
            'checklist' => [
                [$offerings->isNotEmpty(), $offerings->count() . ' ' . ($offerings->count() === 1 ? 'course' : 'courses') . ' offered', 'Ask the Super Admin to assign courses', 'admin.institute-courses'],
                [$courseIds->diff($feeCourses)->isEmpty() && $offerings->isNotEmpty(), $courseIds->diff($feeCourses)->isEmpty() ? 'Fee structure set for every course' : $courseIds->diff($feeCourses)->count() . ' courses without fees', 'Institute Management → Fee Structure', 'admin.onboarding.fee-structure'],
                [InstitutePaymentGateway::enabled()->where('institute_id', $id)->exists(), 'Payment methods for students', 'Institute Management → Payment Settings', 'admin.institute-payment-settings'],
                [Batch::withoutGlobalScopes()->whereNull('deleted_at')->where('institute_id', $id)->where('status', true)->exists(), 'Open batches for admission', 'Institute Management → Batches', 'admin.batches'],
                [CoursePeriod::withoutGlobalScopes()->whereNull('deleted_at')->where('institute_id', $id)->exists(), 'Academic periods (semesters) generated', 'Institute Management → Academic Periods', 'admin.academic-periods'],
                [($roleCount['accounts']['count'] ?? 0) > 0, 'Accounts user for fee verification', 'Users → Add user (Accounts)', 'admin.users'],
                [($roleCount['training-manager']['count'] ?? 0) > 0, 'Training Manager to sign ER forms & ID cards', 'Users → Add user (Training Manager)', 'admin.users'],
            ],
        ];
    }

    /** Fee figures (Module 2) of one institute or all. */
    protected function feeFigures(?int $instituteId = null): array
    {
        $dues = fn () => StudentDue::withoutGlobalScopes()->whereNull('deleted_at')->when($instituteId, fn ($q) => $q->where('institute_id', $instituteId));
        $payments = fn () => StudentPayment::withoutGlobalScopes()->whereNull('deleted_at')->when($instituteId, fn ($q) => $q->where('institute_id', $instituteId));
        $open = fn () => $dues()->whereIn('status', ['pending', 'partial']);

        return [
            'outstanding' => (float) $open()->sum(DB::raw('amount_due - amount_paid')),
            'overdue' => (float) $open()->whereDate('due_date', '<', today())->sum(DB::raw('amount_due - amount_paid')),
            'overdue_count' => $open()->whereDate('due_date', '<', today())->count(),
            'collected_today' => (float) $payments()->where('status', 'success')->where('verified_at', '>=', today())->sum('amount'),
            'collected_month' => (float) $payments()->where('status', 'success')->where('verified_at', '>=', now()->startOfMonth())->sum('amount'),
            'to_verify' => $payments()->where('status', 'pending_verification')->count(),
            'to_verify_amount' => (float) $payments()->where('status', 'pending_verification')->sum('amount'),
            'receipts_month' => $payments()->where('status', 'success')->where('verified_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    // ================================================================== Accounts

    public function accounts(User $user): array
    {
        $id = (int) $user->institute_id;
        $from = now()->startOfMonth()->subMonths(5);
        $collected = StudentPayment::withoutGlobalScopes()->whereNull('deleted_at')->where('institute_id', $id)->where('status', 'success')
            ->where('verified_at', '>=', $from)->selectRaw("DATE_FORMAT(verified_at, '%Y-%m') as ym, SUM(amount) as total")->groupBy('ym')->pluck('total', 'ym');

        return [
            'user' => $user,
            'institute' => $user->institute,
            'fees' => $this->feeFigures($id),
            'admissions' => $this->admissions($id),
            'collection' => collect(range(0, 5))->map(fn ($i) => ['label' => $from->copy()->addMonths($i)->format('M'), 'amount' => (float) ($collected[$from->copy()->addMonths($i)->format('Y-m')] ?? 0)])->all(),
            'toVerify' => StudentPayment::withoutGlobalScopes()->whereNull('deleted_at')->with(['student', 'gateway', 'due'])->where('institute_id', $id)
                ->where('status', 'pending_verification')->oldest()->limit(5)->get(),
            'recentPayments' => StudentPayment::withoutGlobalScopes()->whereNull('deleted_at')->with(['student', 'gateway', 'due'])->where('institute_id', $id)
                ->where('status', 'success')->latest('verified_at')->limit(5)->get(),
            'overdueDues' => StudentDue::withoutGlobalScopes()->whereNull('deleted_at')->with('student')->where('institute_id', $id)
                ->whereIn('status', ['pending', 'partial'])->whereDate('due_date', '<', today())->orderBy('due_date')->limit(5)->get(),
            'notifications' => $this->myNotifications($user),
        ];
    }

    // ================================================================== Training Manager

    public function trainingManager(User $user): array
    {
        $id = (int) $user->institute_id;
        $forms = fn () => ErRequest::withoutGlobalScopes()->whereNull('deleted_at')->where('institute_id', $id);
        $cards = fn () => IdCard::withoutGlobalScopes()->whereNull('deleted_at')->where('institute_id', $id);

        return [
            'user' => $user,
            'institute' => $user->institute,
            'forms' => [
                'to_print' => $forms()->where('status', 'generated')->count(),
                'to_sign' => $forms()->where('status', 'printed')->count(),
                'to_archive' => $forms()->where('status', 'signed')->count(),
                'archived' => $forms()->where('status', 'archived')->count(),
            ],
            'cards' => [
                'pending' => $cards()->where('tm_signature_status', 'pending')->count(),
                'issued_month' => $cards()->where('tm_signature_status', 'physically_signed')->where('issue_date', '>=', now()->startOfMonth())->count(),
                'signed_by_me' => $cards()->where('signed_by', $user->id)->count(),
            ],
            'awaiting' => Student::withoutGlobalScopes()->whereNull('deleted_at')->with(['course', 'erRequest', 'idCard'])->where('institute_id', $id)
                ->where('status', 'er_issued')->orderBy('id')->limit(6)->get(),
            'students' => $this->studentsByStatus($id),
            'notifications' => $this->myNotifications($user),
            'recent' => AuditTrail::with('user')->where('institute_id', $id)->whereIn('module', ['er_requests', 'id_cards'])->latest('id')->limit(5)->get(),
        ];
    }

    // ================================================================== Other staff (BiC, EM, HoT, Faculty)

    /** What each role will see here once its modules are built. */
    const UPCOMING = [
        'bic' => ['Module 3 — Examinations', ['Exam applications blocked by dues', 'Bypass the dues block with a recorded reason']],
        'examination-manager' => ['Modules 3–5 — Exams, Results & Promotion', ['Exam schedule and admit cards to sign', 'Marks entry and result publication', 'Promotion lists to confirm (75 % per subject)']],
        'hot' => ['Modules 6–7 — DMS & MoU', ['MTOE / SOP drafts to review', 'Documents waiting for the 7-day rule', 'MoUs expiring in the next 60 days']],
        'faculty' => ['Modules 3 & 5 — Attendance & Marks', ['Attendance to upload per subject', 'Lesson plans', 'Marks entry for your subjects']],
    ];

    public function staff(User $user): array
    {
        $id = (int) $user->institute_id;
        $role = $user->getRoleNames()->first();

        return [
            'user' => $user,
            'institute' => $user->institute,
            'role' => $role,
            'upcoming' => self::UPCOMING[$role] ?? ['Coming soon', ['The screens for your role appear here as each module goes live.']],
            'students' => $this->studentsByStatus($id ?: null),
            'courses' => $id ? InstituteCourse::with('course')->where('institute_id', $id)->get() : collect(),
            'colleagues' => $id ? $this->usersByRole($this->staffUsers()->where('institute_id', $id))->filter(fn ($r) => $r['count'] > 0)->values() : collect(),
            'notifications' => $this->myNotifications($user),
            'myActivity' => AuditTrail::where('user_id', $user->id)->latest('id')->limit(5)->get(),
        ];
    }
}
