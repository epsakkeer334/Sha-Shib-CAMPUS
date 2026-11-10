@php
    use App\Http\Livewire\Admin\Monitoring\LoginActivityComponent as LA;
    use App\Models\Admin\LoginLog;
    use Illuminate\Support\Str;
    $dayLabel = fn ($d) => $d->isToday() ? 'Today' : ($d->isYesterday() ? 'Yesterday' : $d->format('l, d M Y'));
    $lastDay = null;
    $deviceIcon = ['desktop' => 'ti ti-device-desktop', 'mobile' => 'ti ti-device-mobile', 'tablet' => 'ti ti-device-ipad', 'bot' => 'ti ti-robot'];
    $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    $problems = $stats['failed'] + $stats['blocked'];
@endphp
<div class="content mon-ui la-ui">
    {{-- Header --}}
    <div class="mon-head mb-3">
        <div class="min-w-0">
            <h2 class="mb-1 fw-bold">Login Activity</h2>
            <nav>
                <ol class="breadcrumb mb-1 sl-crumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Monitoring</li>
                    <li class="breadcrumb-item active">Login Activity</li>
                </ol>
            </nav>
            <div class="text-muted small">Every sign-in, failed attempt and sign-out of the admin panel and the student admissions portal. Kept for {{ LoginLog::KEEP_DAYS }} days.</div>
        </div>
        <span class="mon-count"><span class="la-dot"></span> {{ number_format($stats['online']) }} online now</span>
    </div>

    {{-- Today --}}
    <div class="la-kpis mb-3">
        <div class="la-kpi">
            <span class="la-kpi-icon mon-tone-green"><i class="ti ti-login"></i></span>
            <div class="min-w-0">
                <span class="la-kpi-label">Sign-ins today</span>
                <strong>{{ number_format($stats['logins']) }}</strong>
                <small>{{ number_format($stats['admin']) }} admin panel · {{ number_format($stats['portal']) }} student portal</small>
            </div>
        </div>
        <div class="la-kpi">
            <span class="la-kpi-icon mon-tone-blue"><i class="ti ti-users"></i></span>
            <div class="min-w-0">
                <span class="la-kpi-label">People signed in today</span>
                <strong>{{ number_format($stats['people']) }}</strong>
                <small>different accounts</small>
            </div>
        </div>
        <button type="button" class="la-kpi la-kpi-btn {{ $problems ? 'is-alert' : '' }}" wire:click="$set('filterEvent', 'problems')" title="Show failed and blocked attempts">
            <span class="la-kpi-icon mon-tone-{{ $problems ? 'red' : 'slate' }}"><i class="ti ti-shield-x"></i></span>
            <div class="min-w-0">
                <span class="la-kpi-label">Failed / blocked today</span>
                <strong>{{ number_format($problems) }}</strong>
                <small>{{ number_format($stats['failed']) }} wrong password · {{ number_format($stats['blocked']) }} blocked</small>
            </div>
        </button>
        <div class="la-kpi">
            <span class="la-kpi-icon mon-tone-teal"><i class="ti ti-broadcast"></i></span>
            <div class="min-w-0">
                <span class="la-kpi-label">Online now</span>
                <strong>{{ number_format($stats['online']) }}</strong>
                <small>{{ number_format($stats['online_staff']) }} staff · {{ number_format($stats['online_students']) }} students (active in 15 min)</small>
            </div>
        </div>
    </div>

    <div class="la-row mb-3">
        {{-- Last 14 days --}}
        <div class="mon-panel la-card">
            <div class="la-card-head">
                <div><h6 class="mb-0">Last 14 days</h6><span class="mon-sub">Sign-ins and failed / blocked attempts per day</span></div>
                <div class="la-legend"><span><i class="la-sw la-sw-ok"></i> Signed in</span><span><i class="la-sw la-sw-bad"></i> Failed / blocked</span></div>
            </div>
            <div class="la-chart" role="img" aria-label="Sign-ins per day for the last 14 days">
                @foreach($chart['days'] as $d)
                    @php $total = $d['ok'] + $d['bad']; @endphp
                    <div class="la-bar-col {{ $d['day']->isToday() ? 'is-today' : '' }}" title="{{ $d['day']->format('D, d M') }}: {{ $d['ok'] }} signed in, {{ $d['bad'] }} failed / blocked">
                        <span class="la-bar-num">{{ $total ?: '' }}</span>
                        <div class="la-bar">
                            @if($d['bad'])<span class="la-bar-bad" style="height: {{ round($d['bad'] / $chart['max'] * 100, 2) }}%"></span>@endif
                            @if($d['ok'])<span class="la-bar-ok" style="height: {{ round($d['ok'] / $chart['max'] * 100, 2) }}%"></span>@endif
                        </div>
                        <span class="la-bar-day">{{ $d['day']->isToday() ? 'Today' : $d['day']->format('d') }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Watch list --}}
        <div class="mon-panel la-card">
            <div class="la-card-head">
                <div><h6 class="mb-0"><i class="ti ti-eye-exclamation text-danger me-1"></i>Watch list</h6><span class="mon-sub">{{ LA::WATCH_MIN }}+ failed / blocked attempts in 24 hours</span></div>
            </div>
            @forelse($watch as $w)
                <button type="button" class="la-watch" wire:click="lookFor(@js($w['term']))" title="Show these attempts">
                    <span class="la-watch-icon"><i class="ti {{ $w['type'] === 'ip' ? 'ti-world' : 'ti-at' }}"></i></span>
                    <span class="min-w-0 flex-grow-1 text-start">
                        <span class="d-block fw-semibold text-truncate {{ $w['type'] === 'ip' ? 'mon-mono' : '' }}">{{ $w['term'] }}</span>
                        <span class="mon-sub">{{ $w['type'] === 'ip' ? $w['accounts'] . ' ' . Str::plural('email', $w['accounts']) . ' tried' : 'from ' . $w['accounts'] . ' ' . Str::plural('IP', $w['accounts']) }} · last {{ \Carbon\Carbon::parse($w['last_at'])->diffForHumans() }}</span>
                    </span>
                    <span class="mon-chip mon-tone-red">{{ $w['total'] }}×</span>
                </button>
            @empty
                <div class="la-watch-empty"><i class="ti ti-shield-check"></i><div class="fw-semibold">All clear</div><span class="mon-sub">No repeated failed sign-ins in the last 24 hours.</span></div>
            @endforelse
        </div>
    </div>

    {{-- One person --}}
    @if($focus)
        @php [$roleLabel, $roleTone] = LA::roleOf($focus['user']); @endphp
        <div class="la-person mb-3">
            <span class="mon-avatar la-avatar-lg">{{ $initials($focus['user']->name) }}</span>
            <div class="min-w-0 flex-grow-1">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <strong class="la-person-name">{{ $focus['user']->name }}</strong>
                    <span class="mon-chip mon-tone-{{ $roleTone }}">{{ $roleLabel }}</span>
                    @if(!$focus['user']->status)<span class="mon-chip mon-tone-red">Inactive</span>@endif
                </div>
                <div class="mon-sub">{{ $focus['user']->email }}@if($focus['user']->institute) · {{ $focus['user']->institute->name }}@endif</div>
                @if($focus['devices']->isNotEmpty())
                    <div class="la-devices">
                        @foreach($focus['devices'] as $dv)
                            <span><i class="{{ $deviceIcon[$dv->device] ?? 'ti ti-device-desktop' }}"></i> {{ trim($dv->label, ' ·') }} <b>{{ $dv->total }}</b></span>
                        @endforeach
                    </div>
                @endif
            </div>
            <dl class="la-person-facts">
                <div><dt>Last sign-in</dt><dd>{{ $focus['last'] ? $focus['last']->created_at->diffForHumans() : 'Never' }}</dd></div>
                <div><dt>Sign-ins (30 days)</dt><dd>{{ $focus['logins30'] }}</dd></div>
                <div><dt>Failed / blocked</dt><dd class="{{ $focus['problems30'] ? 'text-danger' : '' }}">{{ $focus['problems30'] }}</dd></div>
                <div><dt>IP addresses</dt><dd>{{ $focus['ips'] }}</dd></div>
            </dl>
            <button type="button" class="btn-close" wire:click="$set('filterUser', '')" aria-label="Show everyone"></button>
        </div>
    @endif

    <div class="mon-panel">
        {{-- Filters --}}
        <div class="mon-filters">
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <div class="la-tabs" role="tablist" aria-label="Where">
                    <button type="button" class="{{ $filterPanel === '' ? 'is-active' : '' }}" wire:click="$set('filterPanel', '')">Everywhere</button>
                    @foreach(LoginLog::PANELS as $key => [$label, $icon])
                        <button type="button" class="{{ $filterPanel === $key ? 'is-active' : '' }}" wire:click="$set('filterPanel', '{{ $key }}')"><i class="{{ $icon }}"></i> {{ $label }}</button>
                    @endforeach
                </div>
                <div class="mon-search flex-grow-1">
                    <i class="ti ti-search"></i>
                    <input type="search" class="form-control" placeholder="Search name, email, IP address or reason" wire:model.debounce.400ms="search" aria-label="Search">
                </div>
            </div>
            <div class="mon-filter-grid">
                <select class="form-select form-select-sm" wire:model="filterEvent" aria-label="Result">
                    <option value="">Any result</option>
                    @foreach(LoginLog::EVENTS as $key => [$label])<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    <option value="problems">Failed or blocked</option>
                </select>
                <select class="form-select form-select-sm" wire:model="filterWho" aria-label="Who">
                    <option value="">Everyone</option>
                    @foreach(LA::WHO as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
                <select class="form-select form-select-sm" wire:model="filterInstitute" aria-label="Institute">
                    <option value="">All institutes</option>
                    <option value="central">No institute (Super Admin, unknown)</option>
                    @foreach($institutes as $inst)<option value="{{ $inst->id }}">{{ $inst->name }}</option>@endforeach
                </select>
                <div class="mon-dates">
                    <input type="date" class="form-control form-control-sm" wire:model="dateFrom" aria-label="From date" max="{{ $dateTo ?: today()->toDateString() }}">
                    <span class="text-muted small">to</span>
                    <input type="date" class="form-control form-control-sm" wire:model="dateTo" aria-label="To date" min="{{ $dateFrom }}" max="{{ today()->toDateString() }}">
                </div>
            </div>
            <div class="mon-quick">
                <span class="small text-muted me-1">Quick:</span>
                <button type="button" class="mon-pill" wire:click="range('today')">Today</button>
                <button type="button" class="mon-pill" wire:click="range('7d')">Last 7 days</button>
                <button type="button" class="mon-pill" wire:click="range('30d')">Last 30 days</button>
                <span class="ms-auto small text-muted">{{ number_format($logs->total()) }} {{ Str::plural('entry', $logs->total()) }}</span>
                @if($hasFilters)<button type="button" class="btn btn-sm btn-light" wire:click="clearFilters"><i class="ti ti-x me-1"></i> Clear filters</button>@endif
            </div>
        </div>

        {{-- Entries --}}
        <div class="table-responsive position-relative">
            <div class="mon-loading" wire:loading.delay.flex wire:target="search, filterPanel, filterEvent, filterWho, filterInstitute, filterUser, dateFrom, dateTo, range, clearFilters, person, lookFor, gotoPage, nextPage, previousPage">
                <span class="spinner-border spinner-border-sm text-secondary"></span>
            </div>
            <table class="table align-middle mb-0 mon-table">
                <thead>
                    <tr>
                        <th style="width: 100px;">Time</th>
                        <th>Person</th>
                        <th>Where</th>
                        <th>Result</th>
                        <th>Device</th>
                        <th>IP address</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        @php
                            $day = $log->created_at->copy()->startOfDay();
                            [$eventLabel, $eventIcon, $eventTone] = LoginLog::EVENTS[$log->event] ?? [ucfirst($log->event), 'ti ti-point', 'slate'];
                            [$panelLabel, $panelIcon, $panelTone] = LoginLog::PANELS[$log->panel] ?? [ucfirst($log->panel), 'ti ti-point', 'slate'];
                            [$roleLabel, $roleTone] = LA::roleOf($log->user);
                        @endphp
                        @if(!$lastDay || !$lastDay->equalTo($day))
                            @php $lastDay = $day; @endphp
                            <tr class="mon-day"><td colspan="6"><i class="ti ti-calendar"></i> {{ $dayLabel($day) }}</td></tr>
                        @endif
                        <tr wire:key="la-{{ $log->id }}" class="{{ in_array($log->event, ['failed', 'blocked'], true) ? 'la-row-bad' : '' }}">
                            <td class="text-nowrap">
                                <div class="fw-semibold">{{ $log->created_at->format('h:i A') }}</div>
                                <div class="mon-sub">{{ $log->created_at->diffForHumans(null, true, true) }} ago</div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2 min-w-0">
                                    <span class="mon-avatar {{ $log->user ? '' : 'is-system' }}">@if($log->user){{ $initials($log->user->name) }}@else<i class="ti ti-user-question"></i>@endif</span>
                                    <div class="min-w-0">
                                        @if($log->user)
                                            <button type="button" class="la-name text-truncate" wire:click="person({{ $log->user_id }})" title="Show only {{ $log->user->name }}">{{ $log->user->name }}</button>
                                        @else
                                            <div class="fw-medium text-muted">Unknown account</div>
                                        @endif
                                        <div class="mon-sub text-truncate" style="max-width: 240px;">{{ $log->email ?: '—' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-nowrap">
                                <span class="la-where mon-tone-{{ $panelTone }}"><i class="{{ $panelIcon }}"></i> {{ $panelLabel }}</span>
                                <div class="mon-sub mt-1">{{ $roleLabel }}@if($log->institute) · {{ Str::limit($log->institute->name, 24) }}@endif</div>
                            </td>
                            <td>
                                <span class="mon-chip mon-tone-{{ $eventTone }}"><i class="{{ $eventIcon }}"></i> {{ $eventLabel }}</span>
                                @if($log->reason)<div class="mon-sub mt-1 la-reason" title="{{ $log->reason }}">{{ $log->reason }}</div>@endif
                            </td>
                            <td class="text-nowrap" title="{{ $log->user_agent }}">
                                @if($log->browser || $log->platform)
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="{{ $deviceIcon[$log->device] ?? 'ti ti-device-desktop' }} la-device-icon"></i>
                                        <div><div>{{ $log->browser ?: 'Browser' }}</div><div class="mon-sub">{{ $log->platform ?: ucfirst($log->device ?? '') }}</div></div>
                                    </div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @if($log->ip)<button type="button" class="la-ip mon-mono" wire:click="lookFor(@js($log->ip))" title="Show everything from this IP">{{ $log->ip }}</button>@else<span class="text-muted">—</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="mon-empty"><i class="ti ti-login"></i><div class="fw-semibold">{{ $hasFilters ? 'No entries match these filters' : 'No sign-ins recorded yet' }}</div>
                            @if($hasFilters)<button type="button" class="btn btn-sm btn-light mt-2" wire:click="clearFilters">Clear filters</button>@else<div class="small mt-1">New sign-ins appear here as soon as someone signs in.</div>@endif</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())<div class="mon-foot">{{ $logs->links() }}</div>@endif
    </div>

    @include('livewire.admin.monitoring.styles')
    <style>
        .la-ui .la-dot { width: 8px; height: 8px; border-radius: 50%; background: #22C55E; box-shadow: 0 0 0 3px rgba(34, 197, 94, .2); }
        .la-ui .la-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .la-ui .la-kpi { display: flex; gap: 12px; align-items: flex-start; padding: 14px 16px; background: #fff; border: 1px solid var(--mon-border); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); text-align: left; min-width: 0; }
        .la-ui .la-kpi-btn { cursor: pointer; transition: border-color .15s, box-shadow .15s; }
        .la-ui .la-kpi-btn:hover { border-color: #FDBA8C; box-shadow: 0 4px 12px rgba(16, 24, 40, .08); }
        .la-ui .la-kpi.is-alert { border-color: #FECACA; background: linear-gradient(135deg, #FEF2F2 0%, #fff 70%); }
        .la-ui .la-kpi-icon { width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 20px; background: var(--tone-soft); color: var(--tone); }
        .la-ui .la-kpi > div { display: flex; flex-direction: column; }
        .la-ui .la-kpi-label { font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--mon-muted); }
        .la-ui .la-kpi strong { font-size: 24px; line-height: 1.2; color: var(--mon-ink); }
        .la-ui .la-kpi small { font-size: 12px; color: var(--mon-muted); }

        .la-ui .la-row { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 12px; }
        .la-ui .la-card { padding: 14px 16px; display: flex; flex-direction: column; }
        .la-ui .la-card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
        .la-ui .la-card-head h6 { font-weight: 700; color: var(--mon-ink); }
        .la-ui .la-legend { display: flex; gap: 12px; font-size: 12px; color: var(--mon-muted); }
        .la-ui .la-sw { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 3px; vertical-align: -1px; }
        .la-ui .la-sw-ok, .la-ui .la-bar-ok { background: #22C55E; }
        .la-ui .la-sw-bad, .la-ui .la-bar-bad { background: #F87171; }
        .la-ui .la-chart { display: grid; grid-template-columns: repeat(14, minmax(0, 1fr)); gap: 6px; align-items: end; height: 170px; padding-top: 4px; }
        .la-ui .la-bar-col { display: flex; flex-direction: column; align-items: center; height: 100%; min-width: 0; }
        .la-ui .la-bar { flex: 1; width: 100%; max-width: 26px; display: flex; flex-direction: column-reverse; background: #F3F4F6; border-radius: 6px; overflow: hidden; }
        .la-ui .la-bar span { display: block; width: 100%; }
        .la-ui .la-bar-num { font-size: 11px; font-weight: 600; color: #374151; min-height: 16px; }
        .la-ui .la-bar-day { margin-top: 4px; font-size: 11px; color: var(--mon-muted); white-space: nowrap; }
        .la-ui .la-bar-col.is-today .la-bar-day { color: var(--mon-accent); font-weight: 700; }
        .la-ui .la-bar-col.is-today .la-bar { box-shadow: 0 0 0 2px #FED7AA; }

        .la-ui .la-watch { display: flex; align-items: center; gap: 10px; width: 100%; border: 0; border-bottom: 1px solid var(--mon-soft); background: none; padding: 8px 2px; font-size: 13px; color: var(--mon-ink); min-width: 0; }
        .la-ui .la-watch:last-child { border-bottom: 0; }
        .la-ui .la-watch:hover { background: #FFFBF7; }
        .la-ui .la-watch-icon { width: 30px; height: 30px; border-radius: 9px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; background: #FEE2E2; color: #B91C1C; font-size: 15px; }
        .la-ui .la-watch-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 16px 8px; color: #15803D; }
        .la-ui .la-watch-empty > i { font-size: 34px; margin-bottom: 4px; }

        .la-ui .la-person { position: relative; display: flex; gap: 14px; align-items: center; flex-wrap: wrap; padding: 16px 48px 16px 18px; background: linear-gradient(90deg, #FFF7F2 0%, #fff 60%); border: 1px solid #FED7AA; border-radius: 14px; }
        .la-ui .la-person .btn-close { position: absolute; top: 14px; right: 14px; }
        .la-ui .la-avatar-lg { width: 48px; height: 48px; border-radius: 14px; font-size: 16px; }
        .la-ui .la-person-name { font-size: 16px; color: var(--mon-ink); }
        .la-ui .la-devices { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
        .la-ui .la-devices span { font-size: 12px; padding: 2px 9px; border-radius: 999px; background: #fff; border: 1px solid var(--mon-border); color: #374151; }
        .la-ui .la-person-facts { display: grid; grid-template-columns: repeat(4, auto); gap: 4px 22px; margin: 0; }
        .la-ui .la-person-facts dt { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--mon-muted); }
        .la-ui .la-person-facts dd { margin: 0; font-size: 15px; font-weight: 700; color: var(--mon-ink); white-space: nowrap; }

        .la-ui .la-tabs { display: inline-flex; padding: 4px; border-radius: 12px; background: #F3F4F6; gap: 2px; flex-wrap: wrap; }
        .la-ui .la-tabs button { border: 0; background: transparent; padding: 6px 12px; border-radius: 9px; font-size: 13px; font-weight: 600; color: var(--mon-muted); display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; }
        .la-ui .la-tabs button.is-active { background: #fff; color: var(--mon-ink); box-shadow: 0 1px 3px rgba(16, 24, 40, .12); }
        .la-ui .la-name { border: 0; background: none; padding: 0; font-weight: 500; color: var(--mon-ink); max-width: 240px; display: block; text-align: left; }
        .la-ui .la-name:hover { color: var(--mon-accent); text-decoration: underline; }
        .la-ui .la-where { display: inline-flex; align-items: center; gap: 5px; font-size: 12.5px; font-weight: 600; color: var(--tone); }
        .la-ui .la-reason { max-width: 260px; white-space: normal; }
        .la-ui .la-device-icon { font-size: 18px; color: #6B7280; }
        .la-ui .la-ip { border: 0; background: #F3F4F6; border-radius: 6px; padding: 2px 8px; }
        .la-ui .la-ip:hover { background: #FFEDD5; color: #C2410C; }
        .la-ui .la-row-bad td:first-child { box-shadow: inset 3px 0 0 #F87171; }

        @media (max-width: 1199.98px) {
            .la-ui .la-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .la-ui .la-row { grid-template-columns: minmax(0, 1fr); }
        }
        @media (max-width: 575.98px) {
            .la-ui .la-kpis { grid-template-columns: minmax(0, 1fr); }
            .la-ui .la-chart { gap: 3px; height: 140px; }
            .la-ui .la-bar-num { font-size: 9.5px; }
            .la-ui .la-bar-day { font-size: 9.5px; }
            .la-ui .la-person-facts { grid-template-columns: repeat(2, auto); width: 100%; }
            .la-ui .la-tabs { width: 100%; }
        }
    </style>
</div>
