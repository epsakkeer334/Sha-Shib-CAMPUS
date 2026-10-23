<div class="content sc-ui">
    @php
        $fmt = fn ($v) => money_inr($v, false);
        $periodWord = optional(optional($s)->course)->period_label ?: 'Semester';
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
        <a href="{{ route('admin.scholarships') }}" class="btn btn-light border"><i class="ti ti-award me-1"></i> All scholarships</a>
    </div>

    @if(!$s)
        <!-- Step 1: choose a scholarship -->
        <div class="as-step-title"><span class="as-num">1</span> Choose a scholarship <small class="text-muted fw-normal ms-1">{{ $scholarships->count() }} active</small></div>
        @if($scholarships->isEmpty())
            <div class="sc-panel text-center py-5">
                <span class="sc-hero-icon mb-3" style="width: 64px; height: 64px;"><i class="ti ti-award"></i></span>
                <div class="fw-semibold fs-16">No active scholarships</div>
                <div class="small text-muted mb-3">Create a scholarship first, then assign it to students here.</div>
                <a href="{{ route('admin.scholarships') }}" class="btn btn-primary"><i class="ti ti-circle-plus me-1"></i> Scholarships</a>
            </div>
        @else
            @php $cardFiltered = trim($cardSearch) !== '' || $cardInstitute || $cardCourse || $cardType || $cardOnlyEligible; @endphp
            <div class="as-filterbar mb-3">
                <div class="sc-search"><i class="ti ti-search"></i><input type="search" class="form-control" placeholder="Search scholarship, code or course…" wire:model.debounce.300ms="cardSearch" aria-label="Search scholarships"></div>
                @if($cardInstitutes->count() > 1)
                    <select class="form-select" wire:model="cardInstitute" aria-label="Institute">
                        <option value="">All institutes</option>
                        @foreach($cardInstitutes as $inst)<option value="{{ $inst->id }}">{{ $inst->name }}</option>@endforeach
                    </select>
                @endif
                <select class="form-select" wire:model="cardCourse" aria-label="Course">
                    <option value="">All courses</option>
                    @foreach($cardCourses as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $c->code }})</option>@endforeach
                </select>
                <div class="sc-seg" role="group" aria-label="Discount type">
                    @foreach(['' => 'All types', 'percentage' => '% Percentage', 'fixed' => '₹ Fixed'] as $k => $l)
                        <button type="button" class="{{ $cardType === $k ? 'is-on' : '' }}" wire:click="$set('cardType', '{{ $k }}')">{{ $l }}</button>
                    @endforeach
                </div>
                <label class="as-toggle"><input type="checkbox" class="form-check-input m-0" wire:model="cardOnlyEligible"> With eligible students</label>
                @if($cardFiltered)<button type="button" class="btn btn-sm btn-link text-muted" wire:click="clearCardFilters"><i class="ti ti-x"></i> Clear</button>@endif
                <span class="as-count ms-auto">{{ $cards->count() }} of {{ $scholarships->count() }}</span>
            </div>
            @if($cards->isEmpty())
                <div class="sc-panel text-center py-5">
                    <i class="ti ti-filter-off fs-1 text-muted d-block mb-2"></i>
                    <div class="fw-semibold">No scholarship matches these filters</div>
                    <button type="button" class="btn btn-sm btn-light border mt-2" wire:click="clearCardFilters">Clear filters</button>
                </div>
            @endif
            <div class="sc-grid">
                @foreach($cards as $card)
                    @php $cc = $cardCounts[$card->id] ?? ['eligible' => 0, 'assigned' => 0]; @endphp
                    <button type="button" class="sc-card as-choose" wire:click="chooseScholarship({{ $card->id }})" wire:key="card-{{ $card->id }}">
                        <div class="sc-card-top">
                            <div class="sc-value {{ $card->type === 'percentage' ? 'is-pct' : 'is-fixed' }}">
                                @if($card->type === 'percentage')<b>{{ rtrim(rtrim(number_format((float) $card->value, 2), '0'), '.') }}<small>%</small></b><span>off</span>
                                @else<b><small>₹</small>{{ number_format((float) $card->value) }}</b><span>/ {{ strtolower(optional($card->course)->period_label ?: 'semester') }}</span>@endif
                            </div>
                            <div class="min-w-0 flex-grow-1 text-start">
                                <span class="sc-code">{{ $card->code }}</span>
                                <h5 class="sc-name text-truncate" title="{{ $card->name }}">{{ $card->name }}</h5>
                                <div class="small text-muted text-truncate">{{ optional($card->course)->name }} · {{ $card->batch ? 'Batch ' . $card->batch->code : 'every batch' }}</div>
                            </div>
                        </div>
                        <div class="sc-periods">
                            @foreach(range(1, max(1, (int) optional($card->course)->total_periods)) as $n)
                                <span class="{{ in_array($n, $card->periods, true) ? 'is-on' : '' }}">{{ $n }}</span>
                            @endforeach
                        </div>
                        <div class="sc-card-foot">
                            <div class="sc-metric text-start"><b class="{{ $cc['eligible'] ? 'text-primary' : '' }}">{{ $cc['eligible'] }}</b><span>eligible</span></div>
                            <div class="sc-metric text-start"><b>{{ $cc['assigned'] }}</b><span>assigned</span></div>
                            <span class="as-choose-cta ms-auto">{{ $cc['eligible'] ? 'Select students' : 'View' }} <i class="ti ti-arrow-right"></i></span>
                        </div>
                    </button>
                @endforeach
            </div>
        @endif
    @else
        <!-- Selected scholarship -->
        <div class="as-selected mb-3">
            <div class="sc-value {{ $s->type === 'percentage' ? 'is-pct' : 'is-fixed' }}">
                @if($s->type === 'percentage')<b>{{ rtrim(rtrim(number_format((float) $s->value, 2), '0'), '.') }}<small>%</small></b><span>off</span>
                @else<b><small>₹</small>{{ number_format((float) $s->value) }}</b><span>/ {{ strtolower($periodWord) }}</span>@endif
            </div>
            <div class="min-w-0 flex-grow-1">
                <div class="fw-bold fs-15">{{ $s->name }} <span class="sc-code ms-1">{{ $s->code }}</span></div>
                <div class="small text-muted">{{ optional($s->course)->name }} · {{ $s->batch ? 'Batch ' . $s->batch->code : 'every batch' }}{{ $s->academicYear ? ' · ' . $s->academicYear->name : '' }}</div>
                <div class="as-covers mt-1">
                    @foreach(range(1, max(1, (int) optional($s->course)->total_periods)) as $n)
                        <span class="{{ in_array($n, $s->periods, true) ? 'is-on' : '' }}" title="{{ optional($s->course)->periodName($n) }}">{{ $n }}</span>
                    @endforeach
                    <small class="text-muted ms-1">{{ $s->periods_label }}{{ $s->applies_to === 'all_fees' ? ' + one-time fees' : ' · semester fees only' }}</small>
                </div>
            </div>
            @if($scholarships->count() > 1)
                <select class="form-select form-select-sm as-switch" wire:change="chooseScholarship($event.target.value)" aria-label="Switch scholarship">
                    @foreach($scholarships as $opt)<option value="{{ $opt->id }}" @if((int) $opt->id === (int) $s->id) selected @endif>{{ $opt->name }} ({{ $opt->code }})</option>@endforeach
                </select>
            @endif
            <button type="button" class="btn btn-sm btn-light border" wire:click="changeScholarship"><i class="ti ti-layout-grid me-1"></i> All scholarships</button>
        </div>

        <div class="row g-3">
            <!-- Step 2: students -->
            <div class="{{ $tab === 'eligible' ? 'col-xxl-8' : 'col-12' }}">
                <div class="sc-panel">
                    <div class="sc-panel-head">
                        <div class="sc-tabs">
                            <button type="button" class="{{ $tab === 'eligible' ? 'is-on' : '' }}" wire:click="setTab('eligible')"><i class="ti ti-users me-1"></i> Eligible <span class="badge bg-primary-transparent text-primary ms-1">{{ $counts['eligible'] }}</span></button>
                            <button type="button" class="{{ $tab === 'assigned' ? 'is-on' : '' }}" wire:click="setTab('assigned')"><i class="ti ti-award me-1"></i> Assigned <span class="badge bg-success-transparent text-success ms-1">{{ $counts['assigned'] }}</span></button>
                        </div>
                        @if($tab === 'eligible')
                            <div class="sc-search ms-md-auto"><i class="ti ti-search"></i><input type="search" class="form-control form-control-sm" placeholder="Name, ER number, email…" wire:model.debounce.400ms="search"></div>
                        @endif
                    </div>
                    @if($tab === 'eligible')
                        <div class="as-filterbar as-filterbar-sm">
                            <span class="as-filter-label"><i class="ti ti-filter"></i> Filter</span>
                            @if($batches->isNotEmpty())
                                <select class="form-select form-select-sm" wire:model="batch" aria-label="Batch">
                                    <option value="">All batches</option>
                                    @foreach($batches as $b)<option value="{{ $b->id }}">{{ $b->code }}</option>@endforeach
                                </select>
                            @endif
                            <select class="form-select form-select-sm" wire:model="semester" aria-label="Current semester">
                                <option value="">Any {{ strtolower($periodWord) }}</option>
                                @foreach(range(1, $semesterCount) as $n)<option value="{{ $n }}">Now in {{ optional($s->course)->periodName($n) }}</option>@endforeach
                            </select>
                            <select class="form-select form-select-sm" wire:model="feeState" aria-label="Fee status">
                                <option value="">Any fee status</option>
                                <option value="open">Has fees to pay</option>
                                <option value="settled">Nothing to pay now</option>
                            </select>
                            @if(trim($search) !== '' || $batch || $semester || $feeState)
                                <button type="button" class="btn btn-sm btn-link text-muted" wire:click="clearStudentFilters"><i class="ti ti-x"></i> Clear filters</button>
                            @endif
                            <span class="as-count ms-auto">{{ $students instanceof \Illuminate\Pagination\AbstractPaginator ? $students->total() : 0 }} {{ \Illuminate\Support\Str::plural('student', $students instanceof \Illuminate\Pagination\AbstractPaginator ? $students->total() : 0) }}</span>
                        </div>
                    @endif

                    @if($tab === 'eligible')
                        @php
                            $pageIds = $students->pluck('id')->map(fn ($id) => (string) $id)->all();
                            $pageFree = array_values(array_diff($pageIds, array_map('strval', $have)));
                            $pageAllPicked = $pageFree && !array_diff($pageFree, $selected);
                        @endphp
                        <div class="as-toolbar">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                @if($s->status && $pageFree)
                                    <label class="as-check-all"><input type="checkbox" class="form-check-input m-0" @if($pageAllPicked) checked @endif wire:click="selectPage({{ json_encode($pageFree) }})"> Select this page</label>
                                    @if($counts['eligible'] > count($pageFree))
                                        <button type="button" class="btn btn-sm btn-link px-1" wire:click="selectAllEligible">Select all {{ $counts['eligible'] }} eligible</button>
                                    @endif
                                @endif
                            </div>
                        </div>

                        <div class="as-legend">
                            <span><i class="as-dot as-dot-fee"></i> Total fees</span>
                            <span><i class="as-dot as-dot-other"></i> Existing discounts</span>
                            <span><i class="as-dot as-dot-this"></i> This scholarship</span>
                            <span><i class="as-dot as-dot-pay"></i> Final payable</span>
                        </div>

                        <div class="as-list">
                            @forelse($students as $st)
                                @php
                                    $has = in_array($st->id, $have);
                                    $picked = in_array((string) $st->id, $selected, true);
                                    $struct = $structures[$st->id] ?? ['groups' => [], 'totals' => []];
                                    $t = $struct['totals'];
                                @endphp
                                <div class="as-row {{ $picked ? 'is-picked' : '' }} {{ $has ? 'is-has' : '' }}" wire:key="row-{{ $st->id }}">
                                    <div class="as-row-main">
                                        <div class="as-pick">
                                            @if(!$has && $s->status)
                                                <input type="checkbox" class="form-check-input" @if($picked) checked @endif wire:click="toggle({{ $st->id }})" aria-label="Select {{ $st->full_name }}">
                                            @else
                                                <i class="ti ti-award text-success" title="Already has this scholarship"></i>
                                            @endif
                                        </div>
                                        <div class="as-who">
                                            <span class="sc-av">{{ $st->initials }}</span>
                                            <div class="min-w-0">
                                                <a href="{{ route('admin.students.fees', $st->id) }}" class="fw-semibold text-dark d-block text-truncate">{{ $st->full_name }}</a>
                                                <div class="small text-muted"><span class="font-monospace">{{ $st->er_number }}</span>{{ optional($st->batch)->code ? ' · ' . $st->batch->code : '' }}</div>
                                                @if($st->current_period_label)<div class="small text-muted">{{ $st->current_period_label }}</div>@endif
                                            </div>
                                        </div>
                                        <div class="as-metrics">
                                            <div class="as-m"><span>Total fees</span><b>{{ $fmt($t['fees'] ?? 0) }}</b></div>
                                            <div class="as-m as-m-other"><span>Existing disc.</span><b>{{ ($t['existing'] ?? 0) > 0 ? '− ' . $fmt($t['existing']) : '—' }}</b></div>
                                            <div class="as-m as-m-this">
                                                <span>Scholarship</span>
                                                @if($has)<b class="text-success">Assigned</b>
                                                @else<b>{{ ($t['this_now'] ?? 0) > 0 ? '− ' . $fmt($t['this_now']) : '—' }}</b>
                                                    @if(($t['this_later'] ?? 0) > 0)<small>+ {{ $fmt($t['this_later']) }} later</small>@endif
                                                @endif
                                            </div>
                                            <div class="as-m as-m-pay"><span>Final payable</span><b>{{ $fmt($t['payable_now'] ?? 0) }}</b>
                                                @if(($t['payable_later'] ?? 0) > 0)<small>+ {{ $fmt($t['payable_later']) }} upcoming</small>@endif</div>
                                        </div>
                                        <div class="as-act">
                                            <button type="button" class="btn btn-sm btn-light border" wire:click="showFees({{ $st->id }})" title="Complete fee structure">
                                                <i class="ti ti-receipt-2"></i><span class="d-none d-md-inline ms-1">Fees</span>
                                            </button>
                                            @if(!$has && $s->status)
                                                <button type="button" class="btn btn-sm btn-primary" wire:click="confirmAssign({{ $st->id }})">Assign</button>
                                            @endif
                                        </div>
                                    </div>

                                </div>
                            @empty
                                <div class="text-center text-muted py-5">
                                    <i class="ti ti-users-minus fs-1 d-block mb-2 opacity-50"></i>
                                    No admitted students of {{ optional($s->course)->name }}{{ $s->batch ? ' (batch ' . $s->batch->code . ')' : '' }}{{ $search ? ' match the search' : ' yet' }}.
                                </div>
                            @endforelse
                        </div>
                        @if($students instanceof \Illuminate\Pagination\AbstractPaginator && $students->hasPages())<div class="px-3 py-2 border-top">{{ $students->links() }}</div>@endif
                    @else
                        <div class="table-responsive">
                            <table class="sc-students">
                                <thead><tr><th>Student</th><th>Status</th><th class="text-end">Discount</th><th>Given by</th><th class="text-end">Action</th></tr></thead>
                                <tbody>
                                    @forelse($assigned as $a)
                                        <tr wire:key="a-{{ $a->id }}" class="{{ $a->status === 'active' ? '' : 'is-muted' }}">
                                            <td>
                                                <div class="d-flex gap-2 align-items-center">
                                                    <span class="sc-av">{{ optional($a->student)->initials }}</span>
                                                    <div class="min-w-0">
                                                        <a href="{{ route('admin.students.fees', $a->student_id) }}" class="fw-semibold text-dark">{{ optional($a->student)->full_name }}</a>
                                                        <div class="small text-muted font-monospace">{{ optional($a->student)->er_number }} · {{ optional(optional($a->student)->batch)->code }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                @if($a->status === 'active')<span class="sc-tag sc-tag-ok"><i class="ti ti-circle-check"></i> Active</span>
                                                @else<span class="sc-tag sc-tag-off"><i class="ti ti-ban"></i> Revoked</span>
                                                    <div class="small text-muted mt-1">{{ optional($a->revoker)->name }} · {{ optional($a->revoked_at)->format('d M Y') }}</div>@endif
                                            </td>
                                            <td class="text-end font-monospace fw-bold text-primary">{{ $fmt($a->discount_total ?? 0) }}</td>
                                            <td>
                                                <div class="small">{{ optional($a->assigner)->name }}</div>
                                                <div class="small text-muted">{{ optional($a->assigned_at)->format('d M Y, h:i A') }}</div>
                                                @if($a->remarks)<div class="small text-muted fst-italic text-truncate" style="max-width: 260px;" title="{{ $a->remarks }}">“{{ $a->remarks }}”</div>@endif
                                            </td>
                                            <td class="text-end">
                                                @if($a->status === 'active')
                                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="openRevoke({{ $a->id }})"><i class="ti ti-award-off me-1"></i> Revoke</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted py-5">Nobody has this scholarship yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if($assigned instanceof \Illuminate\Pagination\AbstractPaginator && $assigned->hasPages())<div class="px-3 py-2">{{ $assigned->links() }}</div>@endif
                    @endif
                </div>
            </div>

            <!-- Step 3: review the selection -->
            @if($tab === 'eligible')
                <div class="col-xxl-4">
                    <div class="sc-panel as-review">
                        <div class="as-review-head">
                            <span class="as-num">2</span>
                            <div><div class="fw-bold">Review selection</div><small class="text-muted">{{ $summary && $summary['count'] ? $summary['count'] . ' ' . \Illuminate\Support\Str::plural('student', $summary['count']) . ' selected' : 'Tick students on the left' }}</small></div>
                            @if($selected)<button type="button" class="btn btn-sm btn-link text-muted ms-auto" wire:click="clearSelection">Clear</button>@endif
                        </div>
                        @if(!$summary || !$summary['count'])
                            <div class="as-review-empty">
                                <i class="ti ti-list-check"></i>
                                <div>Select one or more students to see the combined fees, the scholarship amount and the final payable before you assign.</div>
                            </div>
                        @else
                            <div class="as-totals">
                                <div><span>Total fees</span><b>{{ $fmt($summary['fees']) }}</b></div>
                                <div class="as-m-other"><span>Existing discounts</span><b>{{ $summary['existing'] > 0 ? '− ' . $fmt($summary['existing']) : '—' }}</b></div>
                                <div class="as-m-this"><span>This scholarship now</span><b>− {{ $fmt($summary['this_now']) }}</b></div>
                                <div class="as-m-this"><span>…later semesters</span><b>{{ $summary['this_later'] > 0 ? '− ' . $fmt($summary['this_later']) : '—' }}</b></div>
                                <div><span>Already paid</span><b>{{ $summary['paid'] > 0 ? $fmt($summary['paid']) : '—' }}</b></div>
                                <div class="as-m-pay as-total-pay"><span>Final payable now</span><b>{{ $fmt($summary['payable_now']) }}</b></div>
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
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- Complete fee structure -->
    <div class="modal fade" id="feesModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg as-fm">
                @if($feeStudent && $feeStructure)
                    @php $ft = $feeStructure['totals']; $fHas = in_array($feeStudent->id, $have); $fPicked = in_array((string) $feeStudent->id, $selected, true); @endphp
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
                        <div class="as-fm-schol"><i class="ti ti-award"></i><div><small>Scholarship</small><b>{{ optional($s)->name }}</b><span>{{ optional($s)->value_label }}</span></div></div>
                        <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="as-fm-cards">
                            <div class="as-fm-card"><span><i class="ti ti-receipt"></i> Total fees</span><b>{{ $fmt($ft['fees']) }}</b><small>all semesters</small></div>
                            <div class="as-fm-card is-other"><span><i class="ti ti-discount-2"></i> Existing discounts</span><b>{{ $ft['existing'] > 0 ? '− ' . $fmt($ft['existing']) : '—' }}</b><small>other scholarships</small></div>
                            <div class="as-fm-card is-this"><span><i class="ti ti-award"></i> This scholarship</span><b>{{ $fHas ? 'Assigned' : '− ' . $fmt($ft['this_now']) }}</b><small>{{ $fHas ? 'already given' : ($ft['this_later'] > 0 ? '+ ' . $fmt($ft['this_later']) . ' on later semesters' : 'on the charged fees') }}</small></div>
                            <div class="as-fm-card is-paid"><span><i class="ti ti-circle-check"></i> Already paid</span><b>{{ $fmt($ft['paid']) }}</b><small>confirmed payments</small></div>
                            <div class="as-fm-card is-pay"><span><i class="ti ti-wallet"></i> Final payable</span><b>{{ $fmt($ft['payable_now']) }}</b><small>{{ $ft['payable_later'] > 0 ? '+ ' . $fmt($ft['payable_later']) . ' upcoming' : 'nothing upcoming' }}</small></div>
                        </div>

                        @foreach($feeStructure['groups'] as $g)
                            <div class="as-fm-sem {{ $g['covered'] ? 'is-covered' : '' }} {{ $g['upcoming'] ? 'is-upcoming' : '' }}">
                                <div class="as-fm-sem-head">
                                    <span class="as-fm-sem-icon"><i class="ti ti-{{ $g['upcoming'] ? 'calendar-time' : ($g['label'] === 'One-time fees' ? 'receipt' : 'calendar-check') }}"></i></span>
                                    <span class="fw-bold">{{ $g['label'] }}</span>
                                    @if($g['covered'])<span class="sc-tag sc-tag-has"><i class="ti ti-award"></i> covered by this scholarship</span>@endif
                                    @if($g['upcoming'])<span class="sc-tag sc-tag-no"><i class="ti ti-clock"></i> not charged yet</span>@endif
                                    <span class="ms-auto as-fm-sem-total">Payable <b>{{ $fmt($g['subtotal']['payable']) }}</b></span>
                                </div>
                                <div class="table-responsive">
                                    <table class="as-fees">
                                        <thead><tr><th>Fee</th><th>Amount</th><th>Existing disc.</th><th>This scholarship</th><th>Paid</th><th>Payable</th><th>Status</th></tr></thead>
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
                            <div class="is-pay"><span>Still to pay (incl. upcoming)</span><b>{{ $fmt($ft['payable_now'] + $ft['payable_later']) }}</b></div>
                        </div>
                        <div class="as-detail-note"><i class="ti ti-info-circle"></i> Fully paid fees are not discounted (no refunds). Fees not charged yet get the scholarship automatically when they are charged ("projected").</div>
                    </div>
                    <div class="modal-footer bg-light as-fm-foot">
                        <a href="{{ route('admin.students.fees', $feeStudent->id) }}" class="btn btn-light border me-auto"><i class="ti ti-external-link me-1"></i> Student fees page</a>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        @if(!$fHas && optional($s)->status)
                            <button type="button" class="btn {{ $fPicked ? 'btn-outline-secondary' : 'btn-outline-primary' }}" wire:click="toggle({{ $feeStudent->id }})">
                                <i class="ti ti-{{ $fPicked ? 'square-minus' : 'square-plus' }} me-1"></i> {{ $fPicked ? 'Remove from selection' : 'Add to selection' }}
                            </button>
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal" wire:click="confirmAssign({{ $feeStudent->id }})"><i class="ti ti-award me-1"></i> Assign to {{ $feeStudent->first_name }}</button>
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
                    <div class="is-pay"><span>Final payable now</span><b>{{ $fmt($confirm['payable'] ?? 0) }}</b></div>
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

    <script>
        document.addEventListener('livewire:load', function () {
            [['assign', 'assignModal'], ['revoke', 'revokeModal'], ['fees', 'feesModal']].forEach(([name, id]) => {
                window.addEventListener(`open-${name}-modal`, () => bootstrap.Modal.getOrCreateInstance(document.getElementById(id)).show());
                window.addEventListener(`close-${name}-modal`, () => { const m = bootstrap.Modal.getInstance(document.getElementById(id)); if (m) { m.hide(); } });
            });
        });
    </script>
</div>
