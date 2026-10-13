{{-- Super Admin dashboard: system-wide figures (Module 1 + master data; Module 2 headline numbers). Data: DashboardService::superAdmin() --}}
@php
    $L = $d['logins']; $A = $d['admissions'];
    $pct = fn ($n, $t) => $t > 0 ? round($n / $t * 100) : 0;
    $roleTone = ['super-admin' => 'red', 'institute-admin' => 'indigo', 'accounts' => 'green', 'training-manager' => 'sky', 'bic' => 'slate', 'examination-manager' => 'violet', 'hot' => 'amber', 'faculty' => 'teal'];
    $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $N = $d['notifications'];
    $notifyTotal = $N['sent'] + $N['pending'] + $N['failed'];
@endphp
<div class="content db-ui">
    @include('livewire.admin.dashboard.partials.shared.hero', [
        'title' => 'Super Admin Dashboard', 'user' => $d['user'],
        'line' => 'System overview of ' . $d['institutes']->count() . ' ' . \Illuminate\Support\Str::plural('institute', $d['institutes']->count()) . ', ' . $L['total'] . ' staff users and ' . number_format($A['students']) . ' students.',
        'actions' => [['Add User', 'admin.users', 'ti ti-user-plus', false], ['Add Institute', 'admin.institutes', 'ti ti-building-plus', true]],
    ])

    @include('livewire.admin.dashboard.partials.shared.pending', ['pending' => $d['pending']])

    {{-- KPIs --}}
    <div class="db-kpis mb-3">
        @foreach([
            ['Institutes', $d['institutesActive'] . '<small>/' . $d['institutes']->count() . '</small>', 'active institutes', 'ti ti-building-community', 'orange', 'admin.institutes'],
            ['Staff Users', $L['active'] . '<small>/' . $L['total'] . '</small>', '<span class="text-success">● ' . $L['online'] . ' online now</span>', 'ti ti-users', 'indigo', 'admin.users'],
            ['Students', number_format($A['students']), '<span class="text-success">+' . $A['this_month'] . '</span> joined this month', 'ti ti-school', 'teal', 'admin.students'],
            ['Pending Approvals', $A['docs_pending'] + $A['payments_to_verify'], $A['past_deadline'] ? '<span class="text-danger">' . $A['past_deadline'] . ' past onboarding deadline</span>' : 'documents & payments', 'ti ti-checklist', 'amber', 'admin.onboarding.documents'],
            ['Signed In Today', $L['today'], $L['week'] . ' signed in this week', 'ti ti-login', 'sky', 'admin.users'],
            ['Courses', $d['courses']['active'] . '<small>/' . $d['courses']['total'] . '</small>', $d['courses']['offerings'] . ' institute offerings', 'ti ti-books', 'pink', 'admin.masters.courses'],
            ['Master Data', number_format($d['masterTotal']), $d['masters']->count() . ' lists', 'ti ti-database', 'violet', 'admin.masters.academic-years'],
            ['Actions Today', $d['activityToday'], number_format($d['activityMonth']) . ' this month', 'ti ti-history', 'slate', 'admin.audit-trail'],
        ] as [$kLabel, $kValue, $kHint, $kIcon, $kTone, $kRoute])
            @include('livewire.admin.dashboard.partials.shared.kpi', ['label' => $kLabel, 'value' => $kValue, 'hint' => $kHint, 'icon' => $kIcon, 'tone' => $kTone, 'route' => $kRoute, 'params' => []])
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        {{-- Users by role --}}
        <div class="col-xl-4">
            <div class="db-panel">
                <div class="db-panel-head">
                    <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-shield-lock"></i></span><div><h5>Users by Role</h5><small>{{ $L['total'] }} staff users · {{ $pct($L['active'], $L['total']) }}% active</small></div></div>
                    <a href="{{ route('admin.roles') }}" class="db-link">Roles <i class="ti ti-arrow-right"></i></a>
                </div>
                <div class="db-panel-body">
                    <div id="dash-roles"></div>
                    @php
                        $usedRoles = $d['byRole']->filter(fn ($r) => $r['count'] > 0);
                        $topRole = $usedRoles->sortByDesc('count')->first();
                        $unusedRoles = $d['byRole']->filter(fn ($r) => $r['count'] === 0);
                    @endphp
                    <div class="db-mini-stats mt-2">
                        <div><span>Roles in use</span><b>{{ $usedRoles->count() }}<small class="text-muted fs-12">/{{ $d['byRole']->count() }}</small></b></div>
                        <div><span>Largest</span><b class="fs-14">{{ $topRole ? \Illuminate\Support\Str::before($topRole['label'], ' (') : '—' }}</b></div>
                        <div><span>Active</span><b>{{ $pct($L['active'], $L['total']) }}%</b></div>
                    </div>
                    @if($unusedRoles->isNotEmpty())
                        <div class="db-row-sub mt-2" title="{{ $unusedRoles->pluck('label')->implode(', ') }}"><i class="ti ti-info-circle"></i> No users yet: {{ $unusedRoles->map(fn ($r) => \Illuminate\Support\Str::before($r['label'], ' ('))->implode(', ') }}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Login activity --}}
        <div class="col-xl-4">
            <div class="db-panel">
                <div class="db-panel-head">
                    <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-activity"></i></span><div><h5>Login Activity</h5><small>How recently staff signed in</small></div></div>
                    <a href="{{ route('admin.users', ['login' => 'never']) }}" class="db-link">Never signed in <i class="ti ti-arrow-right"></i></a>
                </div>
                <div class="db-panel-body">
                    <div id="dash-logins"></div>
                    <div class="db-legend mt-2">
                        @foreach([['Online now', $L['online'], 'green'], ['This week', max(0, $L['week'] - $L['online']), 'orange'], ['Earlier', $L['older'], 'sky'], ['Never', $L['never'], 'amber']] as [$aLabel, $aCount, $aTone])
                            <div class="db-legend-item tone-{{ $aTone }}"><span><i class="db-dot"></i>{{ $aLabel }}</span><b>{{ $aCount }}</b></div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- People --}}
        <div class="col-xl-4">
            <div class="db-panel">
                <div class="db-panel-head">
                    <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-user-circle"></i></span><h5>People</h5></div>
                    <div class="db-tabs" role="tablist">
                        <button type="button" class="active" data-bs-toggle="tab" data-bs-target="#dbp-recent">Recent</button>
                        <button type="button" data-bs-toggle="tab" data-bs-target="#dbp-new">New</button>
                        <button type="button" data-bs-toggle="tab" data-bs-target="#dbp-never">Never ({{ $L['never'] }})</button>
                    </div>
                </div>
                <div class="db-panel-body pt-1 pb-1 tab-content">
                    @foreach([['dbp-recent', $d['recentLogins'], 'recent'], ['dbp-new', $d['recentUsers'], 'new'], ['dbp-never', $d['neverLoggedIn'], 'never']] as [$tabId, $people, $mode])
                        <div class="tab-pane fade {{ $mode === 'recent' ? 'show active' : '' }}" id="{{ $tabId }}">
                            @forelse($people as $u)
                                @php $role = $u->roles->pluck('name')->first(); $online = $u->last_login_at && $u->last_login_at->gt(now()->subMinutes(15)); @endphp
                                <div class="db-row">
                                    <span class="db-av tone-{{ $roleTone[$role] ?? 'slate' }}">{{ $initials($u->name) }}</span>
                                    <div class="db-row-main">
                                        <div class="db-row-title">{{ $u->name }}</div>
                                        <div class="db-row-sub">{{ \Illuminate\Support\Str::before($u->role_display_name, ' (') }} · {{ optional($u->institute)->code ?? 'Central' }}</div>
                                    </div>
                                    @if($mode === 'recent')
                                        <span class="db-chip {{ $online ? 'tone-green' : 'tone-slate' }}">{{ $online ? '● Online' : $u->last_login_at->diffForHumans(null, true) }}</span>
                                    @elseif($mode === 'new')
                                        <span class="db-row-sub">{{ $u->created_at->format('d M') }}</span>
                                    @else
                                        <span class="db-chip tone-amber">added {{ $u->created_at->diffForHumans(null, true) }} ago</span>
                                    @endif
                                </div>
                            @empty
                                <div class="db-empty is-ok"><i class="ti ti-circle-check"></i>Nobody here.</div>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        {{-- System activity --}}
        <div class="col-xl-8">
            <div class="db-panel">
                <div class="db-panel-head">
                    <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-chart-bar"></i></span><div><h5>System Activity</h5><small>Recorded actions per month · last 12 months</small></div></div>
                    @if($d['mostActive'] && $d['mostActive']['institute'])
                        <span class="db-tag"><i class="ti ti-award text-warning"></i> Most active: {{ $d['mostActive']['institute']->code }} · {{ number_format($d['mostActive']['count']) }}</span>
                    @endif
                </div>
                <div class="db-panel-body"><div id="dash-activity"></div></div>
            </div>
        </div>
        <div class="col-xl-4">
            @include('livewire.admin.dashboard.partials.shared.checklist', ['title' => 'System Set-up', 'items' => $d['checklist']])
        </div>
    </div>

    <div class="row g-3 mb-3">
        {{-- Institutes --}}
        <div class="col-xl-8">
            <div class="db-panel">
                <div class="db-panel-head">
                    <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-building-community"></i></span><div><h5>Institutes</h5><small>{{ $d['institutesActive'] }} active of {{ $d['institutes']->count() }}</small></div></div>
                    <a href="{{ route('admin.institutes') }}" class="db-link">Manage <i class="ti ti-arrow-right"></i></a>
                </div>
                <div class="db-panel-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 db-table">
                            <thead><tr><th>Institute</th><th>Courses</th><th>Staff</th><th>Students</th><th>Status</th></tr></thead>
                            <tbody>
                                @forelse($d['institutes']->take(8) as $inst)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="db-av tone-orange">{{ $initials($inst->name) }}</span>
                                                <div class="min-w-0"><div class="db-row-title" style="max-width: 280px;">{{ $inst->name }}</div><div class="db-row-sub font-monospace">{{ $inst->code }} · est. {{ $inst->established_year }}</div></div>
                                            </div>
                                        </td>
                                        <td>{{ $inst->courses_count }}</td>
                                        <td>{{ $inst->staff_count }}</td>
                                        <td style="min-width: 140px;">
                                            <div class="d-flex align-items-center gap-2"><b>{{ $inst->students_count }}</b>
                                                <div class="db-bar flex-fill tone-orange" style="height: 6px;"><span style="width: {{ $pct($inst->students_count, max(1, $A['students'])) }}%"></span></div></div>
                                        </td>
                                        <td><span class="db-chip {{ $inst->status ? 'tone-green' : 'tone-red' }}">{{ $inst->status ? 'Active' : 'Inactive' }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5"><div class="db-empty"><i class="ti ti-building-off"></i>No institutes yet.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Master data --}}
        <div class="col-xl-4">
            <div class="db-panel">
                <div class="db-panel-head">
                    <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-database"></i></span><div><h5>Master Data</h5><small>{{ number_format($d['masterTotal']) }} records in {{ $d['masters']->count() }} lists</small></div></div>
                </div>
                <div class="db-panel-body">
                    <div class="db-grid">
                        @foreach($d['masters'] as $m)
                            <a href="{{ route($m['route']) }}" class="db-grid-item" title="{{ $m['active'] }} active{{ $m['total'] - $m['active'] ? ', ' . ($m['total'] - $m['active']) . ' inactive' : '' }}">
                                <span class="db-av tone-slate" style="width: 30px; height: 30px; font-size: 14px;"><i class="{{ $m['icon'] }}"></i></span>
                                <div class="min-w-0"><div class="db-row-title" style="font-size: 12.5px;">{{ $m['label'] }}</div>
                                    @if($m['total'] - $m['active'])<div class="db-row-sub">{{ $m['total'] - $m['active'] }} inactive</div>@endif</div>
                                <b>{{ number_format($m['total']) }}</b>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-4">@include('livewire.admin.dashboard.partials.shared.activity', ['entries' => $d['recent']])</div>

        {{-- Email & SMS --}}
        <div class="col-xl-4">
            <div class="db-panel">
                <div class="db-panel-head">
                    <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-mail"></i></span><div><h5>Email &amp; SMS</h5><small>{{ $N['email'] }} email · {{ $N['sms'] }} SMS</small></div></div>
                    <a href="{{ route('admin.notifications') }}" class="db-link">View log <i class="ti ti-arrow-right"></i></a>
                </div>
                <div class="db-panel-body">
                    <div id="dash-notify"></div>
                    <div class="db-mini-stats mt-2">
                        <div><span>Sent</span><b class="text-success">{{ $N['sent'] }}</b></div>
                        <div><span>Pending</span><b class="text-warning">{{ $N['pending'] }}</b></div>
                        <div><span>Failed</span><b class="text-danger">{{ $N['failed'] }}</b></div>
                    </div>
                    <div class="db-row-sub mt-2"><i class="ti ti-info-circle"></i> Email sending is switched off until SMTP2GO is set up.</div>
                </div>
            </div>
        </div>

        {{-- Academic year & serial numbers --}}
        <div class="col-xl-4">
            <div class="db-panel">
                <div class="db-panel-head">
                    <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-calendar-stats"></i></span><div><h5>Academic Year</h5><small>Calendar &amp; serial numbers</small></div></div>
                    <a href="{{ route('admin.masters.academic-years') }}" class="db-link">Manage <i class="ti ti-arrow-right"></i></a>
                </div>
                <div class="db-panel-body">
                    @if($d['currentYear'])
                        @php $cy = $d['currentYear']; $elapsed = $pct(today()->diffInDays($cy->start_date), max(1, $cy->end_date->diffInDays($cy->start_date))); @endphp
                        <div class="d-flex align-items-end justify-content-between mb-1">
                            <div><div class="db-row-sub">Current year</div><div class="fs-20 fw-bold text-dark">{{ $cy->name }}</div></div>
                            <span class="db-chip tone-orange">{{ $elapsed }}% through</span>
                        </div>
                        <div class="db-bar tone-orange mb-1"><span style="width: {{ $elapsed }}%"></span></div>
                        <div class="db-row-sub mb-2">{{ $cy->start_date->format('d M Y') }} – {{ $cy->end_date->format('d M Y') }}</div>
                    @else
                        <div class="alert alert-warning py-2 fs-13">No academic year covers today.</div>
                    @endif
                    @foreach($d['academicYears']->filter(fn ($y) => $y->start_date->isFuture())->take(2) as $y)
                        <div class="db-row"><span class="db-av tone-slate"><i class="ti ti-calendar"></i></span>
                            <div class="db-row-main"><div class="db-row-title">{{ $y->name }}</div><div class="db-row-sub">from {{ $y->start_date->format('d M Y') }}</div></div>
                            <span class="db-row-sub">in {{ today()->diffInDays($y->start_date) }} days</span></div>
                    @endforeach
                    <div class="db-row-sub text-uppercase fw-semibold mt-3 mb-1">Serial numbers · {{ now()->year }}</div>
                    @forelse($d['serials'] as $s)
                        @php $series = config("camp.serial_series.{$s->series_key}"); @endphp
                        <div class="db-row">
                            <span class="db-av tone-violet"><i class="ti ti-hash"></i></span>
                            <div class="db-row-main"><div class="db-row-title">{{ \App\Support\Readable::label(strtolower($s->series_key)) }} <span class="db-row-sub fw-normal">· {{ $s->institute ? $s->institute->code : 'group-wide' }}</span></div>
                                <div class="db-row-sub font-monospace">last {{ str_replace(['{year}', '{institute_code}'], [$s->year, optional($s->institute)->code], $series['format'] ?? '') }}{{ str_pad($s->last_value, $series['pad'] ?? 5, '0', STR_PAD_LEFT) }}</div></div>
                            <b class="text-dark">{{ number_format($s->last_value) }}</b>
                        </div>
                    @empty
                        <div class="db-row-sub">No numbers issued yet this year.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @include('livewire.admin.dashboard.partials.shared.styles')

    <script>
        window.addEventListener('load', function () {
            if (typeof ApexCharts === 'undefined') { return; }
            const accent = '#F26522';
            // Users by Role: sorted, one colour per role (same as the role chips), "count · share" at each bar end
            const roleHex = { 'super-admin': '#DC2626', 'institute-admin': '#4F46E5', 'accounts': '#16A34A', 'training-manager': '#0284C7',
                'bic': '#64748B', 'examination-manager': '#7C3AED', 'hot': '#D97706', 'faculty': '#0D9488' };
            const roles = @json($d['byRole']->filter(fn ($r) => $r['count'] > 0)->sortByDesc('count')->values());
            const roleTotal = roles.reduce((t, r) => t + r.count, 0) || 1;
            new ApexCharts(document.querySelector('#dash-roles'), {
                chart: { type: 'bar', height: Math.max(220, roles.length * 38 + 30), toolbar: { show: false }, fontFamily: 'inherit',
                    animations: { enabled: true, easing: 'easeout', speed: 900, animateGradually: { enabled: true, delay: 90 } } },
                series: [{ name: 'Users', data: roles.map(r => r.count) }],
                xaxis: { categories: roles.map(r => r.label.replace(/ \(.*\)$/, '')), labels: { show: false }, axisBorder: { show: false }, axisTicks: { show: false },
                    max: Math.max(...roles.map(r => r.count)) * 1.28 },
                yaxis: { labels: { style: { fontSize: '12.5px', fontWeight: 600, colors: '#374151' }, maxWidth: 150 } },
                plotOptions: { bar: { horizontal: true, borderRadius: 7, borderRadiusApplication: 'end', barHeight: '62%', distributed: true,
                    dataLabels: { position: 'top' } } },
                colors: roles.map(r => roleHex[r.slug] || '#64748B'),
                fill: { type: 'gradient', gradient: { shade: 'light', type: 'horizontal', shadeIntensity: .25, opacityFrom: .75, opacityTo: 1, stops: [0, 100] } },
                dataLabels: { enabled: true, offsetX: 30, textAnchor: 'start', style: { fontSize: '12px', fontWeight: 700, colors: ['#111827'] },
                    formatter: (v) => v + '  ·  ' + Math.round(v / roleTotal * 100) + '%' },
                tooltip: { y: { formatter: (v) => v + (v === 1 ? ' user' : ' users') + ' (' + Math.round(v / roleTotal * 100) + '% of staff)' } },
                grid: { show: false, padding: { left: 0, right: 10, top: -10, bottom: -10 } },
                legend: { show: false }, states: { hover: { filter: { type: 'darken', value: .9 } } }
            }).render();
            new ApexCharts(document.querySelector('#dash-logins'), {
                chart: { type: 'donut', height: 210, fontFamily: 'inherit' },
                series: [{{ $L['online'] }}, {{ max(0, $L['week'] - $L['online']) }}, {{ $L['older'] }}, {{ $L['never'] }}],
                labels: ['Online now', 'This week', 'Earlier', 'Never'], colors: ['#16A34A', accent, '#0284C7', '#F59E0B'],
                legend: { show: false }, dataLabels: { enabled: false }, stroke: { width: 2 },
                plotOptions: { pie: { donut: { size: '74%', labels: { show: true, total: { show: true, label: 'Staff users', formatter: () => '{{ $L['total'] }}' } } } } }
            }).render();
            const activity = @json($d['activity']);
            new ApexCharts(document.querySelector('#dash-activity'), {
                chart: { type: 'area', height: 290, toolbar: { show: false }, fontFamily: 'inherit' },
                series: [{ name: 'Actions', data: activity.map(a => a.count) }],
                xaxis: { categories: activity.map(a => a.label) }, colors: [accent],
                stroke: { curve: 'smooth', width: 3 }, fill: { type: 'gradient', gradient: { opacityFrom: .35, opacityTo: .04 } },
                dataLabels: { enabled: false }, grid: { borderColor: '#F1F2F4', strokeDashArray: 4 }, markers: { size: 3 }
            }).render();
            new ApexCharts(document.querySelector('#dash-notify'), {
                chart: { type: 'donut', height: 190, fontFamily: 'inherit' },
                series: [{{ $N['sent'] }}, {{ $N['pending'] }}, {{ $N['failed'] }}], labels: ['Sent', 'Pending', 'Failed'],
                colors: ['#16A34A', '#F59E0B', '#DC2626'], legend: { show: false }, dataLabels: { enabled: false },
                plotOptions: { pie: { startAngle: -90, endAngle: 90, offsetY: 10, donut: { size: '74%', labels: { show: true, total: { show: true, label: 'Messages', formatter: () => '{{ $notifyTotal }}' } } } } },
                grid: { padding: { bottom: -60 } }
            }).render();
        });
    </script>
</div>
