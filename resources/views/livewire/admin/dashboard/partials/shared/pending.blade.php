{{-- "Waiting for you": one collapsible list of every pending step the user should know about.
     - every role: their own steps (DashboardService::pendingActions) — shown as "You"
     - Super Admin / Institute Admin: every pending step in their institute(s) with the role and people
       responsible (DashboardService::pendingOverview); their own steps first
     Params: pending, overview (optional), showInstitute (optional) --}}
@php
    $me = auth()->user();
    $roleSlug = $me->getRoleNames()->first();
    $roleName = \Illuminate\Support\Str::before($me->role_display_name, ' (');
    $items = collect($pending);
    $hasOverview = !empty($overview);

    // unified rows
    if ($hasOverview) {
        $rows = collect($overview['rows'])->map(fn ($r) => $r + ['users_list' => $r['users']]);
    } else {
        $rows = $items->filter(fn ($i) => $i['count'] > 0)->map(fn ($i) => $i + [
            'institute' => null, 'roles' => [$me->role_display_name], 'role_slugs' => [$roleSlug],
            'users_list' => collect([$me]), 'missing' => false, 'mine' => true,
        ]);
    }
    $rows = $rows->sortBy(fn ($r) => $r['mine'] ? 0 : 1)->values();   // your steps first
    $mineCount = $rows->where('mine', true)->sum('count');
    $othersCount = $rows->where('mine', false)->sum('count');
    $total = $mineCount + $othersCount;
    $unassigned = $rows->where('missing', true)->count();
    $waitingKeys = $rows->pluck('key')->unique();
    $clearItems = $items->filter(fn ($i) => !$waitingKeys->contains($i['key']));   // steps with nothing waiting
    // header chips: one per pending action (institutes added up), your steps first
    $chips = $rows->groupBy('key')->map(fn ($g) => [
        'key' => $g->first()['key'], 'label' => $g->first()['label'], 'icon' => $g->first()['icon'], 'tone' => $g->first()['tone'],
        'step' => $g->first()['step'], 'url' => $g->first()['url'], 'count' => $g->sum('count'),
        'mine' => $g->contains('mine', true), 'missing' => $g->contains('missing', true),
    ])->sortBy(fn ($c) => $c['mine'] ? 0 : 1)->values();
    $roleTone = ['institute-admin' => 'indigo', 'accounts' => 'green', 'training-manager' => 'sky', 'bic' => 'slate', 'examination-manager' => 'violet', 'hot' => 'amber', 'faculty' => 'teal', 'super-admin' => 'red'];
    $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $showInstitute = $showInstitute ?? false;
@endphp

