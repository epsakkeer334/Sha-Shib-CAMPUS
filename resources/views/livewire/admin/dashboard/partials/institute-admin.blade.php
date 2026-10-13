{{-- Institute Admin dashboard: own institute (Module 1 staff & set-up; Module 2 admissions). Data: DashboardService::instituteAdmin() --}}
@php
    $L = $d['logins']; $A = $d['admissions']; $F = $d['fees'];
    $inst = $d['institute'];
    $pct = fn ($n, $t) => $t > 0 ? round($n / $t * 100) : 0;
    $initials = fn ($name) => collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $roleTone = ['institute-admin' => 'primary', 'accounts' => 'success', 'training-manager' => 'info', 'bic' => 'dark', 'examination-manager' => 'purple', 'hot' => 'warning', 'faculty' => 'pink'];
    $statusHex = ['secondary' => '#6C757D', 'warning' => '#FFC107', 'info' => '#1B84FF', 'primary' => '#F26522', 'success' => '#03C95A', 'dark' => '#212529', 'danger' => '#E70D0D'];
@endphp
<div class="content">
    @include('livewire.admin.dashboard.partials.shared.head', ['title' => 'Institute Dashboard'])

    @include('livewire.admin.dashboard.partials.shared.welcome', [
        'user' => $d['user'],
        'line' => e(optional($inst)->name) . (optional($inst)->code ? ' <span class="badge badge-dark-transparent">' . e($inst->code) . '</span>' : '') . '<br>'
            . 'You have <a href="' . e(route('admin.onboarding.documents')) . '" class="text-primary text-decoration-underline">' . $A['docs_pending'] . '</a> applications to approve'
            . ($A['past_deadline'] ? ' &amp; <a href="' . e(route('admin.students', ['overdue' => 1])) . '" class="text-danger text-decoration-underline">' . $A['past_deadline'] . '</a> students past their onboarding deadline.' : '.'),
        'actions' => [['Add Student', 'admin.students.create', 'ti ti-user-plus', 'btn-secondary'], ['Add Staff User', 'admin.users', 'ti ti-users-plus', 'btn-primary']],
    ])

    @include('livewire.admin.dashboard.partials.shared.pending', ['pending' => $d['pending']])

    <div class="row">
        <div class="col-xxl-8 d-flex">
            <div class="row flex-fill">
                @foreach([
                    ['ti ti-users', 'secondary', 'Staff Users', $L['active'] . '/' . $L['total'], '<span class="text-success"><i class="ti ti-point-filled"></i>' . $L['online'] . ' online</span>', 'Manage', 'admin.users'],
                    ['ti ti-books', 'pink', 'Courses Offered', $d['offerings']->count(), '', 'View All', 'admin.institute-courses'],
                    ['ti ti-users-group', 'info', 'Open Batches', $d['batchesOpen'] . '/' . $d['batchesTotal'], '', 'View All', 'admin.batches'],
                    ['ti ti-calendar-time', 'purple', 'Running Semesters', $d['periodsRunning'], '', 'Academic Periods', 'admin.academic-periods'],
                    ['ti ti-school', 'primary', 'Students', number_format($d['studentTotal']), '<span class="text-success">+' . $A['this_month'] . ' this month</span>', 'View All', 'admin.students'],
                    ['ti ti-file-check', 'warning', 'Documents to Approve', $A['docs_pending'], '', 'Review', 'admin.onboarding.documents'],
                    ['ti ti-id-badge-2', 'success', 'ER Issued', $A['er_this_month'], '<span class="text-muted">this month</span>', 'ER & ID Cards', 'admin.onboarding.enrollment'],
                    ['ti ti-currency-rupee', 'danger', 'Fees Outstanding', money_inr($F['outstanding'], false), $F['overdue'] > 0 ? '<span class="text-danger">' . money_inr($F['overdue'], false) . ' overdue</span>' : '', 'Students', 'admin.students'],
                ] as [$tIcon, $tTone, $tLabel, $tValue, $tExtra, $tLink, $tRoute])
                    @include('livewire.admin.dashboard.partials.shared.tile', ['icon' => $tIcon, 'tone' => $tTone, 'label' => $tLabel, 'value' => $tValue, 'extra' => $tExtra, 'link' => $tLink, 'route' => $tRoute, 'col' => 'col-md-3'])
                @endforeach
            </div>
        </div>

        {{-- Students by status --}}
        <div class="col-xxl-4 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Students by Status</h5>
                    <a href="{{ route('admin.students') }}" class="btn btn-light btn-md mb-2">View All</a>
                </div>
                <div class="card-body">
                    <div id="dash-students"></div>
                    <div class="row g-2 mt-1">
                        @foreach($d['students']->filter(fn ($s) => $s['count'] > 0) as $s)
                            <div class="col-6"><p class="fs-13 mb-1 d-flex justify-content-between"><span><i class="ti ti-circle-filled me-1 text-{{ $s['colour'] }}"></i>{{ $s['label'] }}</span><span class="fw-medium text-dark">{{ $s['count'] }}</span></p></div>
                        @endforeach
                        @if($d['studentTotal'] === 0)<p class="text-muted text-center fs-13 mb-0">No students yet.</p>@endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Staff status --}}
        <div class="col-xxl-4 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Staff by Role</h5>
                    <a href="{{ route('admin.users') }}" class="btn btn-light btn-md mb-2">View All</a>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <p class="fs-13 mb-3">Total Staff</p>
                        <h3 class="mb-3">{{ $L['total'] }}</h3>
                    </div>
                    <div class="progress-stacked emp-stack mb-3">
                        <div class="progress" style="width: {{ $pct($L['week'], $L['total']) }}%"><div class="progress-bar bg-success"></div></div>
                        <div class="progress" style="width: {{ $pct($L['older'], $L['total']) }}%"><div class="progress-bar bg-info"></div></div>
                        <div class="progress" style="width: {{ $pct($L['never'], $L['total']) }}%"><div class="progress-bar bg-warning"></div></div>
                    </div>
                    @foreach($d['byRole'] as $r)
                        <div class="d-flex align-items-center justify-content-between mb-2 p-2 border br-5 {{ $r['count'] ? '' : 'border-dashed' }}">
                            <p class="fs-13 mb-0"><i class="ti ti-square-filled text-{{ $roleTone[$r['slug']] ?? 'secondary' }} fs-12 me-2"></i>{{ $r['label'] }}</p>
                            @if($r['count'])<h6 class="mb-0">{{ $r['count'] }}</h6>@else<span class="badge badge-warning-transparent badge-xs">none yet</span>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Staff sign-ins --}}
        <div class="col-xxl-4 col-xl-6 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Staff Sign-ins</h5>
                    <span class="btn btn-white border btn-sm d-inline-flex align-items-center mb-2"><i class="ti ti-login me-1"></i>{{ $L['today'] }} today</span>
                </div>
                <div class="card-body">
                    @forelse($d['staffList'] as $u)
                        @php $role = $u->roles->pluck('name')->first(); $online = $u->last_login_at && $u->last_login_at->gt(now()->subMinutes(15)); @endphp
                        <div class="d-flex align-items-center justify-content-between mb-3 p-2 border {{ $u->last_login_at ? '' : 'border-dashed' }} br-5">
                            <div class="d-flex align-items-center overflow-hidden">
                                <span class="avatar flex-shrink-0 rounded-circle bg-{{ $roleTone[$role] ?? 'secondary' }}-transparent text-{{ $roleTone[$role] ?? 'secondary' }} fw-semibold">{{ $initials($u->name) }}</span>
                                <div class="ms-2 overflow-hidden">
                                    <h6 class="fs-14 fw-medium text-truncate">{{ $u->name }} @unless($u->status)<span class="badge badge-danger badge-xs">Inactive</span>@endunless</h6>
                                    <p class="fs-13 text-truncate mb-0">{{ $u->role_display_name }}</p>
                                </div>
                            </div>
                            @if($u->last_login_at)
                                <span class="fs-10 fw-medium badge {{ $online ? 'badge-success' : 'badge-secondary-transparent' }} text-nowrap ms-2"><i class="ti ti-circle-filled fs-5 me-1"></i>{{ $online ? 'Online' : $u->last_login_at->diffForHumans(null, true) }}</span>
                            @else
                                <span class="fs-10 fw-medium badge badge-warning text-nowrap ms-2">Never</span>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted text-center py-4">No staff users yet. Add your Accounts and Training Manager users.</p>
                    @endforelse
                    <a href="{{ route('admin.users') }}" class="btn btn-light btn-md w-100">View All Staff</a>
                </div>
            </div>
        </div>

        @include('livewire.admin.dashboard.partials.shared.checklist', ['title' => 'Institute Set-up', 'items' => $d['checklist'], 'col' => 'col-xxl-4 col-xl-6'])
    </div>

    <div class="row">
        {{-- Activity --}}
        <div class="col-xxl-4 col-xl-6 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Institute Activity</h5>
                    <span class="btn btn-white border btn-sm d-inline-flex align-items-center mb-2"><i class="ti ti-calendar me-1"></i>Last 6 months</span>
                </div>
                <div class="card-body pb-0"><div id="dash-activity"></div></div>
            </div>
        </div>

        {{-- Courses offered --}}
        <div class="col-xxl-4 col-xl-6 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Courses Offered</h5>
                    <a href="{{ route('admin.institute-courses') }}" class="btn btn-light btn-md mb-2">View All</a>
                </div>
                <div class="card-body">
                    @forelse($d['offerings']->take(6) as $o)
                        <div class="d-flex align-items-center justify-content-between {{ $loop->last ? 'mb-0' : 'mb-3' }}">
                            <div class="d-flex align-items-center overflow-hidden">
                                <span class="avatar flex-shrink-0 rounded-circle bg-gray-100 text-dark fw-semibold fs-10">{{ \Illuminate\Support\Str::limit(optional($o->course)->code, 5, '') }}</span>
                                <div class="ms-2 overflow-hidden">
                                    <p class="text-dark fw-medium text-truncate mb-0">{{ optional($o->course)->name }}</p>
                                    <span class="fs-12">{{ optional($o->course)->period_summary }}</span>
                                </div>
                            </div>
                            <span class="badge {{ $o->status ? 'badge-success' : 'badge-danger' }} badge-xs">{{ $o->status ? 'Active' : 'Inactive' }}</span>
                        </div>
                    @empty
                        <p class="text-muted text-center py-4 mb-0">No courses assigned yet. The Super Admin assigns courses to your institute.</p>
                    @endforelse
                </div>
            </div>
        </div>

        @include('livewire.admin.dashboard.partials.shared.activity', ['entries' => $d['recent'], 'col' => 'col-xxl-4 col-xl-12'])
    </div>

    <script>
        window.addEventListener('load', function () {
            if (typeof ApexCharts === 'undefined') { return; }
            const students = @json($d['students']->filter(fn ($s) => $s['count'] > 0)->values());
            const hex = @json($statusHex);
            if (students.length) {
                new ApexCharts(document.querySelector('#dash-students'), {
                    chart: { type: 'donut', height: 230 },
                    series: students.map(s => s.count), labels: students.map(s => s.label),
                    colors: students.map(s => hex[s.colour] || '#6C757D'),
                    legend: { show: false }, dataLabels: { enabled: false },
                    plotOptions: { pie: { donut: { size: '72%', labels: { show: true, total: { show: true, label: 'Students', formatter: () => '{{ $d['studentTotal'] }}' } } } } }
                }).render();
            }
            const activity = @json($d['activity']);
            new ApexCharts(document.querySelector('#dash-activity'), {
                chart: { type: 'area', height: 260, toolbar: { show: false } },
                series: [{ name: 'Actions', data: activity.map(a => a.count) }],
                xaxis: { categories: activity.map(a => a.label) }, colors: ['#F26522'],
                stroke: { curve: 'smooth', width: 3 }, fill: { type: 'gradient', gradient: { opacityFrom: .35, opacityTo: .05 } },
                dataLabels: { enabled: false }, grid: { borderColor: '#E5E7EB', strokeDashArray: 4 }
            }).render();
        });
    </script>
</div>
