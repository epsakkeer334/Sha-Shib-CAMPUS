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

    // ================================================================== pending approvals: who acts next

    /**
     * Pending approval steps of the onboarding workflow. For each: the permission needed to act, the role
     * that normally takes the step (null = anyone with the permission) and the next step in plain words.
     */
    const PENDING = [
        'docs' => ['group' => 'Gate 1 · Documents', 'label' => 'Documents to verify', 'icon' => 'ti ti-file-search', 'tone' => 'warning',
            'permission' => 'onboarding.verify_documents', 'role' => null, 'step' => 'Verify each KYC document', 'route' => ['admin.onboarding.documents', ['tab' => 'pending']]],
        'gate1' => ['group' => 'Gate 1 · Documents', 'label' => 'Gate 1 ready to approve', 'icon' => 'ti ti-file-check', 'tone' => 'primary',
            'permission' => 'onboarding.verify_documents', 'role' => null, 'step' => 'Approve the document gate', 'route' => ['admin.onboarding.documents', ['tab' => 'pending']]],
        'payments' => ['group' => 'Gate 2 · Fees', 'label' => 'Payments to verify', 'icon' => 'ti ti-cash', 'tone' => 'warning',
            'permission' => 'payments.verify', 'role' => null, 'step' => 'Verify the payment and issue the receipt', 'route' => ['admin.onboarding.payments', ['tab' => 'pending']]],
        'gate2' => ['group' => 'Gate 2 · Fees', 'label' => 'Fee gate ready to approve', 'icon' => 'ti ti-shield-check', 'tone' => 'primary',
            'permission' => 'payments.verify', 'role' => null, 'step' => 'Approve the fee gate (issues the ER number)', 'route' => ['admin.onboarding.payments', ['tab' => 'gate']]],
        'form_print' => ['group' => 'ER form', 'label' => 'ER forms to print', 'icon' => 'ti ti-printer', 'tone' => 'info',
            'permission' => 'enrollment.manage', 'role' => null, 'step' => 'Print the ER request form', 'route' => ['admin.onboarding.enrollment', ['formStage' => 'generated']]],
        'form_sign' => ['group' => 'ER form', 'label' => 'ER forms to sign', 'icon' => 'ti ti-signature', 'tone' => 'warning',
            'permission' => 'enrollment.manage', 'role' => 'training-manager', 'step' => 'Training Manager signs the printed form', 'route' => ['admin.onboarding.enrollment', ['formStage' => 'printed']]],
        'form_archive' => ['group' => 'ER form', 'label' => 'ER forms to archive', 'icon' => 'ti ti-archive', 'tone' => 'secondary',
            'permission' => 'enrollment.manage', 'role' => null, 'step' => 'Archive the signed form', 'route' => ['admin.onboarding.enrollment', ['formStage' => 'signed']]],
        'card_print' => ['group' => 'ID card', 'label' => 'ID cards to print', 'icon' => 'ti ti-id', 'tone' => 'info',
            'permission' => 'enrollment.manage', 'role' => null, 'step' => 'Print the ID card', 'route' => ['admin.onboarding.enrollment', ['cardStage' => 'to_print']]],
        'card_sign' => ['group' => 'ID card', 'label' => 'ID cards to sign & issue', 'icon' => 'ti ti-id-badge-2', 'tone' => 'danger',
            'permission' => 'enrollment.manage', 'role' => 'training-manager', 'step' => 'Training Manager signs and issues the card', 'route' => ['admin.onboarding.enrollment', ['cardStage' => 'to_sign']]],
        'card_reprint' => ['group' => 'ID card', 'label' => 'Reprinted cards to sign', 'icon' => 'ti ti-copy', 'tone' => 'danger',
            'permission' => 'enrollment.manage', 'role' => 'training-manager', 'step' => 'Training Manager signs the reprinted card', 'route' => ['admin.onboarding.enrollment', ['cardStage' => 'reprinted']]],
    ];

    /**
     * Counts (and short hints) of the pending steps for one institute (null = all institutes).
     *
     * @return array<string, array{count: int, hint: string}>
     */
    public function pendingCounts(?int $instituteId, array $keys): array
    {
        $scope = fn ($q) => $q->when($instituteId, fn ($w) => $w->where('institute_id', $instituteId));
        $onboarding = app(OnboardingService::class);
        $out = [];
        $want = fn ($key) => in_array($key, $keys, true);

        if ($want('docs')) {
            $docs = \App\Models\Admin\StudentDocument::withoutGlobalScopes()->whereNull('deleted_at')->tap($scope)
                ->where('verification_status', 'pending')
                ->whereHas('student', fn ($s) => $s->whereIn('status', ['pending_docs', 'pending_approval']));
            $out['docs'] = ['count' => (clone $docs)->count(), 'hint' => (clone $docs)->distinct('student_id')->count('student_id') . ' applications'];
        }
        if ($want('gate1')) {
            $out['gate1'] = ['count' => Student::withoutGlobalScopes()->whereNull('deleted_at')->tap($scope)->where('status', 'pending_approval')
                ->whereHas('approvals', fn ($a) => $a->where('gate', EnrollmentApproval::DOCUMENTS)->where('status', 'pending'))
                ->with('approvals', 'documents')->get()
                ->filter(fn ($s) => $onboarding->gateBlocker($s, EnrollmentApproval::DOCUMENTS) === null)->count(), 'hint' => 'all documents verified'];
        }
        if ($want('payments')) {
            $payments = StudentPayment::withoutGlobalScopes()->whereNull('deleted_at')->tap($scope)->where('status', 'pending_verification');
            $out['payments'] = ['count' => (clone $payments)->count(), 'hint' => money_inr((clone $payments)->sum('amount'), false)];
        }
        if ($want('gate2')) {
            $out['gate2'] = ['count' => Student::withoutGlobalScopes()->whereNull('deleted_at')->tap($scope)
                ->whereHas('approvals', fn ($a) => $a->where('gate', EnrollmentApproval::FEES)->where('status', 'pending'))
                ->whereHas('approvals', fn ($a) => $a->where('gate', EnrollmentApproval::DOCUMENTS)->where('status', 'approved'))
                ->with('approvals')->get()
                ->filter(fn ($s) => $onboarding->gateBlocker($s, EnrollmentApproval::FEES) === null)->count(), 'hint' => 'all dues cleared'];
        }
        $forms = fn () => ErRequest::withoutGlobalScopes()->whereNull('deleted_at')->tap($scope);
        $cards = fn () => IdCard::withoutGlobalScopes()->whereNull('deleted_at')->tap($scope)->where('tm_signature_status', 'pending');
        foreach (['form_print' => 'generated', 'form_sign' => 'printed', 'form_archive' => 'signed'] as $key => $status) {
            if ($want($key)) {
                $out[$key] = ['count' => $forms()->where('status', $status)->count(), 'hint' => ['form_print' => 'new ER numbers', 'form_sign' => 'TM signature', 'form_archive' => 'signed'][$key]];
            }
        }
        if ($want('card_print')) {
            $out['card_print'] = ['count' => $cards()->where('status', '!=', 'reprinted')->where('print_count', 0)->count(), 'hint' => 'not printed yet'];
        }
        if ($want('card_sign')) {
            $out['card_sign'] = ['count' => $cards()->where('status', '!=', 'reprinted')->where('print_count', '>', 0)->count(), 'hint' => 'printed'];
        }
        if ($want('card_reprint')) {
            $out['card_reprint'] = ['count' => $cards()->where('status', 'reprinted')->count(), 'hint' => 'lost / damaged'];
        }

        return $out;
    }

    /**
     * Who takes a step in an institute: active users holding the permission (and, for signature steps,
     * the Training Managers — or, without a TM, whoever may record the signature).
     *
     * @return array{users: Collection, roles: array, missing: bool}
     */
    public function responsibleFor(string $key, int $instituteId): array
    {
        $def = self::PENDING[$key];
        $holders = User::permission($def['permission'])->with('roles')->where('institute_id', $instituteId)->where('status', true)->orderBy('name')->get();
        $users = $def['role'] ? $holders->filter(fn ($u) => $u->hasRole($def['role']))->values() : $holders;
        if ($def['role'] && $users->isEmpty()) {
            $users = $holders; // no Training Manager account: others with the permission record it
        }
        $roleSlugs = $def['role'] && $users->contains(fn ($u) => $u->hasRole($def['role']))
            ? [$def['role']]
            : $users->flatMap(fn ($u) => $u->getRoleNames())->unique()->values()->all();
        if (!$roleSlugs) {
            // nobody can act: name the roles that normally do
            $roleSlugs = $def['role'] ? [$def['role']] : \Spatie\Permission\Models\Role::permission($def['permission'])->pluck('name')->reject(fn ($r) => $r === 'super-admin')->values()->all();
        }

        return [
            'users' => $users,
            'roles' => collect($roleSlugs)->map(fn ($r) => config("camp.roles.{$r}", $r))->all(),
            'role_slugs' => $roleSlugs,
            'missing' => $users->isEmpty(),
        ];
    }

    /** Link to the queue of a pending step. */
    protected function pendingUrl(string $key): string
    {
        [$route, $params] = self::PENDING[$key]['route'];

        return route($route, $params);
    }

    /**
     * "Waiting for you": the pending steps this user can take (by permission), with counts for their institute
     * (Super Admin: all institutes). Each: key, group, label, hint, count, icon, tone, url, step.
     */
    public function pendingActions(User $user): array
    {
        $keys = collect(self::PENDING)->filter(fn ($def) => $user->can($def['permission']))->keys()->all();
        $counts = $this->pendingCounts($user->isSuperAdmin() ? null : (int) $user->institute_id, $keys);

        return collect($keys)
            ->reject(fn ($key) => $key === 'card_reprint' && !($counts[$key]['count'] ?? 0)) // only when there are reprints
            ->map(fn ($key) => array_merge(
                array_intersect_key(self::PENDING[$key], array_flip(['group', 'label', 'icon', 'tone', 'step'])),
                ['key' => $key, 'count' => $counts[$key]['count'] ?? 0, 'hint' => $counts[$key]['hint'] ?? '', 'url' => $this->pendingUrl($key)]
            ))
            ->values()->all();
    }

    /**
     * Super Admin / Institute Admin: every pending step in their institute(s) with the role whose turn it is
     * and the people responsible — so they can see where work is waiting and with whom.
     * Rows: key, group, label, icon, tone, step, url, count, hint, institute, roles, users, missing, mine.
     */
    public function pendingOverview(User $user): ?array
    {
        if (!$user->isSuperAdmin() && !$user->hasRole('institute-admin')) {
            return null;
        }
        $institutes = $user->isSuperAdmin()
            ? Institute::where('status', true)->orderBy('name')->get(['id', 'name', 'code'])
            : Institute::whereKey($user->institute_id)->get(['id', 'name', 'code']);
        $keys = array_keys(self::PENDING);
        $order = array_flip($keys);
        $rows = [];

        foreach ($institutes as $institute) {
            foreach ($this->pendingCounts($institute->id, $keys) as $key => $c) {
                if (!$c['count']) {
                    continue;
                }
                $who = $this->responsibleFor($key, $institute->id);
                $rows[] = array_merge(
                    array_intersect_key(self::PENDING[$key], array_flip(['group', 'label', 'icon', 'tone', 'step'])),
                    ['key' => $key, 'count' => $c['count'], 'hint' => $c['hint'], 'url' => $this->pendingUrl($key), 'institute' => $institute,
                        'roles' => $who['roles'], 'role_slugs' => $who['role_slugs'], 'users' => $who['users'], 'missing' => $who['missing'],
                        'mine' => $who['users']->contains('id', $user->id)]
                );
            }
        }

        usort($rows, fn ($a, $b) => [$order[$a['key']], $a['institute']->name] <=> [$order[$b['key']], $b['institute']->name]);

        return ['rows' => $rows, 'total' => array_sum(array_column($rows, 'count')), 'institutes' => $institutes->count(),
            'unassigned' => count(array_filter($rows, fn ($r) => $r['missing']))];
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
