@php
    $statCards = [
        ['Fee lines', number_format($stats['lines']), 'ti ti-list-details', 'all', $stats['active'] . ' active · ' . $stats['inactive'] . ' inactive'],
        ['Courses configured', $stats['configured'] . ' / ' . $stats['offered'], 'ti ti-books', 'ok', 'Courses offered with a fee structure'],
        ['Without fees', number_format($stats['missing']), 'ti ti-alert-triangle', $stats['missing'] ? 'warn' : 'ok', $stats['missing'] ? 'Courses that still need fees' : 'Every course has fees'],
        ['Average course total', money_inr($stats['avgTotal'], false), 'ti ti-currency-rupee', 'info', 'Highest ' . money_inr($stats['maxTotal'], false)],
    ];
@endphp
<div class="content fs-ui">
    {{-- Header --}}
    <div class="fs-hero mb-3">
        <div class="min-w-0">
            <h2 class="mb-1 fw-bold">Fee structure</h2>
            <nav>
                <ol class="breadcrumb mb-1 sl-crumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Institute Management</li>
                    <li class="breadcrumb-item active">Fee structure</li>
                </ol>
            </nav>
            <div class="text-muted small">Fees charged for each course. Student dues are generated from these lines on admission.</div>
        </div>
        <button type="button" class="fs-hero-cta" wire:click="create"><i class="ti ti-circle-plus"></i> Add fee</button>
    </div>

    {{-- Summary --}}
    <div class="fs-cards mb-3">
        @foreach($statCards as [$label, $value, $icon, $tone, $hint])
            <div class="fs-card fs-card-{{ $tone }}">
                <span class="fs-card-top">
                    <span class="fs-card-label">{{ $label }}</span>
                    <span class="fs-card-icon"><i class="{{ $icon }}"></i></span>
                </span>
                <span class="fs-card-count">{{ $value }}</span>
                <span class="fs-card-hint text-truncate">{{ $hint }}</span>
            </div>
        @endforeach
    </div>

    {{-- Courses offered without any fee yet --}}
    @if($missing->isNotEmpty())
        <div class="fs-missing mb-3">
            <div class="d-flex align-items-start gap-2">
                <span class="fs-missing-icon"><i class="ti ti-alert-triangle"></i></span>
                <div class="min-w-0 flex-grow-1">
                    <div class="fw-semibold">{{ $missing->count() }} {{ \Illuminate\Support\Str::plural('course', $missing->count()) }} without a fee structure</div>
                    <div class="small">Students joining these courses get no dues and can't pass the fee gate until fees are added.</div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        @foreach($missing->take(8) as $ic)
                            <button type="button" class="fs-missing-chip" wire:click="create({{ $ic->course_id }}, {{ $ic->institute_id }})" title="Add fees for {{ $ic->course->name }}">
                                <span class="fs-code">{{ $ic->course->code }}</span>
                                <span class="text-truncate" style="max-width: 180px;">{{ $ic->course->name }}</span>
                                @if($isSuperAdmin)<span class="fs-inst-code">{{ optional($ic->institute)->code }}</span>@endif
                                <i class="ti ti-plus"></i>
                            </button>
                        @endforeach
                        @if($missing->count() > 8)<span class="small align-self-center">and {{ $missing->count() - 8 }} more</span>@endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="fs-panel">
        {{-- Filters --}}
        <div class="fs-filters">
            <div class="fs-search">
                <i class="ti ti-search"></i>
                <input type="search" class="form-control form-control-sm" placeholder="Fee name or course" wire:model.debounce.400ms="search" aria-label="Search">
            </div>
            @if($isSuperAdmin)
                <select class="form-select form-select-sm" wire:model="filterInstitute" aria-label="Institute">
                    <option value="">All institutes</option>
                    @foreach($institutes as $inst)<option value="{{ $inst->id }}">{{ $inst->name }}</option>@endforeach
                </select>
            @endif
            <select class="form-select form-select-sm" wire:model="filterCourse" aria-label="Course">
                <option value="">All courses</option>
                @foreach($filterCourses as $c)<option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>@endforeach
            </select>
            <select class="form-select form-select-sm" wire:model="filterDue" aria-label="Due">
                <option value="">Any due date</option>
                <option value="joining">Due on joining</option>
                <option value="later">Due after joining</option>
                <option value="fixed">Fixed due date</option>
            </select>
            <select class="form-select form-select-sm" wire:model="filterStatus" aria-label="Status">
                <option value="">Any status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            @if($hasFilters)
                <button type="button" class="btn btn-sm btn-light" wire:click="clearFilters"><i class="ti ti-x me-1"></i> Clear</button>
            @endif
            <span class="ms-auto d-inline-flex align-items-center gap-2">
                <span class="small text-muted">{{ number_format($groups->total()) }} {{ \Illuminate\Support\Str::plural('course', $groups->total()) }} · {{ number_format($lineCount) }} {{ \Illuminate\Support\Str::plural('fee', $lineCount) }}</span>
                @if($groups->count() > 1)
                    <button type="button" class="btn btn-sm btn-light border fs-toggle-all" onclick="fsToggleAll(true)"><i class="ti ti-arrows-maximize"></i> Expand all</button>
                    <button type="button" class="btn btn-sm btn-light border fs-toggle-all" onclick="fsToggleAll(false)"><i class="ti ti-arrows-minimize"></i> Collapse all</button>
                @endif
            </span>
        </div>

        <div class="fs-groups position-relative">
            <div class="fs-loading" wire:loading.delay.flex wire:target="search, filterInstitute, filterCourse, filterStatus, filterDue, clearFilters, toggleStatus, delete, gotoPage, nextPage, previousPage">
                <span class="spinner-border spinner-border-sm text-secondary"></span>
            </div>

            @forelse($groups as $g)
                @php
                    $key = "{$g->institute_id}-{$g->course_id}";
                    $lines = $feesByGroup->get($key, collect());
                    $first = $lines->first();
                    $sum = $groupTotals->get($key);
                    $nextDue = $sum && $sum->next_fixed_due ? \Carbon\Carbon::parse($sum->next_fixed_due) : null;
                    $collapseId = 'fsGroup' . $g->institute_id . '_' . $g->course_id;
                @endphp
                <section class="fs-course" wire:key="course-{{ $key }}">
                    {{-- Card header: click to open / close --}}
                    <div class="fs-course-head {{ $autoOpen ? '' : 'collapsed' }}" role="button" tabindex="0"
                         data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="{{ $autoOpen ? 'true' : 'false' }}" aria-controls="{{ $collapseId }}"
                         wire:ignore.self>
                        <i class="ti ti-chevron-right fs-chevron"></i>
                        <span class="fs-course-icon"><i class="ti ti-book-2"></i></span>
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="fs-code">{{ optional(optional($first)->course)->code ?? $g->course_code }}</span>
                                <span class="fs-course-name text-truncate">{{ optional(optional($first)->course)->name }}</span>
                                @if(($sum->inactive_count ?? 0) > 0)
                                    <span class="fs-chip fs-chip-muted" title="Inactive fee lines are not charged">{{ $sum->inactive_count }} inactive</span>
                                @endif
                            </div>
                            @if($isSuperAdmin)
                                <div class="fs-course-inst"><i class="ti ti-building"></i> {{ $g->institute_name }}
                                    @if(optional(optional($first)->institute)->code)<span class="fs-inst-code ms-1">{{ $first->institute->code }}</span>@endif
                                </div>
                            @endif
                        </div>
                        <div class="fs-course-stats">
                            <span class="fs-stat"><span>Fees</span><strong>{{ $sum->line_count ?? $lines->count() }}</strong></span>
                            <span class="fs-stat"><span>On joining</span><strong>{{ money_inr($sum->joining_total ?? 0, false) }}</strong></span>
                            <span class="fs-stat"><span>Next fixed due</span><strong>{{ $nextDue ? $nextDue->format('d M Y') : '—' }}</strong></span>
                            <span class="fs-stat fs-stat-total"><span>Course total</span><strong>{{ money_inr($sum->active_total ?? 0, false) }}</strong></span>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary fs-group-add" wire:click="create({{ $g->course_id }}, {{ $g->institute_id }})"
                                onclick="event.stopPropagation()" title="Add a fee to this course">
                            <i class="ti ti-plus"></i> Add fee
                        </button>
                    </div>

                    {{-- Fee lines --}}
                    <div id="{{ $collapseId }}" class="collapse {{ $autoOpen ? 'show' : '' }}" wire:ignore.self>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 fs-table">
                                <thead>
                                    <tr>
                                        <th style="width: 64px;">Order</th>
                                        <th>Fee</th>
                                        <th class="text-end">Amount</th>
                                        <th>Due</th>
                                        <th>Charged to</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($lines as $fee)
                                        <tr class="{{ $fee->status ? '' : 'fs-row-inactive' }}" wire:key="fee-{{ $fee->id }}">
                                            <td><span class="fs-order">{{ $fee->sort_order }}</span></td>
                                            <td><span class="fw-semibold">{{ $fee->fee_head }}</span></td>
                                            <td class="text-end"><span class="fs-amount">{{ money_inr($fee->amount, false) }}</span></td>
                                            <td>
                                                @if($fee->isFixedDue())
                                                    <span class="fs-chip {{ $fee->due_date->isPast() ? 'fs-chip-bad' : 'fs-chip-fixed' }}" title="Fixed due date for every student">
                                                        <i class="ti ti-calendar-event"></i> {{ $fee->due_label }}
                                                    </span>
                                                @elseif($fee->due_days)
                                                    <span class="fs-chip fs-chip-info"><i class="ti ti-calendar-time"></i> {{ $fee->due_label }}</span>
                                                @else
                                                    <span class="fs-chip fs-chip-warn"><i class="ti ti-calendar-check"></i> On joining</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($fee->dues_count)
                                                    <span class="small"><i class="ti ti-users text-muted"></i> {{ $fee->dues_count }} {{ \Illuminate\Support\Str::plural('student', $fee->dues_count) }}</span>
                                                @else
                                                    <span class="small text-muted">Not charged yet</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="form-check form-switch m-0" title="{{ $fee->status ? 'Click to deactivate' : 'Click to activate' }}">
                                                    <input class="form-check-input" type="checkbox" role="switch" id="feeSwitch{{ $fee->id }}" @checked($fee->status) wire:click="toggleStatus({{ $fee->id }})">
                                                    <label class="form-check-label small {{ $fee->status ? 'text-success' : 'text-muted' }}" for="feeSwitch{{ $fee->id }}">{{ $fee->status ? 'Active' : 'Inactive' }}</label>
                                                </div>
                                            </td>
                                            <td class="text-end text-nowrap">
                                                <button type="button" class="btn btn-sm btn-outline-warning" wire:click="edit({{ $fee->id }})" title="Edit"><i class="ti ti-edit"></i></button>
                                                @if($fee->dues_count)
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Charged to students — set it Inactive instead"><i class="ti ti-lock"></i></button>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="delete({{ $fee->id }})" title="Delete"
                                                            onclick="confirm('Remove this fee from the structure?') || event.stopImmediatePropagation()"><i class="ti ti-trash"></i></button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                @if($lines->count() > 1)
                                    <tfoot>
                                        <tr class="fs-foot-row">
                                            <td></td>
                                            <td class="text-muted">Total of active fees{{ $hasFilters ? ' (whole course)' : '' }}</td>
                                            <td class="text-end"><span class="fs-amount">{{ money_inr($sum->active_total ?? 0, false) }}</span></td>
                                            <td colspan="4"></td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                </section>
            @empty
                <div class="fs-empty-row">
                    <i class="ti ti-receipt-off"></i>
                    <div>{{ $hasFilters ? 'No fees match these filters.' : 'No fee structure yet.' }}</div>
                    @unless($hasFilters)
                        <button type="button" class="btn btn-sm btn-primary mt-2" wire:click="create"><i class="ti ti-circle-plus me-1"></i> Add the first fee</button>
                    @endunless
                </div>
            @endforelse
        </div>
        @if($groups->hasPages())<div class="fs-panel-foot">{{ $groups->links() }}</div>@endif
    </div>

    {{-- Add / edit modal --}}
    <div class="modal fade" id="feeStructureModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content fs-modal">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fs-group-icon"><i class="ti ti-{{ $editingId ? 'edit' : 'receipt-2' }}"></i></span>
                        <h5 class="modal-title mb-0">{{ $editingId ? 'Edit fee' : 'Add fee' }}</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        @if($isSuperAdmin)
                            <div class="col-12">
                                <label class="form-label fw-medium small" for="feeInstitute">Institute <span class="text-danger">*</span></label>
                                <select id="feeInstitute" class="form-select @error('instituteId') is-invalid @enderror" wire:model="instituteId" @disabled($editingId)>
                                    <option value="">Select institute</option>
                                    @foreach($institutes as $inst)<option value="{{ $inst->id }}">{{ $inst->name }} ({{ $inst->code }})</option>@endforeach
                                </select>
                                @error('instituteId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @endif
                        <div class="col-12">
                            <label class="form-label fw-medium small" for="feeCourse">Course <span class="text-danger">*</span></label>
                            <select id="feeCourse" class="form-select @error('courseId') is-invalid @enderror" wire:model="courseId" @disabled($editingId || !$instituteId)>
                                <option value="">{{ $instituteId ? ($modalCourses->isEmpty() ? 'No courses assigned to this institute' : 'Select course') : 'Select institute first' }}</option>
                                @foreach($modalCourses as $c)<option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>@endforeach
                            </select>
                            @error('courseId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        @include('livewire.admin.students.partials.input', ['name' => 'fee_head', 'label' => 'Fee', 'required' => true, 'col' => 12, 'placeholder' => 'e.g. Admission fee'])
                        @include('livewire.admin.students.partials.input', ['name' => 'amount', 'label' => 'Amount (₹)', 'type' => 'number', 'step' => '0.01', 'required' => true])
                        @include('livewire.admin.students.partials.input', ['name' => 'sort_order', 'label' => 'Display order', 'type' => 'number', 'required' => true])
                        <div class="col-12">
                            <label class="form-label fw-medium small d-block">Due date <span class="text-danger">*</span></label>
                            <div class="fs-due-switch" role="radiogroup" aria-label="Due date type">
                                <input type="radio" class="btn-check" name="due_type" id="dueJoining" value="joining" wire:model="due_type">
                                <label class="fs-due-option" for="dueJoining"><i class="ti ti-calendar-time"></i><span><strong>After joining</strong><small>Each student's joining date + days</small></span></label>
                                <input type="radio" class="btn-check" name="due_type" id="dueFixed" value="fixed" wire:model="due_type">
                                <label class="fs-due-option" for="dueFixed"><i class="ti ti-calendar-event"></i><span><strong>Fixed date</strong><small>Same date for every student</small></span></label>
                            </div>
                        </div>
                        @if($due_type === 'fixed')
                            @include('livewire.admin.students.partials.input', ['name' => 'due_date', 'label' => 'Due on', 'type' => 'date', 'required' => true, 'help' => 'e.g. semester or lab fee due date'])
                        @else
                            @include('livewire.admin.students.partials.input', ['name' => 'due_days', 'label' => 'Days after joining', 'type' => 'number', 'required' => true, 'help' => '0 = due on the joining date'])
                        @endif
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="feeStatus" wire:model="status" value="1">
                                <label class="form-check-label" for="feeStatus">{{ $status ? 'Active' : 'Inactive' }}</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled"><i class="ti ti-check me-1"></i> Save</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .fs-ui { --fs-border: #E5E7EB; --fs-soft: #F1F2F4; --fs-ink: #111827; --fs-muted: #6B7280; --fs-accent: #F26522; }
        .fs-ui .min-w-0 { min-width: 0; }
        .fs-ui .fs-hero { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; padding: 18px 22px; border-radius: 14px; border: 1px solid var(--fs-border);
            background: radial-gradient(circle at 100% 0, rgba(242, 101, 34, .12), transparent 45%), linear-gradient(135deg, #FFFFFF 0%, #FFF8F3 100%); }
        .fs-ui .fs-hero h2 { font-size: 22px; color: var(--fs-ink); }
        .fs-ui .fs-hero-cta { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 999px; border: 0; background: var(--fs-accent); color: #fff; font-size: 14px; font-weight: 500; box-shadow: 0 6px 16px rgba(242, 101, 34, .3); transition: transform .15s; }
        .fs-ui .fs-hero-cta:hover { transform: translateY(-1px); }

        .fs-ui .fs-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; }
        .fs-ui .fs-card { --tone: #4338CA; --tone-soft: #EEF2FF; display: flex; flex-direction: column; gap: 2px; padding: 14px 16px 16px; background: #fff; border: 1px solid var(--fs-border); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); border-top: 3px solid var(--tone); }
        .fs-ui .fs-card-top { display: flex; justify-content: space-between; align-items: center; }
        .fs-ui .fs-card-label { font-size: 13px; font-weight: 600; color: #374151; }
        .fs-ui .fs-card-icon { width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 19px; background: var(--tone-soft); color: var(--tone); }
        .fs-ui .fs-card-count { font-size: 24px; font-weight: 700; line-height: 1.2; color: var(--fs-ink); font-variant-numeric: tabular-nums; }
        .fs-ui .fs-card-hint { font-size: 12px; color: var(--fs-muted); }
        .fs-ui .fs-card-all { --tone: #4338CA; --tone-soft: #EEF2FF; }
        .fs-ui .fs-card-ok { --tone: #16A34A; --tone-soft: #DCFCE7; }
        .fs-ui .fs-card-warn { --tone: #D97706; --tone-soft: #FEF3C7; }
        .fs-ui .fs-card-info { --tone: #0284C7; --tone-soft: #E0F2FE; }

        .fs-ui .fs-missing { padding: 14px 16px; border-radius: 14px; background: #FFFBEB; border: 1px solid #FDE68A; color: #92400E; }
        .fs-ui .fs-missing-icon { width: 32px; height: 32px; border-radius: 9px; background: #FEF3C7; color: #D97706; display: inline-flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; }
        .fs-ui .fs-missing-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px 4px 6px; border-radius: 999px; background: #fff; border: 1px solid #FCD34D; color: #374151; font-size: 12.5px; transition: border-color .15s, box-shadow .15s; }
        .fs-ui .fs-missing-chip:hover { border-color: var(--fs-accent); box-shadow: 0 2px 8px rgba(242, 101, 34, .15); }
        .fs-ui .fs-missing-chip .ti-plus { color: var(--fs-accent); }

        .fs-ui .fs-panel { background: #fff; border: 1px solid var(--fs-border); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); overflow: hidden; }
        .fs-ui .fs-panel-foot { padding: 12px 16px; border-top: 1px solid var(--fs-soft); }
        .fs-ui .fs-panel-foot .pagination { margin: 0; }
        .fs-ui .fs-filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 14px 16px; border-bottom: 1px solid var(--fs-soft); background: #FCFCFD; }
        .fs-ui .fs-filters .form-select { width: auto; min-width: 140px; max-width: 230px; border-radius: 8px; }
        .fs-ui .fs-search { position: relative; width: 240px; max-width: 100%; }
        .fs-ui .fs-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
        .fs-ui .fs-search input { padding-left: 32px; border-radius: 8px; }

        .fs-ui .fs-table thead th { background: #F9FAFB; font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--fs-muted); border-bottom: 1px solid var(--fs-border); padding: 10px 14px; white-space: nowrap; }
        .fs-ui .fs-table td { padding: 11px 14px; border-color: var(--fs-soft); font-size: 14px; }
        .fs-ui .fs-table tbody tr:hover td { background: #FAFAFB; }
        .fs-ui .fs-group-icon { width: 28px; height: 28px; border-radius: 8px; background: #FEF0E7; color: var(--fs-accent); display: inline-flex; align-items: center; justify-content: center; font-size: 15px; }
        .fs-ui .fs-group-add { padding: 1px 10px; border-radius: 999px; font-size: 12px; }
        .fs-ui .fs-total { font-size: 13px; color: var(--fs-muted); }
        .fs-ui .fs-total strong { font-family: 'IBM Plex Mono', ui-monospace, monospace; color: var(--fs-ink); font-size: 14px; }
        .fs-ui .fs-code { padding: 1px 7px; border-radius: 6px; background: #EEF2FF; color: #4338CA; font-weight: 600; font-size: 11.5px; }
        .fs-ui .fs-inst-code { padding: 1px 8px; border-radius: 6px; background: #F3F4F6; color: #374151; font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 11px; }
        .fs-ui .fs-order { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 8px; background: #F3F4F6; color: var(--fs-muted); font-size: 12px; font-weight: 600; }
        .fs-ui .fs-amount { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-weight: 600; color: var(--fs-ink); white-space: nowrap; }
        .fs-ui .fs-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
        .fs-ui .fs-chip-warn { background: #FEF3C7; color: #B45309; }
        .fs-ui .fs-chip-info { background: #E0F2FE; color: #0369A1; }
        .fs-ui .fs-chip-fixed { background: #EEF2FF; color: #4338CA; }
        .fs-ui .fs-chip-bad { background: #FEE2E2; color: #DC2626; }
        .fs-ui .fs-due-switch { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .fs-ui .fs-due-option { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid #E5E7EB; border-radius: 10px; cursor: pointer; margin: 0; transition: border-color .15s, background .15s; }
        .fs-ui .fs-due-option i { font-size: 20px; color: #9CA3AF; }
        .fs-ui .fs-due-option span { display: flex; flex-direction: column; line-height: 1.25; }
        .fs-ui .fs-due-option small { color: #6B7280; font-size: 11.5px; }
        .fs-ui .btn-check:checked + .fs-due-option { border-color: #F26522; background: #FFF7F2; box-shadow: 0 0 0 3px rgba(242, 101, 34, .12); }
        .fs-ui .btn-check:checked + .fs-due-option i { color: #F26522; }
        .fs-ui .fs-groups { padding: 14px; display: flex; flex-direction: column; gap: 12px; background: #FCFCFD; min-height: 80px; }
        .fs-ui .fs-course { background: #fff; border: 1px solid var(--fs-border); border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); transition: box-shadow .15s; }
        .fs-ui .fs-course:hover { box-shadow: 0 6px 16px rgba(16, 24, 40, .07); }
        .fs-ui .fs-course-head { display: flex; align-items: center; gap: 12px; padding: 12px 16px; cursor: pointer; user-select: none; background: linear-gradient(90deg, #FFF7F2 0%, #FFFFFF 55%); border-bottom: 1px solid var(--fs-soft); }
        .fs-ui .fs-course-head.collapsed { border-bottom-color: transparent; background: #fff; }
        .fs-ui .fs-course-head:focus-visible { outline: 2px solid var(--fs-accent); outline-offset: -2px; }
        .fs-ui .fs-chevron { font-size: 18px; color: #9CA3AF; transition: transform .2s; transform: rotate(90deg); }
        .fs-ui .fs-course-head.collapsed .fs-chevron { transform: rotate(0deg); }
        .fs-ui .fs-course-icon { width: 38px; height: 38px; border-radius: 10px; background: #FEF0E7; color: var(--fs-accent); display: inline-flex; align-items: center; justify-content: center; font-size: 19px; flex-shrink: 0; }
        .fs-ui .fs-course-name { font-weight: 600; color: var(--fs-ink); font-size: 14.5px; max-width: 360px; }
        .fs-ui .fs-course-inst { font-size: 12px; color: var(--fs-muted); display: flex; align-items: center; gap: 4px; margin-top: 2px; }
        .fs-ui .fs-course-stats { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
        .fs-ui .fs-stat { display: flex; flex-direction: column; align-items: flex-end; line-height: 1.2; min-width: 64px; }
        .fs-ui .fs-stat:nth-child(2) { min-width: 92px; } .fs-ui .fs-stat:nth-child(3) { min-width: 110px; } .fs-ui .fs-stat-total { min-width: 118px; }
        .fs-ui .fs-stat span { font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em; color: var(--fs-muted); }
        .fs-ui .fs-stat strong { font-size: 13.5px; color: var(--fs-ink); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .fs-ui .fs-stat-total strong { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 15px; color: var(--fs-accent); }
        .fs-ui .fs-chip-muted { background: #F3F4F6; color: var(--fs-muted); }
        .fs-ui .fs-course .fs-table thead th { background: #fff; }
        .fs-ui .fs-course .fs-table td { padding-top: 9px; padding-bottom: 9px; }
        .fs-ui .fs-foot-row td { background: #F9FAFB; font-size: 12.5px; border-top: 1px solid var(--fs-border); }
        .fs-ui .fs-toggle-all { border-radius: 8px; font-size: 12px; padding: 3px 10px; }
        @media (max-width: 991.98px) { .fs-ui .fs-course-head { flex-wrap: wrap; } .fs-ui .fs-course-stats { width: 100%; justify-content: space-between; gap: 10px; order: 3; } .fs-ui .fs-stat { align-items: flex-start; } }
        .fs-ui .fs-row-inactive td { color: #9CA3AF; }
        .fs-ui .fs-row-inactive .fw-semibold, .fs-ui .fs-row-inactive .fs-amount { color: #9CA3AF; text-decoration: line-through; text-decoration-color: #D1D5DB; }
        .fs-ui .fs-empty-row { text-align: center; color: var(--fs-muted); padding: 48px 12px !important; }
        .fs-ui .fs-empty-row > i { font-size: 36px; color: #D1D5DB; display: block; margin-bottom: 6px; }
        .fs-ui .fs-loading { display: none; position: absolute; inset: 0; z-index: 2; background: rgba(255, 255, 255, .6); align-items: center; justify-content: center; }
        .fs-ui .fs-modal { border-radius: 14px; border: 0; }
        @media (max-width: 575.98px) { .fs-ui .fs-search, .fs-ui .fs-filters .form-select { width: 100%; max-width: none; } }
    </style>
</div>

<script>
// Accordion: opening one course closes the others — except during "Expand all" / "Collapse all"
var fsBulkToggle = false;
function fsToggleAll(open) {
    fsBulkToggle = true;
    document.querySelectorAll('.fs-course .collapse').forEach(function (el) {
        bootstrap.Collapse.getOrCreateInstance(el, { toggle: false })[open ? 'show' : 'hide']();
    });
    setTimeout(function () { fsBulkToggle = false; }, 400);
}
document.addEventListener('show.bs.collapse', function (event) {
    if (fsBulkToggle || !event.target.closest('.fs-course')) {
        return;
    }
    document.querySelectorAll('.fs-course .collapse.show').forEach(function (el) {
        if (el !== event.target) {
            bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).hide();
        }
    });
});

document.addEventListener('livewire:load', function () {
    window.addEventListener('open-fee-structure-modal', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('feeStructureModal')).show());
    window.addEventListener('close-fee-structure-modal', () => {
        const instance = bootstrap.Modal.getInstance(document.getElementById('feeStructureModal'));
        if (instance) {
            instance.hide();
        }
    });
});
</script>
