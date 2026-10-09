@php
    use App\Http\Livewire\Admin\Roles\RolesComponent as RC;

    $roleMeta = [
        'institute-admin' => ['indigo', 'ti ti-building', 'Runs the institute: courses, batches, students, document gate, staff'],
        'accounts' => ['green', 'ti ti-cash', 'Fee structure, payments and the fee gate'],
        'training-manager' => ['sky', 'ti ti-signature', 'Signs ER forms and ID cards; attendance (planned)'],
        'bic' => ['slate', 'ti ti-shield-half', 'Bypasses the exam dues block with a reason (planned)'],
        'examination-manager' => ['violet', 'ti ti-clipboard-check', 'Exams, results and promotion confirmation (planned)'],
        'hot' => ['amber', 'ti ti-file-certificate', 'DMS approvals and MoU alerts (planned)'],
        'faculty' => ['teal', 'ti ti-chalkboard', 'Attendance, lesson plans and marks entry (planned)'],
        'student' => ['rose', 'ti ti-school', 'Admissions portal only'],
    ];
    $groupIcons = [
        'Institutes' => 'ti ti-building-community', 'Institute Courses' => 'ti ti-books', 'Students' => 'ti ti-school',
        'Onboarding' => 'ti ti-progress-check', 'Fees & Payments' => 'ti ti-cash', 'Users' => 'ti ti-users',
        'Roles' => 'ti ti-shield-lock', 'Audit Trail' => 'ti ti-history', 'Notifications' => 'ti ti-bell',
        'Serial Numbers' => 'ti ti-123',
    ];
    $grantedCount = fn ($role) => collect($matrix[$role] ?? [])->filter()->count();
    $groupCount = fn ($role, $perms) => collect(array_keys($perms))->filter(fn ($p) => !empty($matrix[$role][RC::key($p)]))->count();
    $current = $roles->firstWhere('name', $selectedRole);
    [$tone, $icon, $about] = $roleMeta[$selectedRole] ?? ['slate', 'ti ti-user', ''];
