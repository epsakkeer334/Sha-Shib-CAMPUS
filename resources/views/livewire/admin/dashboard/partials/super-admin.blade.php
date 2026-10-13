{{-- Super Admin dashboard: system-wide figures (Module 1 + master data; Module 2 headline numbers). Data: DashboardService::superAdmin() --}}
@php
    $L = $d['logins'];
    $pct = fn ($n, $t) => $t > 0 ? round($n / $t * 100) : 0;
    $pendingApprovals = $d['admissions']['docs_pending'] + $d['admissions']['payments_to_verify'];
    $roleTone = ['super-admin' => 'danger', 'institute-admin' => 'primary', 'accounts' => 'success', 'training-manager' => 'info', 'bic' => 'dark', 'examination-manager' => 'purple', 'hot' => 'warning', 'faculty' => 'pink'];
    $initials = fn ($name) => collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $notifyTotal = $d['notifications']['sent'] + $d['notifications']['pending'] + $d['notifications']['failed'];
@endphp
<div class="content">
    @include('livewire.admin.dashboard.partials.shared.head', ['title' => 'Super Admin Dashboard'])

    @include('livewire.admin.dashboard.partials.shared.welcome', [
        'user' => $d['user'],
        'line' => 'You have <a href="' . e(route('admin.onboarding.documents')) . '" class="text-primary text-decoration-underline">' . $d['admissions']['docs_pending'] . '</a> applications waiting for document approval &amp; '
            . '<a href="' . e(route('admin.onboarding.payments')) . '" class="text-primary text-decoration-underline">' . $d['admissions']['payments_to_verify'] . '</a> payments to verify across all institutes.',
        'actions' => [['Add Institute', 'admin.institutes', 'ti ti-building-plus', 'btn-secondary'], ['Add User', 'admin.users', 'ti ti-user-plus', 'btn-primary']],
    ])

    @include('livewire.admin.dashboard.partials.shared.pending', ['pending' => $d['pending']])

    <div class="row">
        {{-- Widget Info --}}
        <div class="col-xxl-8 d-flex">
            <div class="row flex-fill">
                @foreach([
                    ['ti ti-building-community', 'primary', 'Institutes', $d['institutesActive'] . '/' . $d['institutes']->count(), '<span class="text-muted">active</span>', 'View All', 'admin.institutes'],
                    ['ti ti-users', 'secondary', 'Staff Users', $L['active'] . '/' . $L['total'], '<span class="text-success"><i class="ti ti-point-filled"></i>' . $L['online'] . ' online</span>', 'View All', 'admin.users'],
                    ['ti ti-login', 'info', 'Signed In Today', $L['today'], '<span class="text-muted">' . $L['week'] . ' this week</span>', 'View Users', 'admin.users'],
                    ['ti ti-books', 'pink', 'Courses', $d['courses']['active'] . '/' . $d['courses']['total'], '<span class="text-muted">' . $d['courses']['offerings'] . ' offerings</span>', 'View All', 'admin.masters.courses'],
                    ['ti ti-database', 'purple', 'Master Data Records', number_format($d['masterTotal']), '<span class="text-muted">' . $d['masters']->count() . ' lists</span>', 'Manage', 'admin.masters.academic-years'],
                    ['ti ti-school', 'danger', 'Students', number_format($d['admissions']['students']), '<span class="text-success">+' . $d['admissions']['this_month'] . ' this month</span>', 'View All', 'admin.students'],
                    ['ti ti-checklist', 'success', 'Pending Approvals', $pendingApprovals, $d['admissions']['past_deadline'] ? '<span class="text-danger">' . $d['admissions']['past_deadline'] . ' past deadline</span>' : '', 'Review', 'admin.onboarding.documents'],
                    ['ti ti-history', 'dark', 'Actions Today', $d['activityToday'], '<span class="text-muted">' . number_format($d['activityMonth']) . ' this month</span>', 'Audit Trail', 'admin.audit-trail'],
                ] as [$tIcon, $tTone, $tLabel, $tValue, $tExtra, $tLink, $tRoute])
                    @include('livewire.admin.dashboard.partials.shared.tile', ['icon' => $tIcon, 'tone' => $tTone, 'label' => $tLabel, 'value' => $tValue, 'extra' => $tExtra, 'link' => $tLink, 'route' => $tRoute, 'col' => 'col-md-3'])
                @endforeach
            </div>
        </div>

        {{-- Users by Role --}}
        <div class="col-xxl-4 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Users by Role</h5>
                    <a href="{{ route('admin.roles') }}" class="btn btn-white border btn-sm d-inline-flex align-items-center mb-2"><i class="ti ti-shield-lock me-1"></i>Roles</a>
                </div>
                <div class="card-body">
                    <div id="dash-roles"></div>
                    <p class="fs-13 mb-0"><i class="ti ti-circle-filled me-2 fs-8 text-primary"></i>{{ $L['total'] }} staff users across {{ $d['institutes']->count() }} institutes · <span class="text-success fw-bold">{{ $pct($L['active'], $L['total']) }}%</span> active</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- User Status --}}
        <div class="col-xxl-4 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">User Status</h5>
                    <span class="btn btn-white border btn-sm d-inline-flex align-items-center mb-2"><i class="ti ti-users me-1"></i>All institutes</span>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <p class="fs-13 mb-3">Total Staff Users</p>
                        <h3 class="mb-3">{{ $L['total'] }}</h3>
                    </div>
                    <div class="progress-stacked emp-stack mb-3">
                        <div class="progress" role="progressbar" style="width: {{ $pct($L['week'], $L['total']) }}%"><div class="progress-bar bg-success"></div></div>
                        <div class="progress" role="progressbar" style="width: {{ $pct($L['older'], $L['total']) }}%"><div class="progress-bar bg-info"></div></div>
                        <div class="progress" role="progressbar" style="width: {{ $pct($L['never'], $L['total']) }}%"><div class="progress-bar bg-warning"></div></div>
                    </div>
                    <div class="border mb-3">
                        <div class="row gx-0">
                            @foreach([
                                ['Active', $L['active'], 'primary', 'border-end border-bottom', ''],
                                ['Inactive', $L['total'] - $L['active'], 'danger', 'border-bottom text-end', ''],
                                ['Signed in this week', $L['week'], 'success', 'border-end', ''],
                                ['Never signed in', $L['never'], 'warning', 'text-end', ''],
                            ] as [$sLabel, $sCount, $sTone, $sClass])
                                <div class="col-6">
                                    <div class="p-2 flex-fill {{ $sClass }}">
                                        <p class="fs-13 mb-2"><i class="ti ti-square-filled text-{{ $sTone }} fs-12 me-2"></i>{{ $sLabel }} <span class="text-gray-9">({{ $pct($sCount, $L['total']) }}%)</span></p>
                                        <h2 class="display-1">{{ str_pad($sCount, 2, '0', STR_PAD_LEFT) }}</h2>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <h6 class="mb-2">Most Active Institute <span class="fs-12 text-muted fw-normal">(this month)</span></h6>
                    @if($d['mostActive'] && $d['mostActive']['institute'])
                        <div class="p-2 d-flex align-items-center justify-content-between border border-primary bg-primary-100 br-5 mb-4">
                            <div class="d-flex align-items-center overflow-hidden">
                                <span class="me-2"><i class="ti ti-award-filled text-primary fs-24"></i></span>
                                <div class="overflow-hidden">
                                    <h6 class="text-truncate mb-1 fs-14 fw-medium">{{ $d['mostActive']['institute']->name }}</h6>
                                    <p class="fs-13 mb-0">{{ $d['mostActive']['institute']->code }}</p>
                                </div>
                            </div>
                            <div class="text-end">
                                <p class="fs-13 mb-1">Actions</p>
                                <h5 class="text-primary">{{ number_format($d['mostActive']['count']) }}</h5>
                            </div>
                        </div>
                    @else
                        <p class="text-muted fs-13 mb-4">No institute activity recorded this month yet.</p>
                    @endif
                    <a href="{{ route('admin.users') }}" class="btn btn-light btn-md w-100">View All Users</a>
                </div>
            </div>
        </div>

        {{-- Login Activity --}}
        <div class="col-xxl-4 col-xl-6 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Login Activity</h5>
                    <span class="btn btn-white border btn-sm d-inline-flex align-items-center mb-2"><i class="ti ti-calendar me-1"></i>Last 7 days</span>
                </div>
                <div class="card-body">
                    <div class="position-relative mb-4">
                        <div id="dash-logins"></div>
                    </div>
                    <h6 class="mb-3">Status</h6>
                    @foreach([['Online now', $L['online'], 'success'], ['Signed in this week', $L['week'], 'primary'], ['Earlier', $L['older'], 'info'], ['Never signed in', $L['never'], 'warning']] as [$aLabel, $aCount, $aTone])
                        <div class="d-flex align-items-center justify-content-between">
                            <p class="f-13 mb-2"><i class="ti ti-circle-filled text-{{ $aTone }} me-1"></i>{{ $aLabel }}</p>
                            <p class="f-13 fw-medium text-gray-9 mb-2">{{ $aCount }} <span class="text-muted fw-normal">({{ $pct($aCount, $L['total']) }}%)</span></p>
                        </div>
                    @endforeach
                    <div class="bg-light br-5 box-shadow-xs p-2 pb-0 d-flex align-items-center justify-content-between flex-wrap mt-2">
                        <div class="d-flex align-items-center">
                            <p class="mb-2 me-2">Never signed in</p>
                            <div class="avatar-list-stacked avatar-group-sm mb-2">
                                @foreach($d['neverLoggedIn'] as $u)
                                    <span class="avatar avatar-rounded bg-warning text-white fs-10 border border-white" title="{{ $u->name }}">{{ $initials($u->name) }}</span>
                                @endforeach
                                @if($L['never'] > $d['neverLoggedIn']->count())
                                    <span class="avatar bg-primary avatar-rounded text-fixed-white fs-10">+{{ $L['never'] - $d['neverLoggedIn']->count() }}</span>
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('admin.users', ['login' => 'never']) }}" class="fs-13 link-primary text-decoration-underline mb-2">View Details</a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Sign-ins --}}
        <div class="col-xxl-4 col-xl-6 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Recent Sign-ins</h5>
                    <span class="btn btn-white border btn-sm d-inline-flex align-items-center mb-2"><i class="ti ti-clock me-1"></i>Latest</span>
                </div>
                <div class="card-body">
                    @forelse($d['recentLogins'] as $i => $u)
                        @php $role = $u->roles->pluck('name')->first(); $online = $u->last_login_at && $u->last_login_at->gt(now()->subMinutes(15)); @endphp
                        <div class="d-flex align-items-center justify-content-between mb-3 p-2 border {{ $i === 0 ? 'border-dashed' : '' }} br-5">
                            <div class="d-flex align-items-center overflow-hidden">
                                <span class="avatar flex-shrink-0 rounded-circle bg-{{ $roleTone[$role] ?? 'secondary' }}-transparent text-{{ $roleTone[$role] ?? 'secondary' }} fw-semibold">{{ $initials($u->name) }}</span>
                                <div class="ms-2 overflow-hidden">
                                    <h6 class="fs-14 fw-medium text-truncate">{{ $u->name }}</h6>
                                    <p class="fs-13 text-truncate mb-0">{{ $u->role_display_name }} · {{ optional($u->institute)->code ?? 'Central' }}</p>
                                </div>
                            </div>
                            <span class="fs-10 fw-medium d-inline-flex align-items-center badge {{ $online ? 'badge-success' : 'badge-secondary-transparent' }} text-nowrap ms-2" title="{{ $u->last_login_at->format('d M Y, h:i A') }}">
                                <i class="ti ti-circle-filled fs-5 me-1"></i>{{ $online ? 'Online' : $u->last_login_at->diffForHumans(null, true) }}
                            </span>
                        </div>
                    @empty
                        <p class="text-muted text-center py-3">Nobody has signed in yet.</p>
                    @endforelse
                    @if($d['neverLoggedIn']->isNotEmpty())
                        <h6 class="mb-2">Not signed in yet</h6>
                        @foreach($d['neverLoggedIn']->take(2) as $u)
                            <div class="d-flex align-items-center justify-content-between mb-3 p-2 border border-dashed br-5">
                                <div class="d-flex align-items-center overflow-hidden">
                                    <span class="avatar flex-shrink-0 rounded-circle bg-warning-transparent text-warning fw-semibold">{{ $initials($u->name) }}</span>
                                    <div class="ms-2 overflow-hidden">
                                        <h6 class="fs-14 fw-medium text-truncate">{{ $u->name }}</h6>
                                        <p class="fs-13 text-truncate mb-0">{{ $u->role_display_name }} · {{ optional($u->institute)->code ?? 'Central' }}</p>
                                    </div>
                                </div>
                                <span class="fs-10 fw-medium badge badge-danger text-nowrap ms-2">Added {{ $u->created_at->diffForHumans(null, true) }} ago</span>
                            </div>
                        @endforeach
                    @endif
                    <a href="{{ route('admin.users') }}" class="btn btn-light btn-md w-100">View All Users</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Institutes & Courses --}}
        <div class="col-xxl-4 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Institutes &amp; Courses</h5>
                    <a href="{{ route('admin.institutes') }}" class="btn btn-light btn-md mb-2">View All</a>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs tab-style-1 nav-justified d-sm-flex d-block p-0 mb-4" role="tablist">
                        <li class="nav-item" role="presentation"><a class="nav-link fw-medium active" data-bs-toggle="tab" data-bs-target="#dash-inst" href="#dash-inst" role="tab">Institutes</a></li>
                        <li class="nav-item" role="presentation"><a class="nav-link fw-medium" data-bs-toggle="tab" data-bs-target="#dash-courses" href="#dash-courses" role="tab">Courses</a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="dash-inst">
                            @forelse($d['institutes']->sortByDesc('students_count')->take(5) as $inst)
                                <div class="d-flex align-items-center justify-content-between {{ $loop->last ? 'mb-0' : 'mb-4' }}">
                                    <div class="d-flex align-items-center overflow-hidden">
                                        <span class="avatar flex-shrink-0 rounded-circle bg-primary-transparent text-primary fw-semibold">{{ $initials($inst->name) }}</span>
                                        <div class="ms-2 overflow-hidden">
                                            <p class="text-dark fw-medium text-truncate mb-0">{{ $inst->name }}</p>
                                            <span class="fs-12 d-inline-flex align-items-center">{{ $inst->students_count }} students<i class="ti ti-circle-filled fs-4 mx-2 text-primary"></i>{{ $inst->staff_count }} staff</span>
                                        </div>
                                    </div>
                                    <span class="badge {{ $inst->status ? 'badge-success' : 'badge-danger' }} badge-xs">{{ $inst->code }}</span>
                                </div>
                            @empty
                                <p class="text-muted text-center py-3 mb-0">No institutes yet.</p>
                            @endforelse
                        </div>
                        <div class="tab-pane fade" id="dash-courses">
                            @php $offered = \App\Models\Admin\InstituteCourse::selectRaw('course_id, COUNT(*) as total')->groupBy('course_id')->pluck('total', 'course_id'); @endphp
                            @forelse(\App\Models\Admin\Course::orderBy('code')->limit(5)->get() as $course)
                                <div class="d-flex align-items-center justify-content-between {{ $loop->last ? 'mb-0' : 'mb-4' }}">
                                    <div class="d-flex align-items-center overflow-hidden">
                                        <span class="avatar flex-shrink-0 rounded-circle bg-gray-100 text-dark fw-semibold fs-10">{{ \Illuminate\Support\Str::limit($course->code, 5, '') }}</span>
                                        <div class="ms-2 overflow-hidden">
                                            <p class="text-dark fw-medium text-truncate mb-0">{{ $course->name }}</p>
                                            <span class="fs-12">{{ $course->period_summary }}</span>
                                        </div>
                                    </div>
                                    <span class="badge badge-secondary badge-xs text-nowrap">{{ $offered[$course->id] ?? 0 }} institutes</span>
                                </div>
                            @empty
                                <p class="text-muted text-center py-3 mb-0">No courses yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Newest Users --}}
        <div class="col-xxl-4 col-xl-6 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Newest Users</h5>
                    <a href="{{ route('admin.users') }}" class="btn btn-light btn-md mb-2">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-nowrap mb-0">
                            <thead><tr><th>Name</th><th>Role</th></tr></thead>
                            <tbody>
                                @forelse($d['recentUsers'] as $u)
                                    @php $role = $u->roles->pluck('name')->first(); @endphp
                                    <tr>
                                        <td class="{{ $loop->last ? 'border-0' : '' }}">
                                            <div class="d-flex align-items-center">
                                                <span class="avatar rounded-circle bg-{{ $roleTone[$role] ?? 'secondary' }}-transparent text-{{ $roleTone[$role] ?? 'secondary' }} fw-semibold">{{ $initials($u->name) }}</span>
                                                <div class="ms-2">
                                                    <h6 class="fw-medium">{{ \Illuminate\Support\Str::limit($u->name, 22) }}</h6>
                                                    <span class="fs-12">{{ optional($u->institute)->code ?? 'Central' }} · {{ $u->created_at->format('d M Y') }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="{{ $loop->last ? 'border-0' : '' }}"><span class="badge badge-{{ $roleTone[$role] ?? 'secondary' }}-transparent badge-xs">{{ \Illuminate\Support\Str::before($u->role_display_name, ' (') }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="text-center text-muted py-4">No users yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- System set-up --}}
        @include('livewire.admin.dashboard.partials.shared.checklist', ['title' => 'System Set-up', 'items' => $d['checklist'], 'col' => 'col-xxl-4 col-xl-6'])
    </div>

    <div class="row">
        {{-- System Activity --}}
        <div class="col-xl-7 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">System Activity</h5>
                    <span class="btn btn-white border btn-sm d-inline-flex align-items-center mb-2"><i class="ti ti-calendar me-1"></i>Last 12 months</span>
                </div>
                <div class="card-body pb-0">
                    <div class="d-flex align-items-center justify-content-between flex-wrap">
                        <p class="fs-13 text-gray-9 mb-1"><i class="ti ti-square-filled me-2 text-primary"></i>Recorded actions (audit trail)</p>
                        <p class="fs-13 mb-1">Last updated at {{ now()->format('h:i A') }}</p>
                    </div>
                    <div id="dash-activity"></div>
                </div>
            </div>
        </div>

        {{-- Master Data --}}
        <div class="col-xl-5 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Master Data</h5>
                    <span class="badge badge-primary-transparent mb-2">{{ number_format($d['masterTotal']) }} records</span>
                </div>
                <div class="card-body pt-2">
                    <div class="table-responsive">
                        <table class="table table-nowrap mb-0">
                            <tbody>
                                @foreach($d['masters'] as $m)
                                    <tr>
                                        <td class="px-0 {{ $loop->last ? 'border-0' : '' }}">
                                            <div class="d-flex align-items-center">
                                                <span class="avatar avatar-sm rounded-circle bg-{{ $m['tone'] }}-transparent text-{{ $m['tone'] }}"><i class="{{ $m['icon'] }}"></i></span>
                                                <div class="ms-2">
                                                    <h6 class="fw-medium mb-0"><a href="{{ route($m['route']) }}">{{ $m['label'] }}</a></h6>
                                                    <span class="fs-12 text-muted">{{ $m['active'] }} active{{ $m['total'] - $m['active'] ? ' · ' . ($m['total'] - $m['active']) . ' inactive' : '' }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end px-0 {{ $loop->last ? 'border-0' : '' }}">
                                            <h6 class="fw-semibold mb-0">{{ number_format($m['total']) }}</h6>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Institutes table --}}
        <div class="col-xxl-8 col-xl-7 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Institutes</h5>
                    <a href="{{ route('admin.institutes') }}" class="btn btn-light btn-md mb-2">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-nowrap mb-0">
                            <thead><tr><th>Institute</th><th>Code</th><th>Est.</th><th>Courses</th><th>Staff</th><th>Students</th><th>Status</th></tr></thead>
                            <tbody>
                                @forelse($d['institutes']->take(8) as $inst)
                                    <tr>
                                        <td><h6 class="fw-medium mb-0 text-truncate" style="max-width: 240px;">{{ $inst->name }}</h6></td>
                                        <td><span class="badge badge-dark-transparent">{{ $inst->code }}</span></td>
                                        <td>{{ $inst->established_year }}</td>
                                        <td>{{ $inst->courses_count }}</td>
                                        <td>{{ $inst->staff_count }}</td>
                                        <td>
                                            @php $share = $pct($inst->students_count, max(1, $d['admissions']['students'])); @endphp
                                            <div class="d-flex align-items-center gap-2"><span>{{ $inst->students_count }}</span>
                                                <div class="progress progress-xs flex-fill" style="min-width: 60px; height: 5px;"><div class="progress-bar bg-primary" style="width: {{ $share }}%"></div></div></div>
                                        </td>
                                        <td><span class="badge {{ $inst->status ? 'badge-success' : 'badge-danger' }} d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>{{ $inst->status ? 'Active' : 'Inactive' }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center text-muted py-4">No institutes yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Notifications --}}
        <div class="col-xxl-4 col-xl-5 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Email &amp; SMS</h5>
                    <a href="{{ route('admin.notifications') }}" class="btn btn-light btn-md mb-2">View Log</a>
                </div>
                <div class="card-body">
                    <div class="position-relative mb-3"><div id="dash-notify"></div></div>
                    <div class="d-flex align-items-center flex-wrap">
                        @foreach([['Sent', 'sent', 'success'], ['Pending', 'pending', 'warning'], ['Failed', 'failed', 'danger']] as [$nLabel, $nKey, $nTone])
                            <div class="{{ !$loop->last ? 'border-end' : '' }} text-center me-2 pe-2 mb-3">
                                <p class="fs-13 d-inline-flex align-items-center mb-1"><i class="ti ti-circle-filled fs-10 me-1 text-{{ $nTone }}"></i>{{ $nLabel }}</p>
                                <h5>{{ $d['notifications'][$nKey] }}</h5>
                            </div>
                        @endforeach
                    </div>
                    <div class="bg-dark br-5 p-3 pb-0 d-flex align-items-center justify-content-between">
                        <div class="mb-2">
                            <h4 class="text-success">{{ $d['notifications']['email'] }} email · {{ $d['notifications']['sms'] }} SMS</h4>
                            <p class="fs-13 mb-0 text-white-50">Email sending is switched off until SMTP2GO is set up</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Serial Numbers --}}
        <div class="col-xxl-4 col-xl-6 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Serial Numbers · {{ now()->year }}</h5>
                    @if(\Illuminate\Support\Facades\Route::has('admin.serials'))<a href="{{ route('admin.serials') }}" class="btn btn-light btn-md mb-2">View All</a>@endif
                </div>
                <div class="card-body">
                    @forelse($d['serials'] as $s)
                        @php $series = config("camp.serial_series.{$s->series_key}"); @endphp
                        <div class="bg-light p-3 br-5 mb-3">
                            <span class="badge badge-secondary badge-xs mb-1">{{ \App\Support\Readable::label(strtolower($s->series_key)) }}</span>
                            <h6 class="mb-2 text-truncate">{{ $s->institute ? $s->institute->name : 'Group-wide series' }}</h6>
                            <div class="d-flex align-items-center justify-content-between">
                                <p class="fs-13 mb-0"><i class="ti ti-hash me-1"></i>{{ number_format($s->last_value) }} issued</p>
                                <span class="fs-12 text-muted font-monospace">last: {{ str_replace(['{year}', '{institute_code}'], [$s->year, optional($s->institute)->code], $series['format'] ?? '') }}{{ str_pad($s->last_value, $series['pad'] ?? 5, '0', STR_PAD_LEFT) }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted text-center py-4 mb-0"><i class="ti ti-123 d-block fs-24 mb-1"></i>No numbers issued in {{ now()->year }} yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Recent Activities --}}
        @include('livewire.admin.dashboard.partials.shared.activity', ['entries' => $d['recent'], 'col' => 'col-xxl-4 col-xl-6'])

        {{-- Academic Years --}}
        <div class="col-xxl-4 col-xl-12 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Academic Years</h5>
                    <a href="{{ route('admin.masters.academic-years') }}" class="btn btn-light btn-md mb-2">View All</a>
                </div>
                <div class="card-body pb-1">
                    @if($d['currentYear'])
                        @php $cy = $d['currentYear']; $elapsed = $pct(today()->diffInDays($cy->start_date), max(1, $cy->end_date->diffInDays($cy->start_date))); @endphp
                        <div class="bg-dark br-5 p-3 mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div><p class="fs-13 text-white-50 mb-1">Current academic year</p><h4 class="text-white mb-0">{{ $cy->name }}</h4></div>
                                <span class="badge badge-success">{{ $elapsed }}% through</span>
                            </div>
                            <div class="progress" style="height: 6px;"><div class="progress-bar bg-primary" style="width: {{ $elapsed }}%"></div></div>
                            <p class="fs-12 text-white-50 mt-2 mb-0">{{ $cy->start_date->format('d M Y') }} – {{ $cy->end_date->format('d M Y') }}</p>
                        </div>
                    @else
                        <div class="alert alert-warning py-2 fs-13">No academic year covers today. Add one under Master Data.</div>
                    @endif
                    <h6 class="mb-2">Upcoming</h6>
                    @forelse($d['academicYears']->filter(fn ($y) => $y->start_date->isFuture())->take(3) as $y)
                        <div class="d-flex align-items-center justify-content-between bg-light p-2 br-5 mb-2">
                            <div class="d-flex align-items-center"><span class="avatar avatar-sm rounded-circle bg-primary-transparent text-primary me-2"><i class="ti ti-calendar-stats"></i></span>
                                <div><h6 class="fs-14 fw-medium mb-0">{{ $y->name }}</h6><p class="fs-12 mb-0">{{ $y->start_date->format('d M Y') }} – {{ $y->end_date->format('d M Y') }}</p></div></div>
                            <span class="badge {{ $y->status ? 'badge-secondary-transparent' : 'badge-danger-transparent' }} badge-xs">starts in {{ today()->diffInDays($y->start_date) }} days</span>
                        </div>
                    @empty
                        <p class="text-muted fs-13">No upcoming academic years. Add the next one in Master Data.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('load', function () {
            if (typeof ApexCharts === 'undefined') { return; }
            const accent = '#F26522';
            const roles = @json($d['byRole']->filter(fn ($r) => $r['count'] > 0)->values());
            new ApexCharts(document.querySelector('#dash-roles'), {
                chart: { type: 'bar', height: 260, toolbar: { show: false } },
                series: [{ name: 'Users', data: roles.map(r => r.count) }],
                // whole numbers only on the count axis (no 0.2, 0.4 … for small counts)
                xaxis: { categories: roles.map(r => r.label.replace(/ \(.*\)$/, '')), tickAmount: Math.min(5, Math.max(1, ...roles.map(r => r.count))), labels: { formatter: v => Number.isInteger(+v) ? v : Math.round(v) } },
                plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '55%', distributed: true } },
                colors: ['#F26522', '#3B7080', '#03C95A', '#1B84FF', '#AB47BC', '#FFC107', '#E70D0D', '#212529'],
                legend: { show: false }, dataLabels: { enabled: true, style: { fontSize: '11px' } },
                grid: { borderColor: '#E5E7EB', strokeDashArray: 4 }
            }).render();

            new ApexCharts(document.querySelector('#dash-logins'), {
                chart: { type: 'donut', height: 230 },
                series: [{{ $L['online'] }}, {{ max(0, $L['week'] - $L['online']) }}, {{ $L['older'] }}, {{ $L['never'] }}],
                labels: ['Online now', 'This week', 'Earlier', 'Never'],
                colors: ['#03C95A', accent, '#1B84FF', '#FFC107'],
                legend: { show: false }, dataLabels: { enabled: false },
                plotOptions: { pie: { donut: { size: '72%', labels: { show: true, total: { show: true, label: 'Staff users', formatter: () => '{{ $L['total'] }}' } } } } }
            }).render();

            const activity = @json($d['activity']);
            new ApexCharts(document.querySelector('#dash-activity'), {
                chart: { type: 'bar', height: 280, toolbar: { show: false } },
                series: [{ name: 'Actions', data: activity.map(a => a.count) }],
                xaxis: { categories: activity.map(a => a.label) },
                colors: [accent], plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
                dataLabels: { enabled: false }, grid: { borderColor: '#E5E7EB', strokeDashArray: 4 }
            }).render();

            new ApexCharts(document.querySelector('#dash-notify'), {
                chart: { type: 'donut', height: 220 },
                series: [{{ $d['notifications']['sent'] }}, {{ $d['notifications']['pending'] }}, {{ $d['notifications']['failed'] }}],
                labels: ['Sent', 'Pending', 'Failed'], colors: ['#03C95A', '#FFC107', '#E70D0D'],
                legend: { show: false }, dataLabels: { enabled: false },
                plotOptions: { pie: { startAngle: -90, endAngle: 90, offsetY: 10, donut: { size: '72%', labels: { show: true, total: { show: true, label: 'Messages', formatter: () => '{{ $notifyTotal }}' } } } } },
                grid: { padding: { bottom: -70 } }
            }).render();
        });
    </script>
</div>
