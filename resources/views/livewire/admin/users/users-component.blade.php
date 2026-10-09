@php
    $roleTones = [
        'super-admin' => 'red', 'institute-admin' => 'indigo', 'accounts' => 'green', 'training-manager' => 'sky',
        'bic' => 'slate', 'examination-manager' => 'violet', 'hot' => 'amber', 'faculty' => 'teal',
    ];
    $sortIcon = fn ($field) => $sortField === $field ? ($sortDirection === 'asc' ? 'ti-sort-ascending' : 'ti-sort-descending') : 'ti-arrows-sort';
    // login activity in separate buckets (online ⊂ this week), for the segmented bar
    $total = max(1, $stats['total']);
    $inactive = $stats['total'] - $stats['active'];
    $activity = [
        ['online', 'Online now', $stats['online'], 'last 15 minutes', 'ti ti-wifi', 'green'],
        ['7d', 'This week', max(0, $stats['week'] - $stats['online']), 'last 7 days', 'ti ti-login', 'blue'],
        ['earlier', 'Earlier', max(0, $stats['total'] - $stats['week'] - $stats['never']), 'more than 7 days ago', 'ti ti-history', 'slate'],
        ['never', 'Never', $stats['never'], 'not logged in yet', 'ti ti-clock-off', 'amber'],
    ];