@endphp
<div class="content rp-ui">
    {{-- Header --}}
    <div class="rp-head mb-3">
        <div class="min-w-0">
            <h2 class="mb-1 fw-bold">Roles &amp; Permissions</h2>
            <nav>
                <ol class="breadcrumb mb-1 sl-crumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Users &amp; Permissions</li>
                    <li class="breadcrumb-item active">Roles &amp; Permissions</li>
                </ol>
            </nav>
            <div class="text-muted small">Choose what each role can do. Changes apply to every user with that role after saving.</div>
        </div>
        <div class="rp-views" role="tablist">
            <button type="button" class="{{ $view === 'role' ? 'is-active' : '' }}" wire:click="setView('role')"><i class="ti ti-user-shield"></i> By role</button>
            <button type="button" class="{{ $view === 'overview' ? 'is-active' : '' }}" wire:click="setView('overview')"><i class="ti ti-layout-grid"></i> All roles</button>
        </div>
    </div>

    <div class="rp-super mb-3">
        <span class="rp-super-icon"><i class="ti ti-crown"></i></span>
        <div class="min-w-0">
            <strong>Super Admin</strong> always has every permission (incl. Master Data) and cannot be edited here.
            <span class="text-muted">{{ $superAdmins }} {{ \Illuminate\Support\Str::plural('user', $superAdmins) }} · {{ count($groups) }} permission groups · {{ $totalPermissions }} permissions</span>
        </div>
    </div>

    @if($view === 'role')
        <div class="rp-layout">
            {{-- Roles --}}
            <aside class="rp-roles">
                <div class="rp-roles-title">Roles</div>
                @foreach($roles as $role)
                    @php
                        [$rTone, $rIcon] = $roleMeta[$role->name] ?? ['slate', 'ti ti-user'];
                        $count = $grantedCount($role->name);
                        $pct = $totalPermissions ? round($count / $totalPermissions * 100) : 0;
                    @endphp
                    <button type="button" class="rp-role rp-tone-{{ $rTone }} {{ $selectedRole === $role->name ? 'is-active' : '' }}" wire:click="selectRole('{{ $role->name }}')" wire:key="role-{{ $role->name }}">
                        <span class="rp-role-icon"><i class="{{ $rIcon }}"></i></span>
                        <span class="min-w-0 flex-grow-1">
                            <span class="rp-role-name">{{ config("camp.roles.{$role->name}", $role->name) }}
                                @if($pending[$role->name])<span class="rp-dirty" title="Unsaved changes">{{ $pending[$role->name] }}</span>@endif
                            </span>
                            <span class="rp-role-meta">{{ $userCounts[$role->name] ?? 0 }} {{ \Illuminate\Support\Str::plural('user', $userCounts[$role->name] ?? 0) }} · {{ $count }}/{{ $totalPermissions }}</span>
                            <span class="rp-role-bar"><span style="width: {{ $pct }}%"></span></span>
                        </span>
                    </button>
                @endforeach
            </aside>

            {{-- Selected role --}}
            <section class="rp-main">
                @if($current)
                    <div class="rp-role-head rp-tone-{{ $tone }}">
                        <span class="rp-role-head-icon"><i class="{{ $icon }}"></i></span>
                        <div class="min-w-0 flex-grow-1">
                            <h4 class="mb-0">{{ config("camp.roles.{$selectedRole}", $selectedRole) }}</h4>
                            <div class="small text-muted">{{ $about }}</div>
                        </div>
                        <div class="rp-role-score">
                            <strong>{{ $grantedCount($selectedRole) }}</strong><span>/ {{ $totalPermissions }}</span>
                            <small>permissions</small>
                        </div>
                    </div>

                    <div class="rp-tools">
                        <div class="rp-search">
                            <i class="ti ti-search"></i>
                            <input type="search" class="form-control form-control-sm" placeholder="Search permissions or groups" wire:model.debounce.300ms="search" aria-label="Search permissions">
                        </div>
                        <button type="button" class="btn btn-sm btn-light border" wire:click="setAll(1)"><i class="ti ti-checks me-1"></i> Grant all</button>
                        <button type="button" class="btn btn-sm btn-light border" wire:click="setAll(0)"><i class="ti ti-square-off me-1"></i> Revoke all</button>
                        <span class="ms-auto d-inline-flex gap-1">
                            <button type="button" class="btn btn-sm btn-link text-decoration-none px-1" onclick="rpCollapse(true)">Expand all</button>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none px-1" onclick="rpCollapse(false)">Collapse all</button>
                        </span>
                    </div>

                    <div class="rp-groups">
                        @forelse($visibleGroups as $group => $perms)
                            @php
                                $all = $groups[$group];
                                $on = $groupCount($selectedRole, $all);
                                $state = $on === 0 ? 'none' : ($on === count($all) ? 'all' : 'some');
                                $gid = 'rpg-' . \Illuminate\Support\Str::slug($group);
                            @endphp
                            <div class="rp-group" wire:key="grp-{{ $selectedRole }}-{{ $gid }}">
                                <div class="rp-group-head" data-bs-toggle="collapse" data-bs-target="#{{ $gid }}" role="button" aria-expanded="true">
                                    <i class="ti ti-chevron-down rp-chev"></i>
                                    <span class="rp-group-icon"><i class="{{ $groupIcons[$group] ?? 'ti ti-folder' }}"></i></span>
                                    <span class="flex-grow-1 min-w-0">
                                        <span class="rp-group-name">{{ $group }}</span>
                                        <span class="rp-group-count rp-state-{{ $state }}">{{ $on }}/{{ count($all) }}</span>
                                    </span>
                                    <span class="form-check form-switch m-0" onclick="event.stopPropagation()" title="{{ $state === 'all' ? 'Revoke the whole group' : 'Grant the whole group' }}">
                                        <input class="form-check-input rp-group-switch {{ $state === 'some' ? 'is-partial' : '' }}" type="checkbox" role="switch" @checked($state === 'all') wire:click="toggleGroup('{{ $group }}')">
                                    </span>
                                </div>
                                <div id="{{ $gid }}" class="collapse show" wire:ignore.self>
                                    @foreach($perms as $permission => $label)
                                        @php $k = RC::key($permission); $granted = !empty($matrix[$selectedRole][$k]); @endphp
                                        <label class="rp-perm {{ $granted ? 'is-on' : '' }}" for="p-{{ $selectedRole }}-{{ $k }}" wire:key="perm-{{ $selectedRole }}-{{ $k }}">
                                            <span class="min-w-0">
                                                <span class="rp-perm-label">{{ $label }}</span>
                                                <code class="rp-perm-code">{{ $permission }}</code>
                                            </span>
                                            <span class="form-check form-switch m-0">
                                                <input class="form-check-input" type="checkbox" role="switch" id="p-{{ $selectedRole }}-{{ $k }}" wire:model="matrix.{{ $selectedRole }}.{{ $k }}">
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="rp-empty"><i class="ti ti-search-off"></i> No permission matches “{{ $search }}”.</div>
                        @endforelse
                    </div>
                @endif
            </section>
        </div>
    @else
        {{-- All roles: group-wise comparison --}}
        <div class="rp-overview">
            <div class="table-responsive">
                <table class="table align-middle mb-0 rp-ov-table">
                    <thead>
                        <tr>
                            <th class="rp-ov-first">Permission</th>
                            @foreach($roles as $role)
                                @php [$rTone, $rIcon] = $roleMeta[$role->name] ?? ['slate', 'ti ti-user']; @endphp
                                <th class="text-center">
                                    <button type="button" class="rp-ov-role rp-tone-{{ $rTone }}" wire:click="selectRole('{{ $role->name }}')" title="Open this role">
                                        <i class="{{ $rIcon }}"></i>
                                        <span>{{ \Illuminate\Support\Str::before(config("camp.roles.{$role->name}", $role->name), ' (') }}</span>
                                        <small>{{ $grantedCount($role->name) }}/{{ $totalPermissions }}</small>
                                    </button>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    @foreach($groups as $group => $perms)
                        @php $gid = 'rpo-' . \Illuminate\Support\Str::slug($group); @endphp
                        <tbody>
                            <tr class="rp-ov-group collapsed" data-bs-toggle="collapse" data-bs-target=".{{ $gid }}" role="button">
                                <td class="rp-ov-first"><i class="ti ti-chevron-down rp-chev"></i> <i class="{{ $groupIcons[$group] ?? 'ti ti-folder' }} text-muted"></i> {{ $group }}</td>
                                @foreach($roles as $role)
                                    @php $on = $groupCount($role->name, $perms); $state = $on === 0 ? 'none' : ($on === count($perms) ? 'all' : 'some'); @endphp
                                    <td class="text-center" onclick="event.stopPropagation()">
                                        <button type="button" class="rp-ov-pill rp-state-{{ $state }}" wire:click="toggleGroup('{{ $group }}', '{{ $role->name }}')" title="{{ $state === 'all' ? 'Revoke the group' : 'Grant the group' }}">{{ $on }}/{{ count($perms) }}</button>
                                    </td>
                                @endforeach
                            </tr>
                        </tbody>
                        <tbody class="collapse {{ $gid }}" wire:ignore.self>
                            @foreach($perms as $permission => $label)
                                <tr class="rp-ov-row">
                                    <td class="rp-ov-first"><span class="rp-perm-label">{{ $label }}</span> <code class="rp-perm-code">{{ $permission }}</code></td>
                                    @foreach($roles as $role)
                                        <td class="text-center"><input type="checkbox" class="form-check-input" wire:model="matrix.{{ $role->name }}.{{ RC::key($permission) }}"></td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
        </div>
    @endif

    {{-- Save bar --}}
    <div class="rp-savebar {{ $pendingTotal ? 'is-dirty' : '' }}">
        <span class="rp-savebar-text">
            @if($pendingTotal)
                <i class="ti ti-alert-circle"></i> {{ $pendingTotal }} unsaved {{ \Illuminate\Support\Str::plural('change', $pendingTotal) }}
                in {{ collect($pending)->filter()->count() }} {{ \Illuminate\Support\Str::plural('role', collect($pending)->filter()->count()) }}
            @else
                <i class="ti ti-circle-check"></i> All changes saved
            @endif
        </span>
        <span class="d-flex gap-2">
            @if($pendingTotal)<button type="button" class="btn btn-sm btn-light" wire:click="discard">Discard</button>@endif
            <button type="button" class="btn btn-sm btn-primary" wire:click="save" wire:loading.attr="disabled" @disabled(!$pendingTotal)>
                <span wire:loading.remove wire:target="save"><i class="ti ti-device-floppy me-1"></i> Save permissions</span>
                <span wire:loading wire:target="save"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
            </button>
        </span>
    </div>

    <style>
        .rp-ui { --rp-border: #E5E7EB; --rp-soft: #F1F2F4; --rp-ink: #111827; --rp-muted: #6B7280; --rp-accent: #F26522; padding-bottom: 80px; }
        .rp-ui .min-w-0 { min-width: 0; }
        .rp-ui .rp-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; padding-bottom: 14px; border-bottom: 1px dashed #E5E7EB; }
        .rp-ui .rp-head h2 { font-size: 22px; color: var(--rp-ink); }
        .rp-ui .rp-views { display: inline-flex; padding: 4px; border-radius: 12px; background: #F3F4F6; }
        .rp-ui .rp-views button { border: 0; background: transparent; padding: 7px 14px; border-radius: 9px; font-size: 13px; font-weight: 600; color: var(--rp-muted); }
        .rp-ui .rp-views button.is-active { background: #fff; color: var(--rp-ink); box-shadow: 0 1px 3px rgba(16, 24, 40, .12); }
        .rp-ui .rp-super { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-radius: 12px; background: #FFFBEB; border: 1px solid #FDE68A; font-size: 13px; }
        .rp-ui .rp-super-icon { width: 32px; height: 32px; border-radius: 50%; background: #FEF3C7; color: #B45309; display: inline-flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; }

        .rp-ui .rp-layout { display: grid; grid-template-columns: 280px 1fr; gap: 16px; align-items: start; }
        .rp-ui .rp-roles { position: sticky; top: 76px; display: flex; flex-direction: column; gap: 6px; padding: 12px; background: #fff; border: 1px solid var(--rp-border); border-radius: 14px; }
        .rp-ui .rp-roles-title { font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--rp-muted); padding: 2px 6px 6px; }
        .rp-ui .rp-role { display: flex; align-items: center; gap: 10px; padding: 9px 10px; border-radius: 10px; border: 1px solid transparent; background: transparent; text-align: left; transition: background .15s; }
        .rp-ui .rp-role:hover { background: #F9FAFB; }
        .rp-ui .rp-role.is-active { background: var(--tone-soft); border-color: var(--tone); }
        .rp-ui .rp-role-icon { width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; background: var(--tone-soft); color: var(--tone); }
        .rp-ui .rp-role.is-active .rp-role-icon { background: var(--tone); color: #fff; }
        .rp-ui .rp-role-name { display: flex; align-items: center; gap: 6px; font-size: 13.5px; font-weight: 600; color: var(--rp-ink); }
        .rp-ui .rp-role-meta { display: block; font-size: 11.5px; color: var(--rp-muted); }
        .rp-ui .rp-role-bar { display: block; height: 4px; margin-top: 5px; border-radius: 999px; background: #EEF0F3; overflow: hidden; }
        .rp-ui .rp-role-bar span { display: block; height: 100%; background: var(--tone); border-radius: 999px; }
        .rp-ui .rp-dirty { padding: 0 6px; border-radius: 999px; background: var(--rp-accent); color: #fff; font-size: 10.5px; font-weight: 700; }

        .rp-ui .rp-main { background: #fff; border: 1px solid var(--rp-border); border-radius: 14px; overflow: hidden; }
        .rp-ui .rp-role-head { display: flex; align-items: center; gap: 14px; padding: 16px 18px; background: linear-gradient(90deg, var(--tone-soft) 0%, #fff 70%); border-bottom: 1px solid var(--rp-soft); }
        .rp-ui .rp-role-head h4 { font-size: 17px; font-weight: 700; color: var(--rp-ink); }
        .rp-ui .rp-role-head-icon { width: 46px; height: 46px; border-radius: 12px; background: var(--tone); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
        .rp-ui .rp-role-score { text-align: right; line-height: 1.1; }
        .rp-ui .rp-role-score strong { font-size: 26px; color: var(--tone); }
        .rp-ui .rp-role-score span { font-size: 14px; color: var(--rp-muted); margin-left: 2px; }
        .rp-ui .rp-role-score small { display: block; font-size: 11px; color: var(--rp-muted); }
        .rp-ui .rp-tools { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 12px 18px; border-bottom: 1px solid var(--rp-soft); background: #FCFCFD; }
        .rp-ui .rp-search { position: relative; flex: 1 1 220px; max-width: 320px; }
        .rp-ui .rp-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
        .rp-ui .rp-search input { padding-left: 32px; border-radius: 8px; }

        .rp-ui .rp-groups { padding: 14px 18px 18px; display: grid; grid-template-columns: repeat(auto-fill, minmax(330px, 1fr)); gap: 12px; align-items: start; }
        .rp-ui .rp-group { border: 1px solid var(--rp-border); border-radius: 12px; overflow: hidden; }
        .rp-ui .rp-group-head { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: #F9FAFB; cursor: pointer; }
        .rp-ui .rp-chev { color: #9CA3AF; transition: transform .2s; }
        .rp-ui .rp-group-head.collapsed .rp-chev, .rp-ui .rp-ov-group.collapsed .rp-chev { transform: rotate(-90deg); }
        .rp-ui .rp-group-icon { width: 30px; height: 30px; border-radius: 8px; background: #fff; border: 1px solid var(--rp-border); display: inline-flex; align-items: center; justify-content: center; color: #4B5563; flex-shrink: 0; }
        .rp-ui .rp-group-name { font-size: 13.5px; font-weight: 700; color: var(--rp-ink); margin-right: 6px; }
        .rp-ui .rp-group-count, .rp-ui .rp-ov-pill { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 11.5px; font-weight: 700; }
        .rp-ui .rp-state-all { background: #DCFCE7; color: #15803D; } .rp-ui .rp-state-some { background: #FEF3C7; color: #B45309; } .rp-ui .rp-state-none { background: #F3F4F6; color: #9CA3AF; }
        .rp-ui .rp-group-switch.is-partial { background-color: #FCD34D; border-color: #FCD34D; }
        .rp-ui .rp-perm { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 9px 12px 9px 14px; margin: 0; border-top: 1px solid var(--rp-soft); cursor: pointer; transition: background .15s; }
        .rp-ui .rp-perm:hover { background: #FFFBF7; }
        .rp-ui .rp-perm.is-on { box-shadow: inset 3px 0 0 #22C55E; }
        .rp-ui .rp-perm-label { display: block; font-size: 13px; color: #1F2937; }
        .rp-ui .rp-perm-code { font-size: 11px; color: #9A3412; background: #FFF7ED; padding: 0 5px; border-radius: 4px; }
        .rp-ui .rp-empty { grid-column: 1 / -1; text-align: center; color: var(--rp-muted); padding: 36px 12px; }

        .rp-ui .rp-overview { background: #fff; border: 1px solid var(--rp-border); border-radius: 14px; overflow: hidden; }
        .rp-ui .rp-ov-table th, .rp-ui .rp-ov-table td { border-color: var(--rp-soft); padding: 9px 10px; }
        .rp-ui .rp-ov-table thead th { background: #F9FAFB; vertical-align: bottom; }
        .rp-ui .rp-ov-first { min-width: 260px; position: sticky; left: 0; background: inherit; z-index: 1; }
        .rp-ui .rp-ov-table thead .rp-ov-first { background: #F9FAFB; font-size: 11.5px; text-transform: uppercase; letter-spacing: .05em; color: var(--rp-muted); }
        .rp-ui .rp-ov-role { display: inline-flex; flex-direction: column; align-items: center; gap: 3px; border: 0; background: transparent; min-width: 92px; font-size: 12px; font-weight: 600; color: var(--rp-ink); }
        .rp-ui .rp-ov-role i { width: 32px; height: 32px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px; background: var(--tone-soft); color: var(--tone); }
        .rp-ui .rp-ov-role small { color: var(--rp-muted); font-weight: 500; }
        .rp-ui .rp-ov-group td { background: #fff; font-weight: 700; font-size: 13px; cursor: pointer; }
        .rp-ui .rp-ov-group:hover td { background: #FFFBF7; }
        .rp-ui .rp-ov-pill { border: 0; min-width: 46px; }
        .rp-ui .rp-ov-row td { background: #FCFCFD; font-size: 12.5px; }
        .rp-ui .rp-ov-row .rp-ov-first { padding-left: 42px; }

        .rp-ui .rp-savebar { position: fixed; right: 24px; bottom: 20px; z-index: 1030; display: flex; align-items: center; gap: 16px; padding: 10px 12px 10px 16px; border-radius: 14px; background: #fff; border: 1px solid var(--rp-border); box-shadow: 0 10px 30px rgba(16, 24, 40, .15); }
        .rp-ui .rp-savebar-text { font-size: 13px; font-weight: 600; color: #15803D; }
        .rp-ui .rp-savebar.is-dirty { background: #111827; border-color: #111827; }
        .rp-ui .rp-savebar.is-dirty .rp-savebar-text { color: #FDBA74; }

        /* role colours */
        .rp-ui .rp-tone-indigo { --tone: #4338CA; --tone-soft: #EEF2FF; } .rp-ui .rp-tone-green { --tone: #15803D; --tone-soft: #DCFCE7; }
        .rp-ui .rp-tone-sky { --tone: #0369A1; --tone-soft: #E0F2FE; } .rp-ui .rp-tone-slate { --tone: #475569; --tone-soft: #F1F5F9; }
        .rp-ui .rp-tone-violet { --tone: #6D28D9; --tone-soft: #EDE9FE; } .rp-ui .rp-tone-amber { --tone: #B45309; --tone-soft: #FEF3C7; }
        .rp-ui .rp-tone-teal { --tone: #0F766E; --tone-soft: #CCFBF1; } .rp-ui .rp-tone-rose { --tone: #BE123C; --tone-soft: #FFE4E6; }

        @media (max-width: 991.98px) {
            .rp-ui .rp-layout { grid-template-columns: 1fr; }
            .rp-ui .rp-roles { position: static; flex-direction: row; overflow-x: auto; }
            .rp-ui .rp-role { min-width: 210px; }
            .rp-ui .rp-roles-title { display: none; }
        }
        @media (max-width: 575.98px) {
            .rp-ui .rp-groups { grid-template-columns: 1fr; padding: 12px; }
            .rp-ui .rp-savebar { left: 12px; right: 12px; bottom: 12px; justify-content: space-between; }
        }
    </style>
</div>

<script>
function rpCollapse(open) {
    document.querySelectorAll('.rp-groups .collapse').forEach(function (el) {
        bootstrap.Collapse.getOrCreateInstance(el, { toggle: false })[open ? 'show' : 'hide']();
    });
    document.querySelectorAll('.rp-group-head').forEach(function (h) { h.classList.toggle('collapsed', !open); });
}
</script>
