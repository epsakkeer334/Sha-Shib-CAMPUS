<div class="content sc-ui">
    @php
        $fmt = fn ($v) => money_inr($v, false);
        $filtered = trim($search) !== '' || $batch || $semester || (!$s && ($institute || $course));
        $pageIds = $students->pluck('id')->map(fn ($id) => (string) $id)->all();
        $pageEligible = array_map('strval', $eligibleIds);
        $pageAllPicked = $pageEligible && !array_diff($pageEligible, $selected);
    @endphp

    <!-- Header -->
    <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
        <div class="my-auto mb-2">
            <h2 class="mb-1 fw-semibold">Assign Scholarship</h2>
            <nav><ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.scholarships') }}">Scholarships</a></li>
                <li class="breadcrumb-item active">Assign</li>
            </ol></nav>
        </div>
        <a href="{{ route('admin.scholarships') }}" class="btn btn-light border"><i class="ti ti-award me-1"></i> Manage scholarships</a>
    </div>

    <!-- Filters -->
    @php
        $activeFilters = collect([
            $isSuperAdmin && $institute ? 'institute' : null, $course ? 'course' : null, $batch ? 'batch' : null,
            $semester ? 'semester' : null, trim($search) !== '' ? 'search' : null,
        ])->filter()->count();
        $curInst = $isSuperAdmin && $institute ? $institutes->firstWhere('id', (int) $institute) : null;
        $curCourse = $course ? $courses->firstWhere('id', (int) $course) : null;
        $curBatch = $batch ? $batches->firstWhere('id', (int) $batch) : null;
        $valueOf = fn ($o) => $o->type === 'percentage' ? rtrim(rtrim(number_format((float) $o->value, 2), '0'), '.') . '%' : '₹' . number_format((float) $o->value);
    @endphp
    <div class="sc-panel ff mb-3">
        <div class="ff-row">
            {{-- search --}}
            <div class="ff-search">
                <i class="ti ti-search"></i>
                <input id="fSearch" type="search" placeholder="Search name, ER no., email, phone" wire:model.debounce.400ms="search" aria-label="Search students">
                <span wire:loading.delay wire:target="search" class="spinner-border text-primary"></span>
            </div>

            {{-- scholarship --}}
            <div class="dropdown ff-dd">
                <button type="button" class="ff-pill ff-pill-schol {{ $s ? 'is-set' : '' }}" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="true">
                    <i class="ti ti-award"></i>
                    <span class="ff-pill-text">{{ $s ? $s->name : 'Scholarship' }}</span>
                    @if($s)<span class="ff-pill-badge">{{ $valueOf($s) }}</span>@endif
                    <i class="ti ti-chevron-down ff-caret"></i>
                </button>
                <div class="dropdown-menu ff-menu ff-menu-wide">
                    <div class="ff-menu-head">Scholarship to assign <small>{{ $scholarships->count() }} active</small></div>
                    <button type="button" class="ff-opt {{ !$s ? 'is-on' : '' }}" wire:click="pickScholarship(null)">
                        <span class="ff-ico"><i class="ti ti-receipt-2"></i></span>
                        <span class="ff-opt-main"><b>None — just show fees</b><small>See every student's fees</small></span>
                        <i class="ti ti-check ff-check"></i>
                    </button>
                    <div class="ff-menu-list">
                        @foreach($scholarships as $opt)
                            <button type="button" class="ff-opt {{ $s && $s->id === $opt->id ? 'is-on' : '' }}" wire:click="pickScholarship({{ $opt->id }})">
                                <span class="ff-ico ff-ico-val {{ $opt->type === 'percentage' ? 'is-pct' : 'is-fixed' }}">{{ $valueOf($opt) }}</span>
                                <span class="ff-opt-main"><b>{{ $opt->name }}</b><small>{{ $opt->code }} · {{ optional($opt->course)->code }} · {{ $opt->periods_label }}</small></span>
                                <i class="ti ti-check ff-check"></i>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
            @if($s)<button type="button" class="ff-x" wire:click="pickScholarship(null)" title="Clear scholarship" aria-label="Clear scholarship"><i class="ti ti-x"></i></button>@endif

            <span class="ff-sep"></span>

            {{-- institute (Super Admin) --}}
            @if($isSuperAdmin)
                <div class="dropdown ff-dd">
                    <button type="button" class="ff-pill {{ $curInst ? 'is-on' : '' }}" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="ti ti-building"></i><span class="ff-pill-text">{{ $curInst ? $curInst->name : 'Institute' }}</span><i class="ti ti-chevron-down ff-caret"></i>
                    </button>
                    <div class="dropdown-menu ff-menu">
                        <div class="ff-menu-head">Institute</div>
                        <button type="button" class="ff-opt {{ !$curInst ? 'is-on' : '' }}" wire:click="pickInstitute(null)"><span class="ff-opt-main"><b>All institutes</b></span><i class="ti ti-check ff-check"></i></button>
                        <div class="ff-menu-list">
                            @foreach($institutes as $i)
                                <button type="button" class="ff-opt {{ $curInst && $curInst->id === $i->id ? 'is-on' : '' }}" wire:click="pickInstitute({{ $i->id }})">
                                    <span class="ff-opt-main"><b>{{ $i->name }}</b><small>{{ $i->code }}</small></span><i class="ti ti-check ff-check"></i>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- course --}}
            <div class="dropdown ff-dd">
                <button type="button" class="ff-pill {{ $curCourse ? 'is-on' : '' }}" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ti ti-books"></i><span class="ff-pill-text">{{ $curCourse ? $curCourse->code : 'Course' }}</span><i class="ti ti-chevron-down ff-caret"></i>
                </button>
                <div class="dropdown-menu ff-menu">
                    <div class="ff-menu-head">Course</div>
                    <button type="button" class="ff-opt {{ !$curCourse ? 'is-on' : '' }}" wire:click="pickCourse(null)"><span class="ff-opt-main"><b>All courses</b></span><i class="ti ti-check ff-check"></i></button>
                    <div class="ff-menu-list">
                        @foreach($courses as $c)
                            <button type="button" class="ff-opt {{ $curCourse && $curCourse->id === $c->id ? 'is-on' : '' }}" wire:click="pickCourse({{ $c->id }})">
                                <span class="ff-tag">{{ $c->code }}</span><span class="ff-opt-main"><b>{{ $c->name }}</b></span><i class="ti ti-check ff-check"></i>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- batch --}}
            <div class="dropdown ff-dd">
                <button type="button" class="ff-pill {{ $curBatch ? 'is-on' : '' }}" data-bs-toggle="dropdown" aria-expanded="false" @if(!$course) disabled title="Choose a course first" @endif>
                    <i class="ti ti-users-group"></i><span class="ff-pill-text">{{ $curBatch ? $curBatch->code : 'Batch' }}</span><i class="ti ti-chevron-down ff-caret"></i>
                </button>
                @if($course)
                    <div class="dropdown-menu ff-menu">
                        <div class="ff-menu-head">Batch <small>{{ $batches->count() }} in this course</small></div>
                        <button type="button" class="ff-opt {{ !$batch ? 'is-on' : '' }}" wire:click="pickBatch(null)"><span class="ff-opt-main"><b>All batches</b></span><span class="ff-n">{{ $batchTotal }}</span><i class="ti ti-check ff-check"></i></button>
                        <div class="ff-menu-list">
                            @forelse($batches as $b)
                                @php $n = (int) ($batchCounts[$b->id] ?? 0); @endphp
                                <button type="button" class="ff-opt {{ (int) $batch === $b->id ? 'is-on' : '' }} {{ $n ? '' : 'is-empty' }}" wire:click="pickBatch({{ $b->id }})">
                                    <span class="ff-opt-main"><b>{{ $b->code }} @unless($b->status)<em class="ff-closed">closed</em>@endunless</b><small>{{ $b->name }}{{ $b->start_date ? ' · ' . $b->start_date->format('M Y') : '' }}</small></span>
                                    <span class="ff-n">{{ $n }}</span><i class="ti ti-check ff-check"></i>
                                </button>
                            @empty
                                <div class="ff-empty">No batches for this course.</div>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>

            {{-- semester --}}
            <div class="dropdown ff-dd">
                <button type="button" class="ff-pill {{ $semester ? 'is-on' : '' }}" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ti ti-calendar-time"></i><span class="ff-pill-text">{{ $semester ? $periodWord . ' ' . $semester : $periodWord }}</span><i class="ti ti-chevron-down ff-caret"></i>
                </button>
                <div class="dropdown-menu ff-menu">
                    <div class="ff-menu-head">Current {{ strtolower($periodWord) }}</div>
                    <button type="button" class="ff-opt {{ !$semester ? 'is-on' : '' }}" wire:click="pickSemester(0)"><span class="ff-opt-main"><b>Any {{ strtolower($periodWord) }}</b></span><span class="ff-n">{{ $semesterTotal }}</span><i class="ti ti-check ff-check"></i></button>
                    <div class="ff-sem-grid">
                        @foreach(range(1, $semesterCount) as $n)
                            @php $c = (int) ($semesterCounts[$n] ?? 0); @endphp
                            <button type="button" class="ff-sem {{ (int) $semester === $n ? 'is-on' : '' }} {{ $c ? '' : 'is-empty' }}" wire:click="pickSemester({{ $n }})" title="Now in {{ $periodWord }} {{ $n }} · {{ $c }} {{ \Illuminate\Support\Str::plural('student', $c) }}">
                                <b>{{ $n }}</b><span>{{ $c }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            @if($filtered)<button type="button" class="ff-clear" wire:click="clearFilters" title="Clear filters"><i class="ti ti-filter-off"></i> Clear{{ $activeFilters ? ' (' . $activeFilters . ')' : '' }}</button>@endif
            <span class="ff-total ms-auto"><b>{{ $students->total() }}</b> {{ \Illuminate\Support\Str::plural('student', $students->total()) }}</span>
        </div>
    </div>

    <style>
        .sc-ui .ff { padding: 8px 10px; position: relative; z-index: 3; overflow: visible; }
        .sc-ui .ff-row { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
        .sc-ui .ff-search { position: relative; display: flex; align-items: center; flex: 1 1 220px; max-width: 300px; }
        .sc-ui .ff-search > i { position: absolute; left: 10px; color: #9CA3AF; font-size: 15px; pointer-events: none; }
        .sc-ui .ff-search input { width: 100%; height: 34px; padding: 0 28px 0 32px; border: 1px solid #E5E7EB; border-radius: 9px; background: #F9FAFB; font-size: 13px; outline: none; transition: border-color .15s, background .15s, box-shadow .15s; }
        .sc-ui .ff-search input:focus { background: #fff; border-color: #A5B4FC; box-shadow: 0 0 0 3px rgba(99, 102, 241, .12); }
        .sc-ui .ff-search .spinner-border { position: absolute; right: 9px; width: 13px; height: 13px; border-width: 2px; }
        .sc-ui .ff-dd { display: inline-flex; }
        .sc-ui .ff-pill { display: inline-flex; align-items: center; gap: 5px; height: 32px; padding: 0 9px 0 10px; border: 1px solid #E5E7EB; border-radius: 9px; background: #fff; font-size: 12.5px; font-weight: 500; color: #374151; white-space: nowrap; transition: border-color .15s, background .15s; }
        .sc-ui .ff-pill > i:first-child { font-size: 14px; color: #9CA3AF; }
        .sc-ui .ff-pill-text { max-width: 140px; overflow: hidden; text-overflow: ellipsis; }
        .sc-ui .ff-caret { font-size: 12px !important; color: #9CA3AF; margin-left: 1px; }
        .sc-ui .ff-pill:hover:not(:disabled) { border-color: #C7D2FE; background: #FAFAFF; }
        .sc-ui .ff-pill[aria-expanded="true"] { border-color: #818CF8; box-shadow: 0 0 0 3px rgba(99, 102, 241, .12); }
        .sc-ui .ff-pill:disabled { opacity: .5; cursor: not-allowed; }
        .sc-ui .ff-pill.is-on { border-color: #FDBA8C; background: #FFF7F2; color: #C2410C; font-weight: 600; }
        .sc-ui .ff-pill.is-on > i { color: #EA580C; }
        .sc-ui .ff-pill-schol { border-style: dashed; border-color: #C7D2FE; background: #F8F9FF; color: #4338CA; font-weight: 600; }
        .sc-ui .ff-pill-schol > i:first-child { color: #D97706; font-size: 15px; }
        .sc-ui .ff-pill-schol .ff-pill-text { max-width: 190px; }
        .sc-ui .ff-pill-schol.is-set { border: 1px solid #4F46E5; background: #EEF2FF; color: #312E81; }
        .sc-ui .ff-pill-badge { padding: 1px 7px; border-radius: 999px; background: #4F46E5; color: #fff; font-size: 11px; font-weight: 700; font-family: 'IBM Plex Mono', ui-monospace, monospace; }
        .sc-ui .ff-x { width: 26px; height: 26px; border: 0; border-radius: 7px; background: none; color: #9CA3AF; display: inline-flex; align-items: center; justify-content: center; margin-left: -4px; }
        .sc-ui .ff-x:hover { background: #FEF2F2; color: #DC2626; }
        .sc-ui .ff-sep { width: 1px; height: 20px; background: #E5E7EB; margin: 0 2px; }
        .sc-ui .ff-clear { border: 0; background: none; color: #6B7280; font-size: 12.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 3px; padding: 4px 6px; border-radius: 7px; }
        .sc-ui .ff-clear:hover { background: #FEF2F2; color: #DC2626; }
        .sc-ui .ff-total { font-size: 12.5px; color: #6B7280; white-space: nowrap; }
        .sc-ui .ff-total b { color: #111827; font-family: 'IBM Plex Mono', ui-monospace, monospace; }
        /* menus */
        .sc-ui .ff-menu { min-width: 230px; max-width: 280px; padding: 4px; border: 1px solid #E5E7EB; border-radius: 11px; box-shadow: 0 12px 28px rgba(17, 24, 39, .12); margin-top: 4px !important; font-size: 12.5px; }
        .sc-ui .ff-menu-wide { min-width: 290px; max-width: 330px; }
        .sc-ui .ff-menu-head { display: flex; align-items: center; padding: 5px 8px 4px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #9CA3AF; }
        .sc-ui .ff-menu-head small { margin-left: auto; text-transform: none; letter-spacing: 0; font-weight: 500; }
        .sc-ui .ff-menu-list { max-height: 240px; overflow-y: auto; border-top: 1px solid #F3F4F6; margin-top: 3px; padding-top: 3px; }
        .sc-ui .ff-opt { display: flex; align-items: center; gap: 8px; width: 100%; padding: 6px 8px; border: 0; background: none; border-radius: 8px; text-align: left; }
        .sc-ui .ff-opt:hover { background: #F5F7FF; }
        .sc-ui .ff-opt.is-on { background: #FFF7F2; }
        .sc-ui .ff-opt.is-empty .ff-opt-main { opacity: .55; }
        .sc-ui .ff-opt-main { display: flex; flex-direction: column; min-width: 0; flex: 1; line-height: 1.25; }
        .sc-ui .ff-opt-main b { font-size: 12.5px; font-weight: 600; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sc-ui .ff-opt-main small { font-size: 11px; color: #6B7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sc-ui .ff-ico { width: 26px; height: 26px; border-radius: 7px; background: #F3F4F6; color: #6B7280; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0; }
        .sc-ui .ff-ico-val { width: auto; min-width: 42px; padding: 0 6px; font-size: 10.5px; font-weight: 800; font-family: 'IBM Plex Mono', ui-monospace, monospace; }
        .sc-ui .ff-ico-val.is-pct { background: #EEF2FF; color: #3730A3; }
        .sc-ui .ff-ico-val.is-fixed { background: #FEF3C7; color: #92400E; }
        .sc-ui .ff-tag { padding: 1px 6px; border-radius: 5px; background: #EEF2FF; color: #4338CA; font-size: 10.5px; font-weight: 700; flex-shrink: 0; }
        .sc-ui .ff-closed { font-style: normal; font-size: 9.5px; font-weight: 700; color: #991B1B; background: #FEE2E2; padding: 0 5px; border-radius: 999px; margin-left: 3px; }
        .sc-ui .ff-n { min-width: 22px; padding: 1px 6px; border-radius: 999px; background: #F3F4F6; color: #4B5563; font-size: 10.5px; font-weight: 700; text-align: center; }
        .sc-ui .ff-opt.is-on .ff-n { background: #FED7AA; color: #9A3412; }
        .sc-ui .ff-check { color: #EA580C; font-size: 14px; visibility: hidden; }
        .sc-ui .ff-opt.is-on .ff-check { visibility: visible; }
        .sc-ui .ff-empty { padding: 10px; color: #9CA3AF; text-align: center; }
        .sc-ui .ff-sem-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 4px; padding: 5px 4px 3px; border-top: 1px solid #F3F4F6; margin-top: 3px; }
        .sc-ui .ff-sem { display: flex; flex-direction: column; align-items: center; padding: 5px 2px; border: 1px solid #E5E7EB; border-radius: 8px; background: #fff; line-height: 1.15; }
        .sc-ui .ff-sem:hover { border-color: #A5B4FC; background: #F5F7FF; }
        .sc-ui .ff-sem b { font-size: 14px; color: #111827; font-family: 'IBM Plex Mono', ui-monospace, monospace; }
        .sc-ui .ff-sem span { font-size: 10px; color: #6B7280; }
        .sc-ui .ff-sem.is-empty { opacity: .45; }
        .sc-ui .ff-sem.is-on { border-color: #F26522; background: #FFF7F2; }
        .sc-ui .ff-sem.is-on b { color: #C2410C; }
        @media (max-width: 767px) {
            .sc-ui .ff-search { flex: 1 1 100%; max-width: none; }
            .sc-ui .ff-sep { display: none; }
            .sc-ui .ff-total { width: 100%; text-align: right; }
        }
    </style>

    <div class="row g-3">
        <!-- Students -->
        <div class="col-xxl-8">
            <div class="sc-panel">
                <div class="sc-panel-head">
                    <div class="sc-tabs">
                        @foreach($tabs as $key => [$label, $icon, $n])
                            <button type="button" class="{{ $show === $key ? 'is-on' : '' }}" wire:click="setShow('{{ $key }}')">
                                <i class="{{ $icon }} me-1"></i> {{ $label }} <span class="badge {{ $key === 'revoked' ? 'bg-danger-transparent text-danger' : ($key === 'assigned' || $key === 'with' ? 'bg-success-transparent text-success' : 'bg-primary-transparent text-primary') }} ms-1">{{ $n }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                @if($s && $show === 'eligible' && $pageEligible)
                    <div class="as-toolbar">
                        <label class="as-check-all"><input type="checkbox" class="form-check-input m-0" @if($pageAllPicked) checked @endif wire:click="selectPage({{ json_encode($pageEligible) }})"> Select this page</label>
                        @if($tabs['eligible'][2] > count($pageEligible))
                            <button type="button" class="btn btn-sm btn-link px-1" wire:click="selectAllEligible">Select all {{ $tabs['eligible'][2] }} eligible</button>
                        @endif
                        @if($selected)<span class="as-count ms-auto">{{ count($selected) }} selected</span>@endif
                    </div>
                @elseif(!$s)
                    <div class="as-hint"><i class="ti ti-info-circle"></i> Choose a <b>scholarship</b> in the filters (or on the right) to see who is eligible, the discount and the payable after assigning.</div>
                @endif

                <div class="as-legend">
                    <span><i class="as-dot as-dot-fee"></i> Total fees</span>
                    <span><i class="as-dot as-dot-other"></i> Existing discounts</span>
                    @if($s)<span><i class="as-dot as-dot-this"></i> {{ $s->code }}</span>@endif
                    <span><i class="as-dot as-dot-pay"></i> Payable now{{ $s && $show === 'eligible' ? ' (→ after assigning)' : '' }}</span>
                </div>

                <div class="as-list">
                    @forelse($students as $st)
                        @php
                            $t = $structures[$st->id]['totals'];
                            $isEligible = in_array($st->id, $eligibleIds);
                            $has = $s && $st->scholarships->contains('scholarship_id', $s->id);
                            $picked = in_array((string) $st->id, $selected, true);
                            $rv = $revoked[$st->id] ?? null;
                            $existingOnly = $has ? max(0, $t['existing']) : $t['existing'];
                        @endphp
                        <div class="as-row {{ $picked ? 'is-picked' : '' }} {{ $has ? 'is-has' : '' }}" wire:key="row-{{ $st->id }}">
                            <div class="as-row-main">
                                <div class="as-pick">
                                    @if($s && $isEligible && $s->status)
                                        <input type="checkbox" class="form-check-input" @if($picked) checked @endif wire:click="toggle({{ $st->id }})" aria-label="Select {{ $st->full_name }}">
                                    @elseif($has)
                                        <i class="ti ti-award text-success" title="Has {{ $s->name }}"></i>
                                    @else
                                        <span class="as-pick-off" title="{{ $s ? 'Not eligible for this scholarship' : 'Choose a scholarship to select students' }}"></span>
                                    @endif
                                </div>
                                <div class="as-who">
                                    <span class="sc-av">{{ $st->initials }}</span>
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.students.fees', $st->id) }}" class="fw-semibold text-dark d-block text-truncate">{{ $st->full_name }}</a>
                                        <div class="small text-muted"><span class="font-monospace">{{ $st->er_number }}</span> · {{ optional($st->course)->code }}{{ $st->batch ? ' · ' . $st->batch->code : '' }}</div>
                                        <div class="small text-muted text-truncate">{{ $st->current_period_label ?: '—' }}@if($isSuperAdmin && $st->institute) · {{ $st->institute->name }}@endif</div>
                                        @if($st->scholarships->isNotEmpty())
                                            <div class="as-has-chips">
                                                @foreach($st->scholarships as $ss)<span class="sc-code" title="{{ optional($ss->scholarship)->name }}">{{ optional($ss->scholarship)->code }}</span>@endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="as-metrics {{ $s ? '' : 'as-metrics-3' }}">
                                    <div class="as-m"><span>Total fees</span><b>{{ $fmt($t['fees']) }}</b></div>
                                    <div class="as-m as-m-other"><span>Existing disc.</span><b>{{ $existingOnly > 0 ? '− ' . $fmt($existingOnly) : '—' }}</b></div>
                                    @if($s)
                                        <div class="as-m as-m-this"><span>{{ $s->code }}</span>
                                            @if($has)<b>− {{ $fmt($t['this_now']) }}</b><small class="text-success">given</small>
                                            @elseif($isEligible)<b>{{ $t['this_now'] > 0 ? '− ' . $fmt($t['this_now']) : '—' }}</b>@if($t['this_later'] > 0)<small>+ {{ $fmt($t['this_later']) }} later</small>@endif
                                            @else<b class="text-muted">—</b><small>{{ $rv ? 'revoked' : 'not eligible' }}</small>@endif
                                        </div>
                                    @endif
                                    <div class="as-m as-m-pay">
                                        <span>Payable now</span><b>{{ $fmt($t['balance_now']) }}</b>
                                        @if($s && $isEligible && $t['this_now'] > 0)<small class="as-after">→ {{ $fmt($t['payable_now']) }} after assigning</small>
                                        @elseif($t['payable_later'] > 0)<small>+ {{ $fmt($t['payable_later']) }} upcoming</small>@endif
                                    </div>
                                </div>
                                <div class="as-act">
                                    <button type="button" class="btn btn-sm btn-light border" wire:click="showFees({{ $st->id }})" title="Complete fee structure"><i class="ti ti-receipt-2"></i><span class="d-none d-md-inline ms-1">Fees</span></button>
                                    @if($s && $isEligible && $s->status)
                                        <button type="button" class="btn btn-sm btn-primary" wire:click="confirmAssign({{ $st->id }})">{{ $rv ? 'Assign again' : 'Assign' }}</button>
                                    @elseif($has)
                                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="openRevokeFor({{ $st->id }})" title="Revoke {{ $s->code }}"><i class="ti ti-award-off"></i><span class="d-none d-md-inline ms-1">Revoke</span></button>
                                    @endif
                                </div>
                            </div>
                            @if($rv)
                                @php [$givenNote, $why] = array_pad(explode(' | Revoked: ', (string) $rv->remarks, 2), 2, null); if (str_starts_with((string) $givenNote, 'Revoked: ')) { [$givenNote, $why] = [null, substr($givenNote, 9)]; } @endphp
                                <div class="as-rv">
                                    <span><i class="ti ti-award text-success"></i> Given by {{ optional($rv->assigner)->name }} · {{ optional($rv->assigned_at)->format('d M Y') }}</span>
                                    <span><i class="ti ti-award-off text-danger"></i> Revoked by {{ optional($rv->revoker)->name }} · {{ optional($rv->revoked_at)->format('d M Y, h:i A') }}</span>
                                    @if($why)<span class="text-danger">Reason: {{ $why }}</span>@endif
                                    <span class="ms-auto">Discount kept on paid fees: <b class="font-monospace">{{ $fmt($rv->kept ?? 0) }}</b></span>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center text-muted py-5">
                            <i class="ti ti-users-minus fs-1 d-block mb-2 opacity-50"></i>
                            @if($s && $show === 'assigned')Nobody has this scholarship yet.
                            @elseif($s && $show === 'revoked')No revoked assignments.
                            @elseif($s)No eligible students{{ $filtered ? ' match these filters' : '' }} — the scholarship is for {{ optional($s->course)->name }}{{ $s->batch ? ' (batch ' . $s->batch->code . ')' : '' }}, admitted students only.
                            @else No admitted students{{ $filtered ? ' match these filters' : ' yet' }}.@endif
                            @if($filtered)<div class="mt-2"><button type="button" class="btn btn-sm btn-light border" wire:click="clearFilters">Clear filters</button></div>@endif
                        </div>
                    @endforelse
                </div>
                @if($students->hasPages())<div class="px-3 py-2 border-top">{{ $students->links() }}</div>@endif
            </div>
        </div>

        <!-- Scholarship + review -->
        <div class="col-xxl-4">
            <div class="sc-panel as-review">
                @if(!$s)
                    <div class="as-review-head"><span class="as-num">1</span><div><div class="fw-bold">Choose a scholarship</div><small class="text-muted">{{ $scholarships->count() }} active{{ $course ? ' · matching course first' : '' }}</small></div></div>
                    <div class="as-quick">
                        @forelse($scholarships->sortByDesc(fn ($o) => $course && (int) $o->course_id === (int) $course)->take(8) as $opt)
                            <button type="button" class="as-quick-item" wire:click="chooseScholarship({{ $opt->id }})">
                                <span class="sc-value {{ $opt->type === 'percentage' ? 'is-pct' : 'is-fixed' }}">
                                    @if($opt->type === 'percentage')<b>{{ rtrim(rtrim(number_format((float) $opt->value, 2), '0'), '.') }}<small>%</small></b>
                                    @else<b><small>₹</small>{{ number_format((float) $opt->value) }}</b>@endif
                                </span>
                                <span class="min-w-0 text-start">
                                    <span class="d-block fw-semibold text-truncate">{{ $opt->name }}</span>
                                    <small class="text-muted d-block text-truncate">{{ $opt->code }} · {{ optional($opt->course)->name }} · {{ $opt->periods_label }}</small>
                                </span>
                                <i class="ti ti-chevron-right ms-auto text-muted"></i>
                            </button>
                        @empty
                            <div class="small text-muted p-3">No active scholarships. <a href="{{ route('admin.scholarships') }}">Create one</a>.</div>
                        @endforelse
                    </div>
                @else
                    <div class="as-chosen">
                        <div class="sc-value {{ $s->type === 'percentage' ? 'is-pct' : 'is-fixed' }}">
                            @if($s->type === 'percentage')<b>{{ rtrim(rtrim(number_format((float) $s->value, 2), '0'), '.') }}<small>%</small></b><span>off</span>
                            @else<b><small>₹</small>{{ number_format((float) $s->value) }}</b><span>/ {{ strtolower($periodWord) }}</span>@endif
                        </div>
                        <div class="min-w-0 flex-grow-1">
                            <div class="fw-bold text-truncate">{{ $s->name }} <span class="sc-code ms-1">{{ $s->code }}</span></div>
                            <div class="small text-muted text-truncate">{{ optional($s->course)->name }} · {{ $s->batch ? 'Batch ' . $s->batch->code : 'every batch' }}</div>
                            <div class="as-covers mt-1">
                                @foreach(range(1, max(1, (int) optional($s->course)->total_periods)) as $n)<span class="{{ in_array($n, $s->periods, true) ? 'is-on' : '' }}">{{ $n }}</span>@endforeach
                                <small class="text-muted ms-1">{{ $s->applies_to === 'all_fees' ? '+ one-time' : 'semester fees' }}</small>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-light border" wire:click="$set('scholarship', '')" title="Clear scholarship"><i class="ti ti-x"></i></button>
                    </div>
                    <div class="as-review-head"><span class="as-num">2</span>
                        <div><div class="fw-bold">Review selection</div><small class="text-muted">{{ $summary && $summary['count'] ? $summary['count'] . ' ' . \Illuminate\Support\Str::plural('student', $summary['count']) . ' selected' : 'Tick eligible students on the left' }}</small></div>
                        @if($selected)<button type="button" class="btn btn-sm btn-link text-muted ms-auto" wire:click="clearSelection">Clear</button>@endif
                    </div>
                    @if(!$summary || !$summary['count'])
                        <div class="as-review-empty"><i class="ti ti-list-check"></i><div>Select one or more eligible students to see the combined fees, the scholarship amount and the payable after assigning.</div></div>
                    @else
                        <div class="as-totals">
                            <div><span>Total fees</span><b>{{ $fmt($summary['fees']) }}</b></div>
                            <div class="as-m-other"><span>Existing discounts</span><b>{{ $summary['existing'] > 0 ? '− ' . $fmt($summary['existing']) : '—' }}</b></div>
                            <div class="as-m-this"><span>{{ $s->code }} now</span><b>− {{ $fmt($summary['this_now']) }}</b></div>
                            <div class="as-m-this"><span>…later semesters</span><b>{{ $summary['this_later'] > 0 ? '− ' . $fmt($summary['this_later']) : '—' }}</b></div>
                            <div><span>Payable now</span><b>{{ $fmt($summary['balance_now']) }}</b></div>
                            <div><span>Already paid</span><b>{{ $summary['paid'] > 0 ? $fmt($summary['paid']) : '—' }}</b></div>
                            <div class="as-m-pay as-total-pay"><span>Payable after assigning</span><b>{{ $fmt($summary['payable_now']) }}</b></div>
                        </div>
                        <div class="as-picked">
                            @foreach($summary['students'] as $p)
                                <div class="as-picked-row" wire:key="p-{{ $p['id'] }}">
                                    <span class="sc-av" style="width: 30px; height: 30px; font-size: 11px;">{{ $p['initials'] }}</span>
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="fw-semibold text-truncate small">{{ $p['name'] }}</div>
                                        <div class="text-muted" style="font-size: 11.5px;">pays {{ $fmt($p['payable']) }}</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="as-this-amt">− {{ $fmt($p['this']) }}</div>
                                        @if($p['later'] > 0)<div class="text-muted" style="font-size: 11px;">+ {{ $fmt($p['later']) }} later</div>@endif
                                    </div>
                                    <button type="button" class="btn btn-sm btn-icon btn-light" wire:click="toggle({{ $p['id'] }})" title="Remove from selection" aria-label="Remove {{ $p['name'] }}"><i class="ti ti-x"></i></button>
                                </div>
                            @endforeach
                        </div>
                        <div class="p-3 border-top">
                            <input type="text" class="form-control form-control-sm mb-2" wire:model.defer="remarks" placeholder="Note (optional), e.g. Merit list 2026">
                            <button type="button" class="btn as-assign-btn w-100" wire:click="confirmAssign"><i class="ti ti-award me-1"></i> Assign to {{ $summary['count'] }} {{ \Illuminate\Support\Str::plural('student', $summary['count']) }}</button>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <!-- Complete fee structure -->
    <div class="modal fade" id="feesModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg as-fm">
                @if($feeStudent && $feeStructure)
                    @php $ft = $feeStructure['totals']; $fHas = $feeHas; $fPicked = in_array((string) $feeStudent->id, $selected, true); @endphp
                    <div class="as-fm-head">
                        <span class="sc-av as-fm-av">{{ $feeStudent->initials }}</span>
                        <div class="min-w-0 flex-grow-1">
                            <div class="as-fm-kicker">Complete fee structure</div>
                            <h4 class="mb-0 text-truncate">{{ $feeStudent->full_name }}</h4>
                            <div class="as-fm-meta">
                                <span class="font-monospace">{{ $feeStudent->er_number }}</span>
                                <span>{{ optional($feeStudent->course)->name }}</span>
                                @if($feeStudent->batch)<span>Batch {{ $feeStudent->batch->code }}</span>@endif
                                @if($feeStudent->current_period_label)<span>{{ $feeStudent->current_period_label }}</span>@endif
                            </div>
                        </div>
                        @if($s)<div class="as-fm-schol"><i class="ti ti-award"></i><div><small>Scholarship</small><b>{{ $s->name }}</b><span>{{ $s->value_label }}</span></div></div>@endif
                        <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="as-fm-cards">
                            <div class="as-fm-card"><span><i class="ti ti-receipt"></i> Total fees</span><b>{{ $fmt($ft['fees']) }}</b><small>all semesters</small></div>
                            <div class="as-fm-card is-other"><span><i class="ti ti-discount-2"></i> Existing discounts</span><b>{{ $ft['existing'] > 0 ? '− ' . $fmt($ft['existing']) : '—' }}</b><small>other scholarships</small></div>
                            <div class="as-fm-card is-this"><span><i class="ti ti-award"></i> {{ $s ? $s->code : 'Scholarship' }}</span><b>{{ $s ? '− ' . $fmt($ft['this_now']) : '—' }}</b><small>{{ !$s ? 'choose one to preview' : ($fHas ? 'given — saved on the fees' : (!$feeEligible ? 'not eligible' : ($ft['this_later'] > 0 ? 'if assigned · + ' . $fmt($ft['this_later']) . ' later' : 'if assigned'))) }}</small></div>
                            <div class="as-fm-card is-paid"><span><i class="ti ti-circle-check"></i> Already paid</span><b>{{ $fmt($ft['paid']) }}</b><small>confirmed payments</small></div>
                            <div class="as-fm-card is-pay">
                                <span><i class="ti ti-wallet"></i> Payable now</span><b>{{ $fmt($ft['balance_now']) }}</b>
                                <small>@if($s && $feeEligible && $ft['this_now'] > 0)→ <b class="as-after-b">{{ $fmt($ft['payable_now']) }}</b> after assigning @else{{ $ft['payable_later'] > 0 ? '+ ' . $fmt($ft['payable_later']) . ' upcoming' : 'nothing upcoming' }}@endif</small>
                            </div>
                        </div>

                        @foreach($feeStructure['groups'] as $g)
                            <div class="as-fm-sem {{ $g['covered'] ? 'is-covered' : '' }} {{ $g['upcoming'] ? 'is-upcoming' : '' }}">
                                <div class="as-fm-sem-head">
                                    <span class="as-fm-sem-icon"><i class="ti ti-{{ $g['upcoming'] ? 'calendar-time' : ($g['label'] === 'One-time fees' ? 'receipt' : 'calendar-check') }}"></i></span>
                                    <span class="fw-bold">{{ $g['label'] }}</span>
                                    @if($g['covered'])<span class="sc-tag sc-tag-has"><i class="ti ti-award"></i> covered by this scholarship</span>@endif
                                    @if($g['upcoming'])<span class="sc-tag sc-tag-no"><i class="ti ti-clock"></i> not charged yet</span>@endif
                                    <span class="ms-auto as-fm-sem-total">{{ ($fHas || !$s || !$feeEligible) ? 'Payable' : 'After assigning' }} <b>{{ $fmt($g['subtotal']['payable']) }}</b></span>
                                </div>
                                <div class="table-responsive">
                                    <table class="as-fees">
                                        <thead><tr><th>Fee</th><th>Amount</th><th>Existing disc.</th><th>{{ $s ? $s->code : 'Scholarship' }}</th><th>Paid</th><th>{{ ($fHas || !$s || !$feeEligible) ? 'Payable' : 'After assigning' }}</th><th>Status</th></tr></thead>
                                        <tbody>
                                            @foreach($g['lines'] as $line)
                                                <tr class="{{ $line['upcoming'] ? 'is-upcoming' : '' }} {{ $line['waived'] ? 'is-waived' : '' }}">
                                                    <td>{{ $line['fee'] }}@if($line['note'])<span class="sc-tag sc-tag-warn ms-1" style="font-size: 10px;">{{ $line['note'] }}</span>@endif</td>
                                                    <td>{{ $fmt($line['amount']) }}</td>
                                                    <td class="is-other">{{ $line['existing'] > 0 ? '− ' . $fmt($line['existing']) : '—' }}</td>
                                                    <td class="is-this">{{ $line['this'] > 0 ? '− ' . $fmt($line['this']) : '—' }}@if($line['upcoming'] && $line['this'] > 0)<small> projected</small>@endif</td>
                                                    <td>{{ $line['paid'] > 0 ? $fmt($line['paid']) : '—' }}</td>
                                                    <td class="is-pay">{{ $line['waived'] ? '—' : $fmt($line['payable']) }}</td>
                                                    <td class="as-status">
                                                        @php $st = $line['status']; $tone = $st === 'Paid' ? 'ok' : ($st === 'Part paid' ? 'warn' : ($line['upcoming'] ? 'no' : ($st === 'Waived' ? 'no' : 'has'))); @endphp
                                                        <span class="sc-tag sc-tag-{{ $tone }}">{{ $st }}</span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="as-sub-foot">
                                            <tr>
                                                <td>{{ $g['label'] }} total</td>
                                                <td>{{ $fmt($g['subtotal']['amount']) }}</td>
                                                <td class="is-other">{{ $g['subtotal']['existing'] > 0 ? '− ' . $fmt($g['subtotal']['existing']) : '—' }}</td>
                                                <td class="is-this">{{ $g['subtotal']['this'] > 0 ? '− ' . $fmt($g['subtotal']['this']) : '—' }}</td>
                                                <td>{{ $g['subtotal']['paid'] > 0 ? $fmt($g['subtotal']['paid']) : '—' }}</td>
                                                <td class="is-pay">{{ $fmt($g['subtotal']['payable']) }}</td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                        @if(!$feeStructure['groups'])<div class="text-center text-muted py-4"><i class="ti ti-receipt-off fs-2 d-block mb-1"></i>No fees in the fee structure for this student yet.</div>@endif

                        <div class="as-fm-grand">
                            <div><span>All fees</span><b>{{ $fmt($ft['fees']) }}</b></div>
                            <div><span>Discounts (existing + this)</span><b>− {{ $fmt($ft['existing'] + $ft['this_now'] + $ft['this_later']) }}</b></div>
                            <div><span>Paid</span><b>{{ $fmt($ft['paid']) }}</b></div>
                            <div class="is-pay"><span>{{ ($fHas || !$s || !$feeEligible) ? 'Still to pay (incl. upcoming)' : 'Still to pay after assigning (incl. upcoming)' }}</span><b>{{ $fmt($ft['payable_now'] + $ft['payable_later']) }}</b></div>
                        </div>
                        <div class="as-detail-note"><i class="ti ti-info-circle"></i> Fully paid fees are not discounted (no refunds). Fees not charged yet get the scholarship automatically when they are charged ("projected").</div>
                    </div>
                    <div class="modal-footer bg-light as-fm-foot">
                        <a href="{{ route('admin.students.fees', $feeStudent->id) }}" class="btn btn-light border me-auto"><i class="ti ti-external-link me-1"></i> Student fees page</a>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        @if($s && $feeEligible && $s->status)
                            <button type="button" class="btn {{ $fPicked ? 'btn-outline-secondary' : 'btn-outline-primary' }}" wire:click="toggle({{ $feeStudent->id }})">
                                <i class="ti ti-{{ $fPicked ? 'square-minus' : 'square-plus' }} me-1"></i> {{ $fPicked ? 'Remove from selection' : 'Add to selection' }}
                            </button>
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal" wire:click="confirmAssign({{ $feeStudent->id }})"><i class="ti ti-award me-1"></i> Assign to {{ $feeStudent->first_name }}</button>
                        @elseif($s && $fHas)
                            <button type="button" class="btn btn-outline-danger bg-white" data-bs-dismiss="modal" wire:click="openRevokeFor({{ $feeStudent->id }})"><i class="ti ti-award-off me-1"></i> Revoke from {{ $feeStudent->first_name }}</button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Confirm assign -->
    <div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-body py-4 px-4">
            <div class="text-center">
                <span class="sc-hero-icon mb-3"><i class="ti ti-award"></i></span>
                <h5 class="mb-1">Assign {{ optional($s)->name }}?</h5>
                @if($confirm)<p class="text-muted small mb-3">{{ $confirm['name'] ?? ($confirm['count'] . ' ' . \Illuminate\Support\Str::plural('student', $confirm['count'])) }}</p>@endif
            </div>
            @if($confirm)
                <div class="as-confirm">
                    <div><span>Total fees</span><b>{{ $fmt($confirm['fees'] ?? 0) }}</b></div>
                    <div><span>Existing discounts</span><b>{{ ($confirm['existing'] ?? 0) > 0 ? '− ' . $fmt($confirm['existing']) : '—' }}</b></div>
                    <div class="is-this"><span>This scholarship now</span><b>− {{ $fmt($confirm['total']) }}</b></div>
                    @if(($confirm['later'] ?? 0) > 0)<div class="is-this"><span>Later semesters (when charged)</span><b>− {{ $fmt($confirm['later']) }}</b></div>@endif
                    <div class="is-pay"><span>Payable after assigning</span><b>{{ $fmt($confirm['payable'] ?? 0) }}</b></div>
                </div>
                <p class="small text-muted text-center mt-3 mb-3">Fully paid fees are not refunded. Every assignment is recorded in the audit trail and the student is notified.</p>
            @endif
            <input type="text" class="form-control form-control-sm mb-3" wire:model.defer="remarks" placeholder="Note (optional), e.g. Merit list 2026">
            <div class="d-flex justify-content-center gap-2">
                <button class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary px-4" wire:click="assign" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="assign">Yes, assign</span>
                    <span wire:loading wire:target="assign"><span class="spinner-border spinner-border-sm me-1"></span> Applying…</span>
                </button>
            </div>
        </div></div></div>
    </div>

    <!-- Revoke -->
    <div class="modal fade" id="revokeModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-body text-center py-4">
            <i class="ti ti-award-off text-danger fs-1 mb-3"></i>
            <h5>Revoke this scholarship?</h5>
            @if($revokeInfo)
                <div class="as-revoke-info">
                    <div class="fw-semibold">{{ $revokeInfo['name'] }} · {{ $revokeInfo['scholarship'] }}</div>
                    <div class="d-flex justify-content-center gap-3 flex-wrap mt-1">
                        <span>Removed from unpaid fees: <b class="text-danger">{{ money_inr($revokeInfo['removed'], false) }}</b></span>
                        @if($revokeInfo['kept'] > 0)<span>Kept on paid fees: <b>{{ money_inr($revokeInfo['kept'], false) }}</b></span>@endif
                    </div>
                </div>
            @endif
            <p class="small text-muted">The discount is removed from fees that have no payment yet. Paid fees keep their discount.</p>
            <textarea rows="2" class="form-control text-start @error('revokeReason') is-invalid @enderror" wire:model.defer="revokeReason" placeholder="Reason (required)"></textarea>
            @error('revokeReason')<div class="invalid-feedback text-start">{{ $message }}</div>@enderror
            <div class="d-flex justify-content-center gap-2 mt-3">
                <button class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger px-4" wire:click="revoke">Yes, revoke</button>
            </div>
        </div></div></div>
    </div>

    @include('livewire.admin.scholarships.partials.styles')
    <style>
        .sc-ui .as-revoke-info { margin: 8px 0 10px; padding: 10px 12px; border-radius: 12px; background: #FEF2F2; border: 1px solid #FECACA; font-size: 13px; color: #374151; }
        .sc-ui .as-act .btn-outline-danger { background: #fff; }
        .sc-ui .as-m small.as-after { color: #4338CA; font-weight: 600; }
        .sc-ui .as-after-b { font-size: 12px !important; color: #FDE68A !important; font-family: 'IBM Plex Mono', ui-monospace, monospace; }
        .sc-ui .as-fm-foot { display: flex; flex-wrap: wrap; gap: 10px; padding: 14px 20px; }
        .sc-ui .as-fm-foot > * { margin: 0 !important; }
        .sc-ui .as-fm-foot > .me-auto { margin-right: auto !important; }
        .sc-ui .as-fm-foot .btn-outline-primary { background: #fff; }
        .sc-ui .as-filterbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .sc-ui .as-filterbar > .form-select { width: auto; min-width: 170px; max-width: 260px; }
        .sc-ui .as-filterbar-sm { padding: 10px 18px; border-bottom: 1px solid #F1F2F4; background: #fff; }
        .sc-ui .as-filterbar-sm > .form-select { min-width: 150px; }
        .sc-ui .as-filter-label { font-size: 12px; font-weight: 700; color: #6B7280; text-transform: uppercase; letter-spacing: .04em; display: inline-flex; align-items: center; gap: 4px; }
        .sc-ui .as-toggle { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border: 1px solid #E5E7EB; border-radius: 10px; background: #fff; font-size: 13px; cursor: pointer; }
        .sc-ui .as-count { font-size: 12.5px; color: #6B7280; font-weight: 600; }
        .sc-ui .as-step-title { display: flex; align-items: center; gap: 6px; font-weight: 700; font-size: 15px; margin-bottom: 12px; color: #111827; }
        .sc-ui .as-choose { text-align: left; cursor: pointer; width: 100%; }
        .sc-ui .as-choose:focus-visible { outline: 3px solid #A5B4FC; outline-offset: 2px; }
        .sc-ui .as-choose-cta { font-size: 13px; font-weight: 700; color: #4338CA; display: inline-flex; align-items: center; gap: 4px; }
        .sc-ui .as-choose:hover .as-choose-cta i { transform: translateX(3px); }
        .sc-ui .as-choose-cta i { transition: transform .15s; }
        .sc-ui .as-selected { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; padding: 14px 18px; border-radius: 16px; background: linear-gradient(135deg, #EEF2FF, #F5F3FF); border: 1px solid #E0E7FF; }
        .sc-ui .as-switch { max-width: 260px; }
        .sc-ui .as-fm { border-radius: 18px; overflow: hidden; }
        .sc-ui .as-fm-head { display: flex; align-items: center; gap: 14px; padding: 18px 22px; color: #fff; background: radial-gradient(circle at 95% -30%, rgba(251, 191, 36, .35), transparent 45%), linear-gradient(135deg, #1E1B4B, #4338CA); }
        .sc-ui .as-fm-head h4 { color: #fff; font-weight: 700; }
        .sc-ui .as-fm-av { width: 48px; height: 48px; font-size: 16px; background: rgba(255, 255, 255, .15); color: #fff; }
        .sc-ui .as-fm-kicker { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #FCD34D; font-weight: 700; }
        .sc-ui .as-fm-meta { display: flex; flex-wrap: wrap; gap: 4px 14px; font-size: 12.5px; color: rgba(255, 255, 255, .75); }
        .sc-ui .as-fm-schol { display: flex; gap: 10px; align-items: center; padding: 8px 12px; border-radius: 12px; background: rgba(255, 255, 255, .12); border: 1px solid rgba(255, 255, 255, .18); }
        .sc-ui .as-fm-schol > i { font-size: 22px; color: #FCD34D; }
        .sc-ui .as-fm-schol div { display: flex; flex-direction: column; line-height: 1.25; }
        .sc-ui .as-fm-schol small { font-size: 10.5px; color: rgba(255, 255, 255, .65); text-transform: uppercase; letter-spacing: .05em; }
        .sc-ui .as-fm-schol span { font-size: 12px; color: rgba(255, 255, 255, .8); }
        .sc-ui .as-fm-cards { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; margin-bottom: 18px; }
        .sc-ui .as-fm-card { display: flex; flex-direction: column; gap: 2px; padding: 12px 14px; border-radius: 14px; background: #F8FAFC; border: 1px solid #E2E8F0; }
        .sc-ui .as-fm-card span { font-size: 11.5px; color: #64748B; font-weight: 600; display: flex; align-items: center; gap: 5px; }
        .sc-ui .as-fm-card b { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 19px; color: #0F172A; }
        .sc-ui .as-fm-card small { font-size: 11px; color: #94A3B8; }
        .sc-ui .as-fm-card.is-other { background: #FFFBEB; border-color: #FDE68A; } .sc-ui .as-fm-card.is-other b { color: #B45309; }
        .sc-ui .as-fm-card.is-this { background: #EEF2FF; border-color: #C7D2FE; } .sc-ui .as-fm-card.is-this b { color: #4338CA; }
        .sc-ui .as-fm-card.is-paid { background: #F0FDF4; border-color: #BBF7D0; } .sc-ui .as-fm-card.is-paid b { color: #15803D; }
        .sc-ui .as-fm-card.is-pay { background: #1E1B4B; border-color: #1E1B4B; } .sc-ui .as-fm-card.is-pay span, .sc-ui .as-fm-card.is-pay small { color: rgba(255, 255, 255, .7); } .sc-ui .as-fm-card.is-pay b { color: #fff; }
        .sc-ui .as-fm-sem { margin-bottom: 14px; border: 1px solid #E5E7EB; border-radius: 14px; overflow: hidden; }
        .sc-ui .as-fm-sem.is-covered { border-color: #C7D2FE; box-shadow: 0 0 0 3px rgba(99, 102, 241, .06); }
        .sc-ui .as-fm-sem.is-upcoming { border-style: dashed; }
        .sc-ui .as-fm-sem-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 10px 14px; background: #FAFAFB; border-bottom: 1px solid #F1F2F4; }
        .sc-ui .as-fm-sem.is-covered .as-fm-sem-head { background: #F5F7FF; }
        .sc-ui .as-fm-sem-icon { width: 28px; height: 28px; border-radius: 8px; background: #fff; border: 1px solid #E5E7EB; display: inline-flex; align-items: center; justify-content: center; color: #4338CA; }
        .sc-ui .as-fm-sem-total { font-size: 12.5px; color: #6B7280; } .sc-ui .as-fm-sem-total b { font-family: 'IBM Plex Mono', ui-monospace, monospace; color: #111827; margin-left: 4px; }
        .sc-ui .as-fm-sem .as-fees { border: 0; border-radius: 0; }
        .sc-ui .as-fees .as-sub-foot td { background: #FCFCFD; font-weight: 700; color: #374151; border-top: 1px solid #EEF0F3; font-size: 12px; }
        .sc-ui .as-fees .as-sub-foot td:first-child { color: #6B7280; font-weight: 600; }
        .sc-ui .as-fees .as-sub-foot td.is-other { color: #B45309; } .sc-ui .as-fees .as-sub-foot td.is-this { color: #4338CA; } .sc-ui .as-fees .as-sub-foot td.is-pay { color: #111827; }
        /* same columns in every semester table of the pop-up */
        .sc-ui .as-fm-sem .as-fees { table-layout: fixed; min-width: 760px; }
        .sc-ui .as-fm-sem .as-fees th:nth-child(1) { width: 25%; }
        .sc-ui .as-fm-sem .as-fees th:nth-child(2), .sc-ui .as-fm-sem .as-fees th:nth-child(3), .sc-ui .as-fm-sem .as-fees th:nth-child(5), .sc-ui .as-fm-sem .as-fees th:nth-child(6) { width: 11%; }
        .sc-ui .as-fm-sem .as-fees th:nth-child(4) { width: 14%; }
        .sc-ui .as-fm-sem .as-fees th:nth-child(7) { width: 17%; text-align: left; }
        .sc-ui .as-fm-sem .as-fees td { overflow: hidden; text-overflow: ellipsis; }
        .sc-ui .as-fm-grand { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); border-radius: 14px; overflow: hidden; border: 1px solid #1E1B4B; margin-top: 4px; }
        .sc-ui .as-fm-grand > div { display: flex; flex-direction: column; padding: 10px 14px; background: #F8FAFC; border-right: 1px solid #E2E8F0; }
        .sc-ui .as-fm-grand span { font-size: 11px; color: #64748B; } .sc-ui .as-fm-grand b { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 16px; }
        .sc-ui .as-fm-grand .is-pay { background: #1E1B4B; border-right: 0; } .sc-ui .as-fm-grand .is-pay span { color: rgba(255, 255, 255, .7); } .sc-ui .as-fm-grand .is-pay b { color: #FDE68A; }
        @media (max-width: 991px) { .sc-ui .as-fm-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); } .sc-ui .as-fm-card.is-pay { grid-column: 1 / -1; } .sc-ui .as-fm-grand { grid-template-columns: repeat(2, minmax(0, 1fr)); } .sc-ui .as-fm-schol { display: none; } }
        .sc-ui .as-num { display: inline-flex; width: 22px; height: 22px; border-radius: 50%; background: #F26522; color: #fff; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; margin-right: 4px; flex-shrink: 0; }
        .sc-ui .as-covers { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; }
        .sc-ui .as-covers span { width: 22px; height: 22px; border-radius: 6px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; background: #F3F4F6; color: #9CA3AF; }
        .sc-ui .as-covers span.is-on { background: #4338CA; color: #fff; }
        .sc-ui .as-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 18px; background: #FAFAFB; border-bottom: 1px solid #F1F2F4; }
        .sc-ui .as-check-all { display: inline-flex; gap: 8px; align-items: center; font-size: 13px; font-weight: 600; cursor: pointer; }
        .sc-ui .as-legend { display: flex; gap: 16px; flex-wrap: wrap; padding: 8px 18px; font-size: 11.5px; color: #6B7280; border-bottom: 1px solid #F1F2F4; }
        .sc-ui .as-legend span { display: inline-flex; align-items: center; gap: 5px; }
        .sc-ui .as-dot { width: 9px; height: 9px; border-radius: 3px; display: inline-block; }
        .sc-ui .as-dot-fee { background: #94A3B8; } .sc-ui .as-dot-other { background: #F59E0B; } .sc-ui .as-dot-this { background: #4F46E5; } .sc-ui .as-dot-pay { background: #111827; }
        .sc-ui .as-list { display: flex; flex-direction: column; }
        .sc-ui .as-row { border-bottom: 1px solid #F1F2F4; transition: background .15s; }
        .sc-ui .as-row:hover { background: #FCFCFF; }
        .sc-ui .as-row.is-picked { background: #F5F7FF; box-shadow: inset 3px 0 0 #4F46E5; }
        .sc-ui .as-row.is-has { background: #FBFDFB; }
        .sc-ui .as-row-main { display: grid; grid-template-columns: 32px minmax(180px, 1.1fr) minmax(0, 2.4fr) auto; gap: 12px; align-items: center; padding: 12px 18px; }
        .sc-ui .as-pick { display: flex; justify-content: center; }
        .sc-ui .as-pick .form-check-input { width: 18px; height: 18px; cursor: pointer; }
        .sc-ui .as-who { display: flex; gap: 10px; align-items: center; min-width: 0; }
        .sc-ui .as-metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 6px; }
        .sc-ui .as-m { display: flex; flex-direction: column; padding: 6px 10px; border-radius: 10px; background: #F8FAFC; border-left: 3px solid #CBD5E1; min-width: 0; }
        .sc-ui .as-m span { font-size: 10.5px; text-transform: uppercase; letter-spacing: .03em; color: #6B7280; white-space: nowrap; }
        .sc-ui .as-m b { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 13.5px; color: #111827; white-space: nowrap; }
        .sc-ui .as-m small { font-size: 10.5px; color: #6B7280; white-space: nowrap; }
        .sc-ui .as-m-other { border-left-color: #F59E0B; } .sc-ui .as-m-other b { color: #B45309; }
        .sc-ui .as-m-this { border-left-color: #4F46E5; background: #F5F7FF; } .sc-ui .as-m-this b { color: #4338CA; }
        .sc-ui .as-m-pay { border-left-color: #111827; background: #F3F4F6; }
        .sc-ui .as-act { display: flex; gap: 6px; justify-content: flex-end; }
        .sc-ui .as-detail { padding: 4px 18px 16px 62px; }
        .sc-ui .as-fees { width: 100%; font-size: 12.5px; border: 1px solid #E5E7EB; border-radius: 12px; border-collapse: separate; border-spacing: 0; overflow: hidden; }
        .sc-ui .as-fees thead th { background: #F9FAFB; font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; color: #6B7280; padding: 7px 10px; text-align: right; white-space: nowrap; }
        .sc-ui .as-fees th:first-child, .sc-ui .as-fees td:first-child { text-align: left; }
        .sc-ui .as-fees td { padding: 6px 10px; text-align: right; border-top: 1px solid #F3F4F6; font-family: 'IBM Plex Mono', ui-monospace, monospace; white-space: nowrap; }
        .sc-ui .as-fees td:first-child { font-family: inherit; color: #374151; white-space: normal; }
        .sc-ui .as-fees td.as-status { font-family: inherit; font-size: 11.5px; color: #6B7280; text-align: left; white-space: normal; }
        .sc-ui .as-fees .as-group td { background: #FAFAFB; font-family: inherit; text-align: left; font-size: 12.5px; padding: 7px 10px; }
        .sc-ui .as-fees .as-group.is-covered td { background: #F5F7FF; }
        .sc-ui .as-fees .as-sub td { background: #FCFCFD; font-weight: 600; color: #374151; font-size: 12px; }
        .sc-ui .as-fees .as-sub td:first-child { color: #6B7280; font-weight: 500; }
        .sc-ui .as-fees tfoot td { background: #1E1B4B; color: #fff; font-weight: 700; border-top: 0; }
        .sc-ui .as-fees tfoot td:first-child { color: #fff; }
        .sc-ui .as-fees tfoot td.is-other { color: #FCD34D; } .sc-ui .as-fees tfoot td.is-this { color: #C7D2FE; } .sc-ui .as-fees tfoot td.is-pay { color: #FDE68A; }
        .sc-ui .as-fees .is-other { color: #B45309; } .sc-ui .as-fees .is-this { color: #4338CA; font-weight: 700; } .sc-ui .as-fees .is-pay { font-weight: 700; color: #111827; }
        .sc-ui .as-fees .is-this small { font-size: 10px; font-weight: 500; color: #818CF8; font-family: inherit; }
        .sc-ui .as-fees tr.is-upcoming td { color: #6B7280; font-style: italic; }
        .sc-ui .as-fees tr.is-upcoming td.is-this { color: #6366F1; }
        .sc-ui .as-fees tr.is-waived td { text-decoration: line-through; color: #9CA3AF; }
        .sc-ui .as-detail-note { font-size: 11.5px; color: #6B7280; margin-top: 8px; }
        .sc-ui .as-review { position: sticky; top: 80px; overflow: hidden; }
        .sc-ui .as-review-head { display: flex; align-items: center; gap: 10px; padding: 14px 18px; border-bottom: 1px solid #F1F2F4; background: linear-gradient(180deg, #FAFAFF, #fff); }
        .sc-ui .as-review-empty { display: flex; gap: 12px; align-items: flex-start; padding: 18px; font-size: 13px; color: #6B7280; }
        .sc-ui .as-review-empty i { font-size: 28px; color: #C7D2FE; }
        .sc-ui .as-totals { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding: 14px 18px; }
        .sc-ui .as-totals > div { display: flex; flex-direction: column; padding: 8px 10px; border-radius: 10px; background: #F8FAFC; border-left: 3px solid #CBD5E1; }
        .sc-ui .as-totals span { font-size: 11px; color: #6B7280; }
        .sc-ui .as-totals b { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 15px; color: #111827; }
        .sc-ui .as-totals .as-m-other { border-left-color: #F59E0B; } .sc-ui .as-totals .as-m-other b { color: #B45309; }
        .sc-ui .as-totals .as-m-this { border-left-color: #4F46E5; background: #F5F7FF; } .sc-ui .as-totals .as-m-this b { color: #4338CA; }
        .sc-ui .as-total-pay { grid-column: 1 / -1; background: #1E1B4B !important; border-left-color: #FBBF24 !important; }
        .sc-ui .as-total-pay span { color: rgba(255, 255, 255, .7); } .sc-ui .as-total-pay b { color: #fff; font-size: 20px; }
        .sc-ui .as-picked { max-height: 300px; overflow-y: auto; padding: 0 12px 8px; }
        .sc-ui .as-picked-row { display: flex; align-items: center; gap: 8px; padding: 8px 6px; border-bottom: 1px dashed #EEF0F3; }
        .sc-ui .as-picked-row:last-child { border-bottom: 0; }
        .sc-ui .as-this-amt { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-weight: 700; color: #4338CA; font-size: 13px; }
        .sc-ui .as-assign-btn { background: linear-gradient(135deg, #FBBF24, #F59E0B); color: #451A03; font-weight: 700; border: 0; padding: 10px; }
        .sc-ui .as-assign-btn:hover { color: #451A03; filter: brightness(1.05); }
        .sc-ui .as-confirm { display: flex; flex-direction: column; border: 1px solid #E5E7EB; border-radius: 12px; overflow: hidden; }
        .sc-ui .as-confirm > div { display: flex; justify-content: space-between; padding: 8px 14px; font-size: 13.5px; border-bottom: 1px solid #F3F4F6; }
        .sc-ui .as-confirm > div:last-child { border-bottom: 0; }
        .sc-ui .as-confirm b { font-family: 'IBM Plex Mono', ui-monospace, monospace; }
        .sc-ui .as-confirm .is-this b { color: #4338CA; }
        .sc-ui .as-confirm .is-pay { background: #1E1B4B; color: #fff; } .sc-ui .as-confirm .is-pay b { font-size: 16px; }
        @media (max-width: 1199px) {
            .sc-ui .as-row-main { grid-template-columns: 32px minmax(0, 1fr) auto; }
            .sc-ui .as-metrics { grid-column: 2 / -1; grid-row: 2; grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .sc-ui .as-detail { padding-left: 18px; }
            .sc-ui .as-review { position: static; }
        }
    </style>
    <style>
        .sc-ui .as-filters { padding: 14px 18px; }
        .sc-ui .as-filters-grid { display: grid; grid-template-columns: minmax(220px, 2fr) repeat(auto-fit, minmax(150px, 1fr)); gap: 10px 12px; align-items: end; }
        .sc-ui .as-f { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
        .sc-ui .as-f .sc-search { flex: none; max-width: none; width: 100%; }
        .sc-ui .as-m small { white-space: normal !important; line-height: 1.25; }
        .sc-ui .as-m small.as-after { display: block; margin-top: 2px; }
        .sc-ui .as-f-label { font-size: 11.5px; font-weight: 700; color: #6B7280; text-transform: uppercase; letter-spacing: .04em; margin: 0; }
        .sc-ui .as-f-schol .as-f-label { color: #4338CA; }
        .sc-ui .as-f-schol .form-select { border-color: #C7D2FE; background-color: #F5F7FF; font-weight: 600; }
        .sc-ui .as-f-schol .form-select.is-chosen { border: 2px solid #4F46E5; }
        .sc-ui .as-filters-foot { margin-top: 6px; }
        .sc-ui .as-hint { padding: 10px 18px; font-size: 13px; color: #3730A3; background: #F5F7FF; border-bottom: 1px solid #E0E7FF; }
        .sc-ui .as-pick-off { width: 16px; height: 16px; border-radius: 4px; border: 1.5px dashed #D1D5DB; display: inline-block; }
        .sc-ui .as-has-chips { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 3px; }
        .sc-ui .as-has-chips .sc-code { font-size: 10.5px; }
        .sc-ui .as-metrics-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .sc-ui .as-rv { display: flex; flex-wrap: wrap; gap: 4px 16px; padding: 0 18px 12px 62px; font-size: 12px; color: #4B5563; }
        .sc-ui .as-quick { display: flex; flex-direction: column; padding: 6px; max-height: 520px; overflow-y: auto; }
        .sc-ui .as-quick-item { display: flex; align-items: center; gap: 10px; width: 100%; padding: 8px 10px; border: 0; background: #fff; border-radius: 12px; text-align: left; transition: background .15s; }
        .sc-ui .as-quick-item:hover { background: #F5F7FF; }
        .sc-ui .as-quick-item .sc-value { width: 54px; height: 46px; border-radius: 10px; flex-shrink: 0; }
        .sc-ui .as-quick-item .sc-value b { font-size: 15px; }
        .sc-ui .as-chosen { display: flex; gap: 12px; align-items: center; padding: 14px 18px; background: linear-gradient(135deg, #EEF2FF, #F5F3FF); border-bottom: 1px solid #E0E7FF; }
        .sc-ui .as-chosen .sc-value { width: 64px; height: 58px; flex-shrink: 0; }
        .sc-ui .as-totals .as-total-pay { grid-column: 1 / -1; }
        @media (max-width: 767px) { .sc-ui .as-filters-grid { grid-template-columns: 1fr 1fr; } .sc-ui .as-f-search, .sc-ui .as-f-schol { grid-column: 1 / -1; } .sc-ui .as-rv { padding-left: 18px; } }
    </style>

    <script>
        document.addEventListener('livewire:load', function () {
            [['assign', 'assignModal'], ['revoke', 'revokeModal'], ['fees', 'feesModal']].forEach(([name, id]) => {
                window.addEventListener(`open-${name}-modal`, () => bootstrap.Modal.getOrCreateInstance(document.getElementById(id)).show());
                window.addEventListener(`close-${name}-modal`, () => { const m = bootstrap.Modal.getInstance(document.getElementById(id)); if (m) { m.hide(); } });
            });
        });
    </script>
</div>