@endphp
<div class="content us-ui">
    {{-- Header --}}
    <div class="us-hero mb-3">
        <div class="min-w-0">
            <h2 class="mb-1 fw-bold">{{ $scopeInstituteName ? 'Institute Users' : 'Users' }}</h2>
            <nav>
                <ol class="breadcrumb mb-1 sl-crumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Users &amp; Permissions</li>
                    @if($scopeInstituteName)
                        <li class="breadcrumb-item"><a href="{{ route('admin.institutes') }}">Institutes</a></li>
                        <li class="breadcrumb-item active">{{ $scopeInstituteName }}</li>
                    @else
                        <li class="breadcrumb-item active">Users</li>
                    @endif
                </ol>
            </nav>
            <div class="text-muted small">
                {{ $scopeInstituteName ? 'Staff logins of this institute.' : ($isSuperAdmin ? 'Staff logins of every institute and central users. Students are managed under Students.' : 'Staff logins of your institute. Students are managed under Students.') }}
            </div>
        </div>
        @can('users.create')
            <button type="button" wire:click="openModal" class="us-hero-cta"><i class="ti ti-user-plus"></i> Add user</button>
        @endcan
    </div>

    {{-- Overview strip: accounts + login activity (click a segment to filter) --}}
    <div class="us-overview mb-3">
        <div class="us-ov-accounts">
            <span class="us-ov-icon"><i class="ti ti-users"></i></span>
            <div class="flex-grow-1 min-w-0">
                <div class="us-ov-label">User accounts</div>
                <div class="us-ov-total">{{ number_format($stats['total']) }}</div>
                <div class="us-ov-split" title="{{ $stats['active'] }} active · {{ $inactive }} inactive">
                    <span class="is-active" style="width: {{ round($stats['active'] / $total * 100, 1) }}%"></span>
                </div>
                <div class="us-ov-legend">
                    <button type="button" class="{{ $filterStatus === 'active' ? 'is-on' : '' }}" wire:click="$set('filterStatus', '{{ $filterStatus === 'active' ? '' : 'active' }}')"><i class="us-dot us-dot-green"></i> {{ $stats['active'] }} active</button>
                    <button type="button" class="{{ $filterStatus === 'inactive' ? 'is-on' : '' }}" wire:click="$set('filterStatus', '{{ $filterStatus === 'inactive' ? '' : 'inactive' }}')"><i class="us-dot us-dot-grey"></i> {{ $inactive }} inactive</button>
                </div>
            </div>
        </div>
        <div class="us-ov-activity">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="us-ov-label"><i class="ti ti-activity"></i> Login activity</span>
                @if($filterLogin)<button type="button" class="us-ov-reset" wire:click="$set('filterLogin', '')"><i class="ti ti-x"></i> Show all</button>@endif
            </div>
            <div class="us-ov-bar">
                @foreach($activity as [$key, $label, $count, $hint, $icon, $tone])
                    @if($count > 0)<span class="us-seg us-seg-{{ $tone }} {{ $filterLogin && $filterLogin !== $key ? 'is-dim' : '' }}" style="width: {{ round($count / $total * 100, 1) }}%" title="{{ $label }}: {{ $count }}"></span>@endif
                @endforeach
            </div>
            <div class="us-ov-tiles">
                @foreach($activity as [$key, $label, $count, $hint, $icon, $tone])
                    <button type="button" class="us-ov-tile us-ov-{{ $tone }} {{ $filterLogin === $key ? 'is-active' : '' }}" wire:click="$set('filterLogin', '{{ $filterLogin === $key ? '' : $key }}')">
                        <span class="us-ov-tile-icon"><i class="{{ $icon }}"></i></span>
                        <span class="min-w-0">
                            <span class="us-ov-tile-count">{{ number_format($count) }}</span>
                            <span class="us-ov-tile-label">{{ $label }}</span>
                            <span class="us-ov-tile-hint">{{ $hint }}</span>
                        </span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="us-panel">
        {{-- Role chips --}}
        @if($roleChips->isNotEmpty())
            <div class="us-roles">
                <button type="button" class="us-role-chip {{ $filterRole === '' ? 'is-active' : '' }}" wire:click="$set('filterRole', '')">
                    All roles <span class="us-role-count">{{ $stats['total'] }}</span>
                </button>
                @foreach($roleChips as $slug => $chip)
                    <button type="button" class="us-role-chip us-tone-{{ $roleTones[$slug] ?? 'slate' }} {{ $filterRole === $slug ? 'is-active' : '' }}"
                            wire:click="$set('filterRole', '{{ $filterRole === $slug ? '' : $slug }}')">
                        {{ $chip['label'] }} <span class="us-role-count">{{ $chip['count'] }}</span>
                    </button>
                @endforeach
            </div>
        @endif

        {{-- Filters --}}
        <div class="us-filters">
            <div class="us-search">
                <i class="ti ti-search"></i>
                <input type="search" class="form-control form-control-sm" placeholder="Name, email, phone or employee code" wire:model.debounce.400ms="search" aria-label="Search users">
            </div>
            @if($filterInstitutes->isNotEmpty())
                <select class="form-select form-select-sm" wire:model="filterInstitute" aria-label="Institute">
                    <option value="">All institutes</option>
                    <option value="central">Central users (no institute)</option>
                    @foreach($filterInstitutes as $inst)<option value="{{ $inst->id }}">{{ $inst->name }}</option>@endforeach
                </select>
            @endif
            <select class="form-select form-select-sm" wire:model="filterRole" aria-label="Role">
                <option value="">All roles</option>
                @foreach(collect(config('camp.roles'))->except('student') as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach
            </select>
            <select class="form-select form-select-sm" wire:model="filterStatus" aria-label="Status">
                <option value="">Any status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <select class="form-select form-select-sm" wire:model="filterLogin" aria-label="Login activity">
                <option value="">Any login activity</option>
                @foreach($loginFilters as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
            </select>
            @if($hasFilters)
                <button type="button" class="btn btn-sm btn-light" wire:click="clearFilters"><i class="ti ti-x me-1"></i> Clear</button>
            @endif
            <span class="ms-auto small text-muted">{{ number_format($users->total()) }} {{ \Illuminate\Support\Str::plural('user', $users->total()) }}</span>
        </div>

        {{-- List --}}
        <div class="table-responsive position-relative">
            <div class="us-loading" wire:loading.delay.flex wire:target="search, filterInstitute, filterRole, filterStatus, filterLogin, clearFilters, sortBy, toggleStatus, gotoPage, nextPage, previousPage">
                <span class="spinner-border spinner-border-sm text-secondary"></span>
            </div>
            <table class="table align-middle mb-0 us-table">
                <thead>
                    <tr>
                        <th><a href="javascript:void(0);" wire:click="sortBy('name')">User <i class="ti {{ $sortIcon('name') }}"></i></a></th>
                        <th>Role</th>
                        @if($isSuperAdmin && !$scopeInstituteId)<th>Institute</th>@endif
                        <th>Status</th>
                        <th><a href="javascript:void(0);" wire:click="sortBy('last_login_at')">Last login <i class="ti {{ $sortIcon('last_login_at') }}"></i></a></th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        @php
                            $slug = $user->roles->pluck('name')->first();
                            $manage = $canManageRow($user);
                            $self = $user->id === auth()->id();
                            $initials = collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
                        @endphp
                        <tr wire:key="user-{{ $user->id }}" class="{{ $user->status ? '' : 'us-row-off' }}">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="us-avatar us-tone-{{ $roleTones[$slug] ?? 'slate' }}">
                                        {{ $initials ?: '?' }}
                                        @if($user->is_online)<span class="us-online" title="Online"></span>@endif
                                    </span>
                                    <div class="min-w-0">
                                        <div class="fw-semibold text-truncate">{{ $user->name }} @if($self)<span class="us-you">You</span>@endif</div>
                                        <div class="us-sub text-truncate"><i class="ti ti-mail"></i> {{ $user->email }}</div>
                                        @if($user->phone || $user->employee_code)
                                            <div class="us-sub">
                                                @if($user->phone)<span class="me-2"><i class="ti ti-phone"></i> {{ $user->phone }}</span>@endif
                                                @if($user->employee_code)<span class="us-emp">{{ $user->employee_code }}</span>@endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td><span class="us-role us-tone-{{ $roleTones[$slug] ?? 'slate' }}">{{ $user->role_display_name }}</span></td>
                            @if($isSuperAdmin && !$scopeInstituteId)
                                <td>
                                    @if($user->institute)
                                        <div class="small fw-medium text-truncate" style="max-width: 220px;">{{ $user->institute->name }}</div>
                                        @if($user->institute->code)<span class="us-inst-code">{{ $user->institute->code }}</span>@endif
                                    @else
                                        <span class="us-central"><i class="ti ti-world"></i> Central</span>
                                    @endif
                                </td>
                            @endif
                            <td>
                                @if($manage && !$self)
                                    @can('users.update')
                                        <div class="form-check form-switch m-0" title="{{ $user->status ? 'Click to deactivate' : 'Click to activate' }}">
                                            <input class="form-check-input" type="checkbox" role="switch" id="userSwitch{{ $user->id }}" @checked($user->status) wire:click="toggleStatus({{ $user->id }})">
                                            <label class="form-check-label small {{ $user->status ? 'text-success' : 'text-muted' }}" for="userSwitch{{ $user->id }}">{{ $user->status ? 'Active' : 'Inactive' }}</label>
                                        </div>
                                    @else
                                        <span class="us-status {{ $user->status ? 'is-on' : '' }}">{{ $user->status ? 'Active' : 'Inactive' }}</span>
                                    @endcan
                                @else
                                    <span class="us-status {{ $user->status ? 'is-on' : '' }}">{{ $user->status ? 'Active' : 'Inactive' }}</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @if($user->last_login_at)
                                    <div class="small fw-medium {{ $user->is_online ? 'text-success' : '' }}">{{ $user->is_online ? 'Online now' : $user->last_login_at->diffForHumans() }}</div>
                                    <div class="us-sub">{{ $user->last_login_at->format('d M Y, h:i A') }}@if($user->last_login_ip) · {{ $user->last_login_ip }}@endif</div>
                                @else
                                    <span class="us-never"><i class="ti ti-clock-off"></i> Never logged in</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if($manage)
                                    @can('users.update')
                                        <button type="button" class="btn btn-sm btn-outline-warning" wire:click="edit({{ $user->id }})" title="Edit"><i class="ti ti-edit"></i></button>
                                    @endcan
                                    @can('users.delete')
                                        @unless($self)
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="confirmDelete({{ $user->id }})" title="Delete"><i class="ti ti-trash"></i></button>
                                        @endunless
                                    @endcan
                                @else
                                    <span class="small text-muted" title="Managed by the Super Admin"><i class="ti ti-lock"></i></span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="us-empty">
                                <i class="ti ti-users-minus"></i>
                                <div class="fw-semibold">{{ $hasFilters ? 'No users match these filters' : 'No users yet' }}</div>
                                @if($hasFilters)<button type="button" class="btn btn-sm btn-light mt-2" wire:click="clearFilters">Clear filters</button>@endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())<div class="us-foot">{{ $users->links() }}</div>@endif
    </div>

    <style>
        .us-ui { --us-border: #E5E7EB; --us-soft: #F1F2F4; --us-ink: #111827; --us-muted: #6B7280; --us-accent: #F26522; }
        .us-ui .min-w-0 { min-width: 0; }
        .us-ui .us-hero { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; padding: 18px 22px; border-radius: 14px; border: 1px solid var(--us-border);
            background: radial-gradient(circle at 100% 0, rgba(242, 101, 34, .12), transparent 45%), linear-gradient(135deg, #FFFFFF 0%, #FFF8F3 100%); }
        .us-ui .us-hero h2 { font-size: 22px; color: var(--us-ink); }
        .us-ui .us-hero-cta { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 999px; border: 0; background: var(--us-accent); color: #fff; font-size: 14px; font-weight: 500; box-shadow: 0 6px 16px rgba(242, 101, 34, .3); transition: transform .15s; }
        .us-ui .us-hero-cta:hover { transform: translateY(-1px); }

        /* overview strip (this page's own summary design) */
        .us-ui .us-overview { display: grid; grid-template-columns: minmax(240px, 300px) 1fr; background: #fff; border: 1px solid var(--us-border); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); overflow: hidden; }
        .us-ui .us-ov-accounts { display: flex; gap: 14px; align-items: flex-start; padding: 18px 20px; color: #fff;
            background: radial-gradient(circle at 0 100%, rgba(242, 101, 34, .45), transparent 60%), linear-gradient(145deg, #1E293B 0%, #0F172A 100%); }
        .us-ui .us-ov-icon { width: 44px; height: 44px; border-radius: 12px; background: rgba(255, 255, 255, .1); border: 1px solid rgba(255, 255, 255, .15); display: inline-flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
        .us-ui .us-ov-label { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: #94A3B8; }
        .us-ui .us-ov-activity .us-ov-label { color: var(--us-muted); }
        .us-ui .us-ov-total { font-size: 34px; font-weight: 800; line-height: 1.1; margin: 2px 0 10px; }
        .us-ui .us-ov-split { height: 6px; border-radius: 999px; background: rgba(255, 255, 255, .18); overflow: hidden; }
        .us-ui .us-ov-split span { display: block; height: 100%; border-radius: 999px; background: #4ADE80; }
        .us-ui .us-ov-legend { display: flex; gap: 6px; margin-top: 10px; flex-wrap: wrap; }
        .us-ui .us-ov-legend button { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 999px; border: 1px solid rgba(255, 255, 255, .18); background: transparent; color: #E2E8F0; font-size: 12px; }
        .us-ui .us-ov-legend button.is-on, .us-ui .us-ov-legend button:hover { background: rgba(255, 255, 255, .14); }
        .us-ui .us-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; }
        .us-ui .us-dot-green { background: #4ADE80; } .us-ui .us-dot-grey { background: #94A3B8; }

        .us-ui .us-ov-activity { padding: 16px 20px; }
        .us-ui .us-ov-reset { border: 0; background: #FEF0E7; color: #C2410C; border-radius: 999px; padding: 2px 10px; font-size: 12px; font-weight: 600; }
        .us-ui .us-ov-bar { display: flex; gap: 3px; height: 10px; border-radius: 999px; background: #F1F5F9; overflow: hidden; margin-bottom: 12px; }
        .us-ui .us-seg { height: 100%; transition: opacity .15s; }
        .us-ui .us-seg.is-dim { opacity: .25; }
        .us-ui .us-seg-green { background: #22C55E; } .us-ui .us-seg-blue { background: #3B82F6; }
        .us-ui .us-seg-slate { background: #94A3B8; } .us-ui .us-seg-amber { background: #F59E0B; }
        .us-ui .us-ov-tiles { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
        .us-ui .us-ov-tile { --c: #475569; --c-soft: #F1F5F9; display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: 1px solid transparent; border-radius: 10px; background: transparent; text-align: left; transition: background .15s, border-color .15s; }
        .us-ui .us-ov-tile:hover { background: #F8FAFC; }
        .us-ui .us-ov-tile.is-active { background: var(--c-soft); border-color: var(--c); }
        .us-ui .us-ov-tile-icon { width: 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; background: var(--c-soft); color: var(--c); }
        .us-ui .us-ov-tile-count { display: block; font-size: 20px; font-weight: 700; color: var(--us-ink); line-height: 1.1; }
        .us-ui .us-ov-tile-label { display: block; font-size: 12.5px; font-weight: 600; color: var(--c); }
        .us-ui .us-ov-tile-hint { display: block; font-size: 11px; color: var(--us-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .us-ui .us-ov-green { --c: #15803D; --c-soft: #DCFCE7; } .us-ui .us-ov-blue { --c: #1D4ED8; --c-soft: #DBEAFE; }
        .us-ui .us-ov-slate { --c: #475569; --c-soft: #F1F5F9; } .us-ui .us-ov-amber { --c: #B45309; --c-soft: #FEF3C7; }
        @media (max-width: 991.98px) { .us-ui .us-overview { grid-template-columns: 1fr; } }
        @media (max-width: 575.98px) { .us-ui .us-ov-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

        .us-ui .us-panel { background: #fff; border: 1px solid var(--us-border); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); overflow: hidden; }
        .us-ui .us-roles { display: flex; flex-wrap: wrap; gap: 6px; padding: 12px 16px; border-bottom: 1px solid var(--us-soft); }
        .us-ui .us-role-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px; border: 1px solid var(--us-border); background: #fff; font-size: 12.5px; font-weight: 500; color: #374151; transition: all .15s; }
        .us-ui .us-role-chip:hover { border-color: #FDBA8C; }
        .us-ui .us-role-chip.is-active { background: var(--us-accent); border-color: var(--us-accent); color: #fff; }
        .us-ui .us-role-count { padding: 0 7px; border-radius: 999px; background: rgba(0, 0, 0, .06); font-size: 11.5px; font-weight: 700; }
        .us-ui .us-role-chip.is-active .us-role-count { background: rgba(255, 255, 255, .25); }

        .us-ui .us-filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--us-soft); background: #FCFCFD; }
        .us-ui .us-filters .form-select { width: auto; min-width: 150px; max-width: 230px; border-radius: 8px; }
        .us-ui .us-search { position: relative; flex: 1 1 240px; max-width: 320px; }
        .us-ui .us-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
        .us-ui .us-search input { padding-left: 32px; border-radius: 8px; }

        .us-ui .us-table thead th { background: #F9FAFB; font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--us-muted); padding: 10px 16px; white-space: nowrap; border-bottom: 1px solid var(--us-border); }
        .us-ui .us-table thead th a { color: inherit; }
        .us-ui .us-table td { padding: 11px 16px; border-color: var(--us-soft); font-size: 13.5px; }
        .us-ui .us-table tbody tr:hover td { background: #FFFBF7; }
        .us-ui .us-row-off td { opacity: .62; }
        .us-ui .us-avatar { position: relative; width: 40px; height: 40px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; flex-shrink: 0; background: var(--tone-soft); color: var(--tone); }
        .us-ui .us-online { position: absolute; right: -2px; bottom: -2px; width: 12px; height: 12px; border-radius: 50%; background: #22C55E; border: 2px solid #fff; }
        .us-ui .us-sub { font-size: 12px; color: var(--us-muted); margin-top: 1px; }
        .us-ui .us-you { margin-left: 4px; padding: 0 6px; border-radius: 6px; background: #FEF0E7; color: #C2410C; font-size: 10.5px; font-weight: 700; vertical-align: middle; }
        .us-ui .us-emp { padding: 0 6px; border-radius: 5px; background: #F3F4F6; color: #374151; font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 11px; }
        .us-ui .us-role { display: inline-flex; align-items: center; padding: 3px 11px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; background: var(--tone-soft); color: var(--tone); }
        .us-ui .us-inst-code { padding: 0 6px; border-radius: 5px; background: #F3F4F6; color: #4B5563; font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 11px; }
        .us-ui .us-central { display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px; border-radius: 999px; background: #FEF2F2; color: #B91C1C; font-size: 12px; font-weight: 600; }
        .us-ui .us-status { padding: 2px 10px; border-radius: 999px; background: #F3F4F6; color: var(--us-muted); font-size: 12px; font-weight: 600; }
        .us-ui .us-status.is-on { background: #DCFCE7; color: #15803D; }
        .us-ui .us-never { display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px; border-radius: 999px; background: #FEF3C7; color: #B45309; font-size: 12px; font-weight: 600; }
        .us-ui .us-empty { text-align: center; color: var(--us-muted); padding: 48px 12px !important; }
        .us-ui .us-empty > i { font-size: 36px; color: #D1D5DB; display: block; margin-bottom: 6px; }
        .us-ui .us-loading { display: none; position: absolute; inset: 0; z-index: 2; background: rgba(255, 255, 255, .6); align-items: center; justify-content: center; }
        .us-ui .us-foot { padding: 12px 16px; border-top: 1px solid var(--us-soft); }
        .us-ui .us-foot .pagination { margin: 0; }

        /* role colours */
        .us-ui .us-tone-red { --tone: #B91C1C; --tone-soft: #FEE2E2; } .us-ui .us-tone-indigo { --tone: #4338CA; --tone-soft: #E0E7FF; }
        .us-ui .us-tone-green { --tone: #15803D; --tone-soft: #DCFCE7; } .us-ui .us-tone-sky { --tone: #0369A1; --tone-soft: #E0F2FE; }
        .us-ui .us-tone-slate { --tone: #475569; --tone-soft: #F1F5F9; } .us-ui .us-tone-violet { --tone: #6D28D9; --tone-soft: #EDE9FE; }
        .us-ui .us-tone-amber { --tone: #B45309; --tone-soft: #FEF3C7; } .us-ui .us-tone-teal { --tone: #0F766E; --tone-soft: #CCFBF1; }
        .us-ui .us-role-chip[class*="us-tone-"]:not(.is-active) { color: var(--tone); }
    </style>

    <!-- Add/Edit Modal -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <div class="modal-header bg-primary bg-opacity-10 border-bottom">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md bg-soft-primary bg-opacity-10 rounded-circle me-3 d-flex align-items-center justify-content-center">
                            <i class="ti ti-user-plus text-primary fs-14"></i>
                        </div>
                        <div>
                            <h4 class="modal-title fw-semibold mb-0">{{ $isEdit ? 'Edit User' : 'Add New User' }}</h4>
                            <small class="text-muted">{{ $isEdit ? 'Update the user details below' : 'Create a login and assign a role' }}</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" wire:click="closeModal"></button>
                </div>

                <div class="modal-body p-4">
                    <!-- Section: Role & Institute -->
                    <div class="card border-0 bg-light bg-opacity-50 mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <i class="ti ti-shield text-primary me-2 fs-14"></i>
                                <h6 class="fw-semibold mb-0">Role & Institute</h6>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Role <span class="text-danger">*</span></label>
                                    <select class="form-select @error('role') is-invalid @enderror" wire:model="role" {{ $editingSelf ? 'disabled' : '' }}>
                                        <option value="">Select role</option>
                                        @foreach($roleOptions as $slug => $label)
                                            <option value="{{ $slug }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    @if($editingSelf)
                                        <small class="text-muted">You cannot change your own role.</small>
                                    @endif
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">
                                        Institute @if($role !== 'super-admin')<span class="text-danger">*</span>@endif
                                    </label>
                                    @if($role === 'super-admin')
                                        <input type="text" class="form-control bg-light" value="All institutes (central user)" readonly>
                                    @elseif($isSuperAdmin && !$editingSelf)
                                        <select class="form-select @error('institute_id') is-invalid @enderror" wire:model="institute_id">
                                            <option value="">Select institute</option>
                                            @foreach($instituteOptions as $institute)
                                                <option value="{{ $institute->id }}">{{ $institute->name }} ({{ $institute->code }})</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" class="form-control bg-light" readonly
                                               value="{{ optional($instituteOptions->firstWhere('id', $institute_id))->name ?? '—' }}">
                                    @endif
                                    @error('institute_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Personal Details -->
                    <div class="card border-0 bg-light bg-opacity-50 mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <i class="ti ti-user text-primary me-2 fs-14"></i>
                                <h6 class="fw-semibold mb-0">User Details</h6>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model.defer="name" placeholder="Full name">
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Employee Code</label>
                                    <input type="text" class="form-control @error('employee_code') is-invalid @enderror" wire:model.defer="employee_code" placeholder="Optional">
                                    @error('employee_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Email (login) <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror" wire:model.defer="email" placeholder="user@example.com">
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Phone</label>
                                    <input type="tel" class="form-control @error('phone') is-invalid @enderror" wire:model.defer="phone" placeholder="Phone number">
                                    @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Password -->
                    <div class="card border-0 bg-light bg-opacity-50 mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <i class="ti ti-key text-primary me-2 fs-14"></i>
                                <h6 class="fw-semibold mb-0">Password</h6>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Password @unless($isEdit)<span class="text-danger">*</span>@endunless</label>
                                    <input type="password" class="form-control @error('password') is-invalid @enderror" wire:model.defer="password"
                                           placeholder="{{ $isEdit ? 'Leave blank to keep current password' : 'Minimum 8 characters' }}" autocomplete="new-password">
                                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Confirm Password</label>
                                    <input type="password" class="form-control" wire:model.defer="password_confirmation" autocomplete="new-password">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Status -->
                    <div class="card border-0 bg-light bg-opacity-50">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <i class="ti ti-toggle-right text-primary me-2 fs-14"></i>
                                    <div>
                                        <h6 class="fw-semibold mb-0">Status</h6>
                                        <small class="text-muted">Inactive users cannot log in</small>
                                    </div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="userStatusSwitch"
                                           wire:model="status" value="1" {{ $editingSelf ? 'disabled' : '' }}>
                                    <label class="form-check-label fw-medium" for="userStatusSwitch">{{ $status ? 'Active' : 'Inactive' }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top">
                    <button class="btn btn-light px-4 me-2" wire:click="closeModal">
                        <i class="ti ti-x me-1 fs-16"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary px-4" wire:click="save" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">
                            <i class="ti ti-device-floppy me-1 fs-16"></i> {{ $isEdit ? 'Update User' : 'Save User' }}
                        </span>
                        <span wire:loading wire:target="save">
                            <span class="spinner-border spinner-border-sm me-1"></span> Saving...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="userDeleteModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <i class="ti ti-alert-triangle text-danger fs-1 mb-3"></i>
                    <h5>Confirm Delete</h5>
                    <p class="text-muted mb-4">Are you sure you want to delete this user? They will no longer be able to log in.</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-danger px-4" wire:click="delete">Yes, Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('livewire:load', function () {
    [
        { name: 'user', id: 'userModal' },
        { name: 'user-delete', id: 'userDeleteModal' },
    ].forEach(config => {
        window.addEventListener(`open-${config.name}-modal`, () => {
            bootstrap.Modal.getOrCreateInstance(document.getElementById(config.id)).show();
        });

        window.addEventListener(`close-${config.name}-modal`, () => {
            const instance = bootstrap.Modal.getInstance(document.getElementById(config.id));
            if (instance) {
                instance.hide();
            }
            document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
            document.body.classList.remove('modal-open');
        });
    });
});
</script>
