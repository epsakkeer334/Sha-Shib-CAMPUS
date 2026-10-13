{{-- Dashboard for BiC, Examination Manager, HoT and Faculty: institute snapshot, own account, notifications, and what comes with their modules. Data: DashboardService::staff() --}}
@php
    $user = $d['user'];
    $studentTotal = $d['students']->sum('count');
    $enrolled = $d['students']->whereIn('status', ['er_issued', 'active'])->sum('count');
    [$upcomingTitle, $upcomingItems] = $d['upcoming'];
    $sections = collect(\App\Services\MenuService::flatFor($user))
        ->map(fn ($section) => array_merge($section, ['items' => array_values(array_filter($section['items'], fn ($item) => $item['route'] !== 'admin.dashboard'))]))
        ->filter(fn ($section) => count($section['items']) > 0);
    $roleTone = ['institute-admin' => 'indigo', 'accounts' => 'green', 'training-manager' => 'sky', 'bic' => 'slate', 'examination-manager' => 'violet', 'hot' => 'amber', 'faculty' => 'teal'];
@endphp
<div class="content db-ui">
    @include('livewire.admin.dashboard.partials.shared.hero', [
        'title' => \Illuminate\Support\Str::before($user->role_display_name, ' (') . ' Dashboard', 'user' => $user,
        'line' => e($user->role_display_name) . ($d['institute'] ? ' · <i class="ti ti-building"></i> ' . e($d['institute']->name) . ' <span class="db-tag font-monospace ms-1">' . e($d['institute']->code) . '</span>' : ''),
        'actions' => [],
    ])

    @include('livewire.admin.dashboard.partials.shared.pending', ['pending' => $d['pending']])

    <div class="db-kpis mb-3">
        @foreach([
            ['Students', number_format($studentTotal), 'in your institute', 'ti ti-school', 'teal', ''],
            ['Enrolled', number_format($enrolled), 'ER issued / active', 'ti ti-user-check', 'green', ''],
            ['Courses Offered', $d['courses']->count(), 'at your institute', 'ti ti-books', 'pink', ''],
            ['Unread Notifications', $d['notifications']['unread'], 'in your bell', 'ti ti-bell', 'amber', 'admin.my-notifications'],
        ] as [$kLabel, $kValue, $kHint, $kIcon, $kTone, $kRoute])
            @include('livewire.admin.dashboard.partials.shared.kpi', ['label' => $kLabel, 'value' => $kValue, 'hint' => $kHint, 'icon' => $kIcon, 'tone' => $kTone, 'route' => $kRoute, 'params' => []])
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        {{-- Work area --}}
        <div class="col-xl-4">
            <div class="db-panel">
                <div class="db-panel-head">
                    <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-briefcase"></i></span><div><h5>Your Work Area</h5><small>Arriving with {{ $upcomingTitle }}</small></div></div>
                    <span class="db-chip tone-sky">Coming soon</span>
                </div>
                <div class="db-panel-body pt-1 pb-1">
                    @foreach($upcomingItems as $item)
                        <div class="db-row"><span class="db-av db-av-round tone-sky"><i class="ti ti-clock-hour-4"></i></span><div class="db-row-main"><div class="db-row-title fw-medium">{{ $item }}</div></div></div>
                    @endforeach
                    @if($sections->isNotEmpty())
                        <div class="db-row-sub text-uppercase fw-semibold mt-3 mb-2">Available now</div>
                        <div class="d-flex gap-2 flex-wrap mb-2">
                            @foreach($sections as $section)
                                @foreach($section['items'] as $item)
                                    <a href="{{ route($item['route']) }}" class="db-btn"><i class="{{ $item['icon'] }}"></i>{{ $item['label'] }}</a>
                                @endforeach
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Institute snapshot --}}
        <div class="col-xl-4">
            <div class="db-panel">
                <div class="db-panel-head">
                    <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-building"></i></span><div><h5>Institute Snapshot</h5><small>{{ optional($d['institute'])->name ?? 'No institute' }}</small></div></div>
                    @if($d['institute'])<span class="db-tag">Est. {{ $d['institute']->established_year }}</span>@endif
                </div>
                <div class="db-panel-body">
                    <div class="db-row-sub text-uppercase fw-semibold mb-2">Colleagues</div>
                    <div class="d-flex flex-wrap gap-1 mb-3">
                        @forelse($d['colleagues'] as $r)
                            <span class="db-chip tone-{{ $roleTone[$r['slug']] ?? 'slate' }}">{{ \Illuminate\Support\Str::before($r['label'], ' (') }} · {{ $r['count'] }}</span>
                        @empty
                            <span class="db-row-sub">No colleagues yet.</span>
                        @endforelse
                    </div>
                    <div class="db-row-sub text-uppercase fw-semibold mb-2">Courses</div>
                    <div class="d-flex flex-wrap gap-1 mb-3">
                        @forelse($d['courses'] as $o)
                            <span class="db-chip tone-pink" title="{{ optional($o->course)->name }}">{{ optional($o->course)->code }}</span>
                        @empty
                            <span class="db-row-sub">No courses yet.</span>
                        @endforelse
                    </div>
                    <div class="db-row-sub text-uppercase fw-semibold mb-2">Students</div>
                    <div class="db-legend">
                        @foreach($d['students']->filter(fn ($s) => $s['count'] > 0) as $s)
                            <div class="db-legend-item"><span>{{ $s['label'] }}</span><b>{{ $s['count'] }}</b></div>
                        @endforeach
                        @if(!$studentTotal)<span class="db-row-sub">No students yet.</span>@endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">@include('livewire.admin.dashboard.partials.shared.notifications', ['notifications' => $d['notifications']])</div>
    </div>

    {{-- My account --}}
    <div class="db-panel">
        <div class="db-panel-head">
            <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-user-circle"></i></span><div><h5>My Account &amp; Recent Actions</h5><small>{{ $user->email }}</small></div></div>
        </div>
        <div class="db-panel-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="db-mini-stats" style="grid-template-columns: 1fr;">
                        <div><span>Role</span><b class="fs-14">{{ $user->role_display_name }}</b></div>
                        <div style="border-top: 1px solid #F1F2F4;"><span>Signed in at</span><b class="fs-14">{{ $user->last_login_at ? $user->last_login_at->format('d M Y, h:i A') : 'First time — welcome!' }}</b></div>
                    </div>
                </div>
                <div class="col-md-8">
                    @forelse($d['myActivity'] as $e)
                        <div class="db-row">
                            <span class="db-av db-av-round tone-indigo"><i class="ti ti-point"></i></span>
                            <div class="db-row-main"><div class="db-row-title fw-medium">{{ $e->meta['description'] ?? (\App\Support\Readable::label($e->action) . ' ' . \App\Support\Readable::label($e->module)) }}</div></div>
                            <span class="db-row-sub text-nowrap">{{ $e->created_at->diffForHumans() }}</span>
                        </div>
                    @empty
                        <div class="db-empty"><i class="ti ti-history"></i>Your actions in CAMP will be listed here.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @include('livewire.admin.dashboard.partials.shared.styles')
</div>
