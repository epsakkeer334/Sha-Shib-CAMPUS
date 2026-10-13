{{-- Institute Admin dashboard: own institute (Module 1 staff & set-up; Module 2 admissions). Data: DashboardService::instituteAdmin() --}}
@php
    $L = $d['logins']; $A = $d['admissions']; $F = $d['fees'];
    $inst = $d['institute'];
    $pct = fn ($n, $t) => $t > 0 ? round($n / $t * 100) : 0;
    $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $roleTone = ['institute-admin' => 'indigo', 'accounts' => 'green', 'training-manager' => 'sky', 'bic' => 'slate', 'examination-manager' => 'violet', 'hot' => 'amber', 'faculty' => 'teal'];
    $statusHex = ['secondary' => '#94A3B8', 'warning' => '#F59E0B', 'info' => '#0284C7', 'primary' => '#F26522', 'success' => '#16A34A', 'dark' => '#334155', 'danger' => '#DC2626'];
@endphp
<div class="content db-ui">
    @include('livewire.admin.dashboard.partials.shared.hero', [
        'title' => 'Institute Dashboard', 'user' => $d['user'],
        'line' => '<i class="ti ti-building"></i> ' . e(optional($inst)->name) . (optional($inst)->code ? ' <span class="db-tag font-monospace ms-1">' . e($inst->code) . '</span>' : ''),
        'actions' => [['Add Staff User', 'admin.users', 'ti ti-users-plus', false], ['Add Student', 'admin.students.create', 'ti ti-user-plus', true]],
    ])

    @include('livewire.admin.dashboard.partials.shared.pending', ['pending' => $d['pending'], 'overview' => $d['overview'], 'showInstitute' => false])

    <div class="db-kpis mb-3">
        @foreach([
            ['Students', number_format($d['studentTotal']), '<span class="text-success">+' . $A['this_month'] . '</span> joined this month', 'ti ti-school', 'teal', 'admin.students'],
            ['ER Issued', $A['er_this_month'], 'this month', 'ti ti-id-badge-2', 'green', 'admin.onboarding.enrollment'],
            ['Past Deadline', $A['past_deadline'], 'onboarding not finished', 'ti ti-clock-exclamation', $A['past_deadline'] ? 'red' : 'slate', 'admin.students'],
            ['Fees Outstanding', money_inr($F['outstanding'], false), $F['overdue'] > 0 ? '<span class="text-danger">' . e(money_inr($F['overdue'], false)) . ' overdue</span>' : 'nothing overdue', 'ti ti-currency-rupee', 'orange', 'admin.students'],
            ['Staff Users', $L['active'] . '<small>/' . $L['total'] . '</small>', '<span class="text-success">● ' . $L['online'] . ' online now</span>', 'ti ti-users', 'indigo', 'admin.users'],
            ['Courses Offered', $d['offerings']->count(), 'assigned by the Super Admin', 'ti ti-books', 'pink', 'admin.institute-courses'],
            ['Open Batches', $d['batchesOpen'] . '<small>/' . $d['batchesTotal'] . '</small>', 'open for admission', 'ti ti-users-group', 'sky', 'admin.batches'],
            ['Running Semesters', $d['periodsRunning'], 'academic periods today', 'ti ti-calendar-time', 'violet', 'admin.academic-periods'],
        ] as [$kLabel, $kValue, $kHint, $kIcon, $kTone, $kRoute])
            @include('livewire.admin.dashboard.partials.shared.kpi', ['label' => $kLabel, 'value' => $kValue, 'hint' => $kHint, 'icon' => $kIcon, 'tone' => $kTone, 'route' => $kRoute, 'params' => $kLabel === 'Past Deadline' ? ['overdue' => 1] : []])
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        {{-- Students by status --}}
        <div class="col-xl-4">
            <div class="db-panel">
                <div class="db-panel-head">
                    <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-chart-donut"></i></span><div><h5>Students by Status</h5><small>{{ number_format($d['studentTotal']) }} students</small></div></div>
                    <a href="{{ route('admin.students') }}" class="db-link">View all <i class="ti ti-arrow-right"></i></a>
                </div>
                <div class="db-panel-body">
                    @if($d['studentTotal'])
                        <div id="dash-students"></div>
                        <div class="db-legend mt-2">
                            @foreach($d['students']->filter(fn ($s) => $s['count'] > 0) as $s)
                                <div class="db-legend-item"><span><i class="db-dot" style="background: {{ $statusHex[$s['colour']] ?? '#94A3B8' }}"></i>{{ $s['label'] }}</span><b>{{ $s['count'] }}</b></div>
                            @endforeach
                        </div>
                    @else
                        <div class="db-empty"><i class="ti ti-school-off"></i>No students yet.</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Staff --}}
        <div class="col-xl-4">
            <div class="db-panel">
                <div class="db-panel-head">
                    <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-users"></i></span><div><h5>Staff</h5><small>{{ $L['total'] }} users · {{ $L['today'] }} signed in today</small></div></div>
                    <a href="{{ route('admin.users') }}" class="db-link">Manage <i class="ti ti-arrow-right"></i></a>
                </div>
                <div class="db-panel-body pt-2 pb-1">
                    <div class="db-bar mb-2">
                        <span style="width: {{ $pct($L['week'], $L['total']) }}%; background: #16A34A;"></span>
                        <span style="width: {{ $pct($L['older'], $L['total']) }}%; background: #0284C7;"></span>
                        <span style="width: {{ $pct($L['never'], $L['total']) }}%; background: #F59E0B;"></span>
                    </div>
                    <div class="d-flex gap-3 flex-wrap db-row-sub mb-2">
                        <span><i class="db-dot" style="background:#16A34A"></i>this week {{ $L['week'] }}</span>
                        <span><i class="db-dot" style="background:#0284C7"></i>earlier {{ $L['older'] }}</span>
                        <span><i class="db-dot" style="background:#F59E0B"></i>never {{ $L['never'] }}</span>
                    </div>
                    @foreach($d['staffList'] as $u)
                        @php $role = $u->roles->pluck('name')->first(); $online = $u->last_login_at && $u->last_login_at->gt(now()->subMinutes(15)); @endphp
                        <div class="db-row">
                            <span class="db-av tone-{{ $roleTone[$role] ?? 'slate' }}">{{ $initials($u->name) }}</span>
                            <div class="db-row-main"><div class="db-row-title">{{ $u->name }} @unless($u->status)<span class="db-chip tone-red">Inactive</span>@endunless</div>
                                <div class="db-row-sub">{{ \Illuminate\Support\Str::before($u->role_display_name, ' (') }}</div></div>
                            @if($u->last_login_at)
                                <span class="db-chip {{ $online ? 'tone-green' : 'tone-slate' }}">{{ $online ? '● Online' : $u->last_login_at->diffForHumans(null, true) }}</span>
                            @else
                                <span class="db-chip tone-amber">Never</span>
                            @endif
                        </div>
                    @endforeach
                    @if($d['staffList']->isEmpty())<div class="db-empty"><i class="ti ti-users"></i>No staff users yet.</div>@endif
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        @foreach($d['byRole'] as $r)
                            <span class="db-chip tone-{{ $r['count'] ? ($roleTone[$r['slug']] ?? 'slate') : 'slate' }} {{ $r['count'] ? '' : 'opacity-50' }}">{{ \Illuminate\Support\Str::before($r['label'], ' (') }} {{ $r['count'] }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">@include('livewire.admin.dashboard.partials.shared.checklist', ['title' => 'Institute Set-up', 'items' => $d['checklist']])</div>
    </div>

    <div class="row g-3">
        {{-- Activity --}}
        <div class="col-xl-5">
            <div class="db-panel">
                <div class="db-panel-head">
                    <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-chart-area-line"></i></span><div><h5>Institute Activity</h5><small>Recorded actions · last 6 months</small></div></div>
                </div>
                <div class="db-panel-body"><div id="dash-activity"></div></div>
            </div>
        </div>

        {{-- Courses --}}
        <div class="col-xl-3">
            <div class="db-panel">
                <div class="db-panel-head">
                    <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-books"></i></span><h5>Courses</h5></div>
                    <a href="{{ route('admin.institute-courses') }}" class="db-link">View <i class="ti ti-arrow-right"></i></a>
                </div>
                <div class="db-panel-body pt-1 pb-1">
                    @forelse($d['offerings']->take(6) as $o)
                        <div class="db-row">
                            <span class="db-av tone-pink" style="font-size: 10px;">{{ \Illuminate\Support\Str::limit(optional($o->course)->code, 5, '') }}</span>
                            <div class="db-row-main"><div class="db-row-title">{{ optional($o->course)->name }}</div><div class="db-row-sub">{{ optional($o->course)->period_summary }}</div></div>
                            @unless($o->status)<span class="db-chip tone-red">Inactive</span>@endunless
                        </div>
                    @empty
                        <div class="db-empty"><i class="ti ti-books-off"></i>No courses assigned yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-4">@include('livewire.admin.dashboard.partials.shared.activity', ['entries' => $d['recent']])</div>
    </div>

    @include('livewire.admin.dashboard.partials.shared.styles')

    <script>
        window.addEventListener('load', function () {
            if (typeof ApexCharts === 'undefined') { return; }
            const students = @json($d['students']->filter(fn ($s) => $s['count'] > 0)->values());
            const hex = @json($statusHex);
            if (students.length && document.querySelector('#dash-students')) {
                new ApexCharts(document.querySelector('#dash-students'), {
                    chart: { type: 'donut', height: 220, fontFamily: 'inherit' },
                    series: students.map(s => s.count), labels: students.map(s => s.label), colors: students.map(s => hex[s.colour] || '#94A3B8'),
                    legend: { show: false }, dataLabels: { enabled: false }, stroke: { width: 2 },
                    plotOptions: { pie: { donut: { size: '74%', labels: { show: true, total: { show: true, label: 'Students', formatter: () => '{{ $d['studentTotal'] }}' } } } } }
                }).render();
            }
            const activity = @json($d['activity']);
            new ApexCharts(document.querySelector('#dash-activity'), {
                chart: { type: 'area', height: 250, toolbar: { show: false }, fontFamily: 'inherit' },
                series: [{ name: 'Actions', data: activity.map(a => a.count) }],
                xaxis: { categories: activity.map(a => a.label) }, colors: ['#F26522'],
                stroke: { curve: 'smooth', width: 3 }, fill: { type: 'gradient', gradient: { opacityFrom: .35, opacityTo: .04 } },
                dataLabels: { enabled: false }, grid: { borderColor: '#F1F2F4', strokeDashArray: 4 }, markers: { size: 3 }
            }).render();
        });
    </script>
</div>