@if($items->isNotEmpty() || $rows->isNotEmpty())
    <div class="wf {{ $total ? 'wf-has-work' : 'wf-clear' }} mb-3" id="waitingForYou" data-mine="{{ $mineCount }}">
        {{-- Header: click to show / hide --}}
        <div class="wf-head" data-wf-toggle>
            <span class="wf-bell"><i class="ti {{ $total ? 'ti-bell-ringing' : 'ti-circle-check' }}"></i></span>
            <span class="wf-title">Waiting for you <span class="wf-role">· your next steps as {{ $roleName }}</span></span>
            @if($total)
                <span class="wf-chips">
                    @foreach($chips->take(5) as $c)
                        <a href="{{ $c['url'] }}" class="wf-chip pa-tone-{{ $c['tone'] }} {{ $c['mine'] ? 'is-mine' : '' }}" title="{{ $c['mine'] ? 'Your step' : 'Waiting with others' }}: {{ $c['step'] }}">
                            <i class="{{ $c['icon'] }}"></i>{{ $c['label'] }}<span class="wf-chip-count">{{ $c['count'] }}</span>
                            <span class="wf-blink {{ $c['mine'] || $c['missing'] ? 'is-red' : '' }}" aria-hidden="true"></span>
                        </a>
                    @endforeach
                    @if($chips->count() > 5)<span class="wf-chip-more">+{{ $chips->count() - 5 }} more</span>@endif
                </span>
            @else
                <span class="wf-badge">All clear</span>
            @endif
            @if($total)
                <span class="wf-split">
                    <span class="wf-split-mine" title="Steps you can take">Yours <b>{{ $mineCount }}</b></span>
                    @if($hasOverview)<span class="wf-split-others" title="Steps waiting with other staff">With others <b>{{ $othersCount }}</b></span>@endif
                </span>
            @endif
            @if($unassigned)<span class="wf-warn" title="Pending steps with no active user who can act"><i class="ti ti-user-off"></i> {{ $unassigned }} unassigned</span>@endif
            <button type="button" class="wf-chevron" aria-expanded="true" aria-controls="wfBody" data-wf-button><span class="wf-chevron-label" data-wf-label>Hide</span> <i class="ti ti-chevron-up"></i></button>
        </div>

        {{-- Body (smooth height animation) --}}
        <div class="wf-collapse" id="wfBody">
            <div class="wf-inner">
                @if($rows->isNotEmpty())
                    <div class="wf-cols">
                        <span>Pending action</span><span class="text-center">Waiting</span><span>Next step</span><span>{{ $hasOverview ? 'Who acts next' : 'Responsible' }}</span><span></span>
                    </div>
                    <div class="wf-rows">
                        @foreach($rows as $r)
                            <div class="wf-row pa-tone-{{ $r['tone'] }} {{ $r['mine'] ? 'is-mine' : '' }} {{ $r['missing'] ? 'is-missing' : '' }}" data-pending="{{ $r['key'] }}" data-overview="{{ $r['key'] }}">
                                <div class="wf-action">
                                    <span class="wf-icon"><i class="{{ $r['icon'] }}"></i></span>
                                    <div class="min-w-0">
                                        <div class="wf-label">{{ $r['label'] }}</div>
                                        <div class="wf-sub">
                                            {{ $r['group'] }}
                                            @if($showInstitute && $r['institute'])· <span class="wf-inst" title="{{ $r['institute']->name }}">{{ $r['institute']->code }}</span> <span class="wf-inst-name">{{ $r['institute']->name }}</span>@endif
                                        </div>
                                    </div>
                                </div>
                                <div class="wf-count-cell"><span class="wf-count">{{ $r['count'] }}<span class="wf-blink" aria-hidden="true"></span></span><span class="wf-hint">{{ $r['hint'] }}</span></div>
                                <div class="wf-step"><i class="ti ti-arrow-forward-up"></i> {{ $r['step'] }}</div>
                                <div class="wf-who">
                                    @foreach($r['roles'] as $i => $roleLabel)
                                        <span class="wf-role-chip tone-{{ $roleTone[$r['role_slugs'][$i] ?? ''] ?? 'slate' }}">{{ \Illuminate\Support\Str::before($roleLabel, ' (') }}</span>
                                    @endforeach
                                    @if($r['missing'])
                                        <span class="wf-person is-missing"><i class="ti ti-alert-triangle"></i> No active user — add one</span>
                                    @else
                                        @foreach($r['users_list']->take(2) as $u)
                                            <span class="wf-person {{ $u->id === $me->id ? 'is-me' : '' }}" title="{{ $u->name }} · {{ $u->role_display_name }}"><span class="wf-person-av">{{ $initials($u->name) }}</span>{{ $u->id === $me->id ? 'You' : $u->name }}</span>
                                        @endforeach
                                        @if($r['users_list']->count() > 2)<span class="wf-more">+{{ $r['users_list']->count() - 2 }}</span>@endif
                                    @endif
                                </div>
                                <div class="wf-go-cell">
                                    @if($r['missing'])
                                        <a href="{{ route('admin.users') }}" class="wf-go is-warn">Add user <i class="ti ti-arrow-right"></i></a>
                                    @else
                                        <a href="{{ $r['url'] }}" class="wf-go {{ $r['mine'] ? 'is-primary' : '' }}">{{ $r['mine'] ? 'Do it' : 'Open' }} <i class="ti ti-arrow-right"></i></a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="wf-empty"><i class="ti ti-circle-check"></i> Nothing is waiting{{ $hasOverview ? ' with anyone' : ' for you' }} right now.</div>
                @endif

                @if($clearItems->isNotEmpty())
                    <div class="wf-clear-line">
                        <span class="wf-clear-title"><i class="ti ti-circle-check"></i> All clear:</span>
                        @foreach($clearItems as $it)
                            <a href="{{ $it['url'] }}" class="wf-clear-item" data-pending="{{ $it['key'] }}" title="{{ $it['group'] }} — {{ $it['step'] }}">{{ $it['label'] }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        (function () {
            const box = document.getElementById('waitingForYou');
            if (!box) { return; }
            const head = box.querySelector('[data-wf-toggle]');
            const btn = box.querySelector('[data-wf-button]');
            const label = box.querySelector('[data-wf-label]');
            const key = 'camp.waitingForYou.collapsed';
            const set = (collapsed) => {
                box.classList.toggle('is-collapsed', collapsed);
                btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                label.textContent = collapsed ? 'Show' : 'Hide';
            };
            let stored = null;
            try { stored = localStorage.getItem(key); } catch (e) {}
            // remembered choice; always open again when something is waiting for this user
            set(stored === '1' && box.dataset.mine === '0');
            // the Show/Hide button, or any empty part of the header (chips keep opening their queue)
            head.addEventListener('click', (e) => {
                if (e.target.closest('a')) { return; }
                const collapsed = !box.classList.contains('is-collapsed');
                set(collapsed);
                try { localStorage.setItem(key, collapsed ? '1' : '0'); } catch (e) {}
            });
        })();
    </script>

    <style>
        .wf { background: #fff; border: 1px solid #E5E7EB; border-left: 4px solid #22C55E; border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); overflow: hidden; }
        .wf.wf-has-work { border-left-color: #E70D0D; }
        .wf-head { width: 100%; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 11px 16px; border: 0; background: #fff; text-align: left; cursor: pointer; }
        .wf-head:hover { background: #FCFCFD; }
        .wf-bell { width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 18px; background: #DCFCE7; color: #15803D; }
        .wf-has-work .wf-bell { background: #FEE2E2; color: #DC2626; animation: wfRing 2.4s ease-in-out infinite; }
        @keyframes wfRing { 0%, 80%, 100% { transform: rotate(0); } 84% { transform: rotate(14deg); } 88% { transform: rotate(-12deg); } 92% { transform: rotate(8deg); } 96% { transform: rotate(-4deg); } }
        .wf-title { font-size: 14.5px; font-weight: 700; color: #111827; }
        .wf-role { font-weight: 500; color: #6B7280; font-size: 12.5px; }
        .wf-badge { position: relative; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; background: #DCFCE7; color: #15803D; white-space: nowrap; }
        .wf-has-work .wf-badge { background: #E70D0D; color: #fff; }
        .wf-split { display: inline-flex; border: 1px solid #E5E7EB; border-radius: 999px; overflow: hidden; font-size: 12px; white-space: nowrap; }
        .wf-split > span { padding: 2px 10px; color: #6B7280; }
        .wf-split b { color: #111827; margin-left: 2px; }
        .wf-split-mine { background: #FFF1E7; }
        .wf-split-mine b { color: #C2410C !important; }
        .wf-split-others { border-left: 1px solid #E5E7EB; }
        .wf-warn { display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 700; background: #FEF2F2; color: #B91C1C; border: 1px solid #FECACA; white-space: nowrap; }
        .wf-chevron { margin-left: auto; border: 0; background: none; padding: 4px 6px; border-radius: 8px; display: inline-flex; align-items: center; gap: 4px; font-size: 12.5px; font-weight: 600; color: #6B7280; white-space: nowrap; }
        .wf-chevron i { font-size: 16px; transition: transform .35s ease; }
        .wf.is-collapsed .wf-chevron i { transform: rotate(180deg); }
        .wf-head:hover .wf-chevron { color: #F26522; }

        /* smooth show / hide */
        .wf-collapse { display: grid; grid-template-rows: 1fr; transition: grid-template-rows .4s cubic-bezier(.2, .7, .2, 1), opacity .3s ease; opacity: 1; }
        .wf.is-collapsed .wf-collapse { grid-template-rows: 0fr; opacity: 0; }
        .wf-inner { overflow: hidden; min-height: 0; border-top: 1px solid #F1F2F4; }
        .wf.is-collapsed .wf-inner { border-top-color: transparent; }

        /* unified list */
        .wf-cols, .wf-row { display: grid; grid-template-columns: minmax(0, 1.5fr) 96px minmax(0, 1.4fr) minmax(0, 1.6fr) 84px; gap: 14px; align-items: center; }
        .wf-cols { padding: 8px 16px; background: #F9FAFB; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #6B7280; }
        .wf-rows { max-height: 330px; overflow-y: auto; }
        .wf-row { --tone: #6B7280; --tone-soft: #F3F4F6; padding: 10px 16px; border-bottom: 1px solid #F3F4F6; border-left: 3px solid transparent; transition: background .15s; }
        .wf-row:last-child { border-bottom: 0; }
        .wf-row:hover { background: #FFFBF7; }
        .wf-row.is-mine { border-left-color: #F26522; background: linear-gradient(90deg, #FFF7F2, #fff 40%); }
        .wf-row.is-missing { background: #FEF2F2; border-left-color: #DC2626; }
        .wf-action { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .wf-icon { position: relative; width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 17px; background: var(--tone-soft); color: var(--tone); }
        .wf-label { font-size: 13.5px; font-weight: 700; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .wf-sub { font-size: 11.5px; color: #6B7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .wf-inst { padding: 0 5px; border-radius: 4px; background: #F3F4F6; color: #374151; font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 10.5px; }
        .wf-inst-name { color: #9CA3AF; }
        .wf-count-cell { display: flex; flex-direction: column; align-items: center; gap: 1px; min-width: 0; }
        .wf-count { position: relative; min-width: 32px; padding: 2px 9px; border-radius: 999px; text-align: center; font-weight: 800; font-size: 14px; background: var(--tone-soft); color: var(--tone);
            box-shadow: 0 0 0 1px var(--tone-soft); animation: wfGlow 2.2s ease-in-out infinite; }
        /* blinking dot: red for your steps, amber for steps with others */
        .wf-blink { position: absolute; top: -3px; right: -4px; width: 10px; height: 10px; border-radius: 50%; background: #F59E0B; border: 2px solid #fff;
            box-shadow: 0 0 0 0 rgba(245, 158, 11, .6); animation: wfBlink 1.6s ease-out infinite; }
        .wf-row.is-mine .wf-blink, .wf-row.is-missing .wf-blink, .wf-blink.is-red { background: #EF4444; animation-name: wfBlinkRed; }
        .wf-badge .wf-blink { top: -4px; right: -5px; }
        /* header action chips (stay visible when the list is hidden) */
        .wf-chips { display: inline-flex; flex-wrap: wrap; gap: 6px; align-items: center; }
        .wf-chip { position: relative; display: inline-flex; align-items: center; gap: 6px; padding: 4px 5px 4px 10px; border-radius: 999px; border: 1px solid var(--tone); background: var(--tone-soft); color: var(--tone);
            font-size: 12.5px; font-weight: 600; text-decoration: none; white-space: nowrap; transition: transform .12s, box-shadow .12s; }
        .wf-chip:hover { color: var(--tone); transform: translateY(-1px); box-shadow: 0 4px 10px rgba(16, 24, 40, .08); }
        .wf-chip i { font-size: 15px; }
        .wf-chip-count { min-width: 22px; padding: 0 7px; border-radius: 999px; background: var(--tone); color: #fff; text-align: center; font-weight: 700; }
        .wf-chip .wf-blink { top: -3px; right: -3px; }
        .wf-chip.is-mine { box-shadow: 0 0 0 2px rgba(242, 101, 34, .18); }
        .wf-chip-more { font-size: 12px; font-weight: 600; color: #6B7280; }
        @keyframes wfBlink { 0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, .6); transform: scale(1); } 50% { transform: scale(1.15); } 70% { box-shadow: 0 0 0 8px rgba(245, 158, 11, 0); } 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); transform: scale(1); } }
        @keyframes wfBlinkRed { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, .65); transform: scale(1); } 50% { transform: scale(1.15); } 70% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); transform: scale(1); } }
        @keyframes wfGlow { 0%, 100% { box-shadow: 0 0 0 1px var(--tone-soft); } 50% { box-shadow: 0 0 0 4px var(--tone-soft); } }
        .wf-hint { font-size: 10.5px; color: #9CA3AF; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
        .wf-step { font-size: 12.5px; color: #374151; min-width: 0; }
        .wf-step i { color: #F26522; }
        .wf-who { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; min-width: 0; }
        .wf-role-chip { padding: 2px 8px; border-radius: 999px; font-size: 11.5px; font-weight: 700; background: var(--tone-soft); color: var(--tone); white-space: nowrap; }
        .wf-person { display: inline-flex; align-items: center; gap: 5px; padding: 1px 9px 1px 1px; border-radius: 999px; background: #F3F4F6; font-size: 12px; font-weight: 600; color: #374151; white-space: nowrap; max-width: 170px; overflow: hidden; text-overflow: ellipsis; }
        .wf-person-av { width: 20px; height: 20px; border-radius: 50%; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 9.5px; font-weight: 700; background: #E0E7FF; color: #4338CA; }
        .wf-person.is-me { background: #FFF1E7; color: #C2410C; }
        .wf-person.is-me .wf-person-av { background: #F26522; color: #fff; }
        .wf-person.is-missing { padding: 2px 9px; background: #FEE2E2; color: #B91C1C; }
        .wf-more { font-size: 12px; color: #6B7280; }
        .wf-go-cell { text-align: right; }
        .wf-go { display: inline-flex; align-items: center; gap: 4px; padding: 5px 11px; border-radius: 8px; font-size: 12.5px; font-weight: 700; text-decoration: none; color: #374151; border: 1px solid #E5E7EB; background: #fff; white-space: nowrap; transition: all .15s; }
        .wf-go:hover { border-color: #FDBA8C; color: #F26522; }
        .wf-go.is-primary { background: #F26522; border-color: #F26522; color: #fff; box-shadow: 0 3px 10px rgba(242, 101, 34, .25); }
        .wf-go.is-primary:hover { background: #E0551A; color: #fff; }
        .wf-go.is-warn { border-color: #FCA5A5; color: #B91C1C; }
        .wf-empty { padding: 14px 16px; font-size: 13px; color: #15803D; }
        .wf-clear-line { display: flex; align-items: center; gap: 6px 10px; flex-wrap: wrap; padding: 8px 16px; border-top: 1px dashed #E5E7EB; background: #FCFCFD; font-size: 12px; }
        .wf-clear-title { color: #15803D; font-weight: 700; }
        .wf-clear-item { color: #9CA3AF; text-decoration: none; }
        .wf-clear-item:hover { color: #F26522; }
        .wf-clear-item + .wf-clear-item::before { content: '·'; margin-right: 10px; color: #D1D5DB; }
        .wf-row .tone-indigo { --tone: #4338CA; --tone-soft: #EEF2FF; } .wf-row .tone-green { --tone: #15803D; --tone-soft: #DCFCE7; }
        .wf-row .tone-sky { --tone: #0369A1; --tone-soft: #E0F2FE; } .wf-row .tone-violet { --tone: #6D28D9; --tone-soft: #EDE9FE; }
        .wf-row .tone-amber { --tone: #B45309; --tone-soft: #FEF3C7; } .wf-row .tone-teal { --tone: #0F766E; --tone-soft: #CCFBF1; }
        .wf-row .tone-slate { --tone: #475569; --tone-soft: #F1F5F9; } .wf-row .tone-red { --tone: #B91C1C; --tone-soft: #FEE2E2; }
        .pa-tone-warning { --tone: #B45309; --tone-soft: #FFFBEB; } .pa-tone-primary { --tone: #EA580C; --tone-soft: #FFF7ED; }
        .pa-tone-info { --tone: #1D4ED8; --tone-soft: #EFF6FF; } .pa-tone-danger { --tone: #B91C1C; --tone-soft: #FEF2F2; }
        .pa-tone-secondary { --tone: #334155; --tone-soft: #F1F5F9; }
        @media (max-width: 1199.98px) {
            .wf-cols { display: none; }
            .wf-row { grid-template-columns: minmax(0, 1fr) auto; gap: 8px 12px; }
            .wf-count-cell { grid-column: 2; grid-row: 1; }
            .wf-step, .wf-who { grid-column: 1 / 3; }
            .wf-go-cell { grid-column: 1 / 3; text-align: left; }
        }
        @media (prefers-reduced-motion: reduce) { .wf-collapse, .wf-chevron i { transition: none; } .wf-has-work .wf-bell, .wf-blink, .wf-count { animation: none; } }
    </style>
@endif
