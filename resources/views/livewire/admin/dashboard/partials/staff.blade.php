{{-- Dashboard for BiC, Examination Manager, HoT and Faculty: institute snapshot, own account, notifications, and what comes with their modules. Data: DashboardService::staff() --}}
@php
    $user = $d['user'];
    $studentTotal = $d['students']->sum('count');
    $active = $d['students']->whereIn('status', ['er_issued', 'active'])->sum('count');
    [$upcomingTitle, $upcomingItems] = $d['upcoming'];
    $sections = collect(\App\Services\MenuService::flatFor($user))
        ->map(fn ($section) => array_merge($section, ['items' => array_values(array_filter($section['items'], fn ($item) => $item['route'] !== 'admin.dashboard'))]))
        ->filter(fn ($section) => count($section['items']) > 0);
@endphp
<div class="content">
    @include('livewire.admin.dashboard.partials.shared.head', ['title' => \Illuminate\Support\Str::before($user->role_display_name, ' (') . ' Dashboard'])

    @include('livewire.admin.dashboard.partials.shared.welcome', [
        'user' => $user,
        'line' => e($user->role_display_name) . ($d['institute'] ? ' · ' . e($d['institute']->name) . ' <span class="badge badge-dark-transparent">' . e($d['institute']->code) . '</span>' : '')
            . ($d['notifications']['unread'] ? '<br>You have <span class="text-primary">' . $d['notifications']['unread'] . '</span> unread notifications.' : ''),
        'actions' => [],
    ])

    @include('livewire.admin.dashboard.partials.shared.pending', ['pending' => $d['pending']])

    <div class="row">
        @foreach([
            ['ti ti-school', 'primary', 'Students', number_format($studentTotal), '<span class="text-muted">in your institute</span>', '', ''],
            ['ti ti-user-check', 'success', 'Enrolled Students', number_format($active), '<span class="text-muted">ER issued / active</span>', '', ''],
            ['ti ti-books', 'pink', 'Courses Offered', $d['courses']->count(), '', '', ''],
            ['ti ti-bell', 'warning', 'Unread Notifications', $d['notifications']['unread'], '', 'View All', 'admin.my-notifications'],
        ] as [$tIcon, $tTone, $tLabel, $tValue, $tExtra, $tLink, $tRoute])
            @include('livewire.admin.dashboard.partials.shared.tile', ['icon' => $tIcon, 'tone' => $tTone, 'label' => $tLabel, 'value' => $tValue, 'extra' => $tExtra, 'link' => $tLink, 'route' => $tRoute, 'col' => 'col-xl-3 col-md-6'])
        @endforeach
    </div>

    <div class="row">
        {{-- Coming with the next modules --}}
        <div class="col-xxl-4 col-xl-6 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Your Work Area</h5>
                    <span class="badge badge-info-transparent mb-2">Coming soon</span>
                </div>
                <div class="card-body">
                    <div class="bg-dark br-5 p-3 mb-3">
                        <p class="fs-13 text-white-50 mb-1">Arriving with</p>
                        <h5 class="text-white mb-0">{{ $upcomingTitle }}</h5>
                    </div>
                    @foreach($upcomingItems as $item)
                        <div class="d-flex align-items-center todo-item border p-2 br-5 mb-2">
                            <i class="ti ti-clock-hour-4 text-info me-2"></i><span class="fw-medium">{{ $item }}</span>
                        </div>
                    @endforeach
                    @if($sections->isNotEmpty())
                        <h6 class="mt-3 mb-2">Available now</h6>
                        <div class="d-flex gap-2 flex-wrap">
                            @foreach($sections as $section)
                                @foreach($section['items'] as $item)
                                    <a href="{{ route($item['route']) }}" class="btn btn-sm btn-outline-primary"><i class="{{ $item['icon'] }} me-1"></i>{{ $item['label'] }}</a>
                                @endforeach
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Institute snapshot --}}
        <div class="col-xxl-4 col-xl-6 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2"><h5 class="mb-2">Institute Snapshot</h5></div>
                <div class="card-body">
                    @if($d['institute'])
                        <div class="p-2 d-flex align-items-center justify-content-between border border-primary bg-primary-100 br-5 mb-3">
                            <div class="d-flex align-items-center overflow-hidden">
                                <span class="me-2"><i class="ti ti-building text-primary fs-24"></i></span>
                                <div class="overflow-hidden"><h6 class="text-truncate mb-1 fs-14 fw-medium">{{ $d['institute']->name }}</h6><p class="fs-13 mb-0">Est. {{ $d['institute']->established_year }}</p></div>
                            </div>
                            <span class="badge badge-dark-transparent">{{ $d['institute']->code }}</span>
                        </div>
                    @endif
                    <h6 class="mb-2">Colleagues</h6>
                    @forelse($d['colleagues'] as $r)
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <p class="fs-13 mb-0"><i class="ti ti-square-filled text-primary fs-12 me-2"></i>{{ $r['label'] }}</p>
                            <span class="fw-medium">{{ $r['count'] }}</span>
                        </div>
                    @empty
                        <p class="text-muted fs-13">No colleagues yet.</p>
                    @endforelse
                    <h6 class="mt-3 mb-2">Courses</h6>
                    <div class="d-flex gap-1 flex-wrap">
                        @forelse($d['courses'] as $o)
                            <span class="badge badge-secondary-transparent" title="{{ optional($o->course)->name }}">{{ optional($o->course)->code }}</span>
                        @empty
                            <span class="text-muted fs-13">No courses yet.</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        @include('livewire.admin.dashboard.partials.shared.notifications', ['notifications' => $d['notifications'], 'col' => 'col-xxl-4 col-xl-12'])
    </div>

    <div class="row">
        {{-- My account --}}
        <div class="col-xl-12 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2"><h5 class="mb-2">My Account &amp; Recent Actions</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="bg-light br-5 p-3 mb-3">
                                <p class="fs-13 mb-1">Signed in as</p><h6 class="mb-2">{{ $user->email }}</h6>
                                <p class="fs-13 mb-1">Role</p><h6 class="mb-2">{{ $user->role_display_name }}</h6>
                                <p class="fs-13 mb-1">Signed in at</p><h6 class="mb-0">{{ $user->last_login_at ? $user->last_login_at->format('d M Y, h:i A') : 'First time — welcome!' }}</h6>
                            </div>
                        </div>
                        <div class="col-md-8">
                            @forelse($d['myActivity'] as $e)
                                <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                                    <span class="fs-13">{{ $e->meta['description'] ?? (\App\Support\Readable::label($e->action) . ' ' . \App\Support\Readable::label($e->module)) }}</span>
                                    <span class="fs-12 text-muted text-nowrap ms-2">{{ $e->created_at->diffForHumans() }}</span>
                                </div>
                            @empty
                                <p class="text-muted fs-13 mb-0">Your actions in CAMP will be listed here.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
