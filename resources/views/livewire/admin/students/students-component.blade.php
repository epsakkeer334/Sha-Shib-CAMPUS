@php
    $sortIcon = fn ($field) => $sortField === $field ? ($sortDirection === 'asc' ? 'ti-arrow-up' : 'ti-arrow-down') : 'ti-arrows-sort text-muted opacity-50';
    $cards = [
        // key, label, value, icon, tone, hint, active?
        ['all', 'Total students', $summary['total'], 'ti ti-users', 'all', $summary['this_month'] . ' added this month', !$status && !$payment && !$docsGate && !$feesGate && !$overdue],
        ['pending_approval', 'Pending approval', $summary['pending_approval'], 'ti ti-hourglass-high', 'warn', 'Waiting for the gates', $status === 'pending_approval' && !$payment],
        ['er_issued', 'ER issued', $summary['er_issued'], 'ti ti-id-badge-2', 'ok', 'ER number generated', $status === 'er_issued' && !$payment],
        ['unpaid', 'Fees outstanding', $summary['unpaid'], 'ti ti-cash', 'bad', 'Dues not fully paid', $payment === 'unpaid' && !$status],
    ];
@endphp
<div class="content students-list">
    {{-- Header (same layout as Institutes Management) --}}
    <div class="sl-hero mb-3">
        <div class="sl-hero-text">
            <div class="d-flex align-items-center gap-2 mb-1">
                <h2 class="mb-0 fw-bold">Students</h2>
                <span class="sl-total" title="Total students">{{ number_format($summary['total']) }}</span>
            </div>
            <nav>
                <ol class="breadcrumb mb-1 sl-crumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Admissions</li>
                    <li class="breadcrumb-item active">Students</li>
                </ol>
            </nav>
            <div class="sl-hero-sub">
                <span class="text-muted">Every admission from registration to ID card.</span>
                <span class="sl-pill sl-pill-ok"><i class="ti ti-trending-up"></i> +{{ number_format($summary['this_month']) }} this month</span>
                @if($summary['overdue'])
                    <button type="button" class="sl-pill sl-pill-bad {{ $overdue ? 'is-on' : '' }}" wire:click="showOverdue" title="Onboarding deadline passed without an ER number — show them">
                        <i class="ti ti-alarm"></i> {{ number_format($summary['overdue']) }} past deadline
                    </button>
                @else
                    <span class="sl-pill sl-pill-muted"><i class="ti ti-circle-check"></i> No one past deadline</span>
                @endif
            </div>
        </div>
        <div class="sl-hero-actions">
            @can('onboarding.verify_documents')
                <a href="{{ route('admin.onboarding.documents') }}" class="btn btn-light border sl-hero-btn"><i class="ti ti-file-check me-1"></i> Document queue</a>
            @endcan
            @can('students.create')
                <a href="{{ route('admin.students.create') }}" class="sl-hero-cta"><i class="ti ti-user-plus"></i> Add Student</a>
            @endcan
        </div>
    </div>

    {{-- Summary (click to filter) --}}
    <div class="sl-cards mb-3">
        @foreach($cards as [$key, $label, $value, $icon, $tone, $hint, $active])
            <button type="button" wire:click="quickFilter('{{ $key }}')" class="sl-card sl-card-{{ $tone }} {{ $active ? 'is-active' : '' }}" aria-pressed="{{ $active ? 'true' : 'false' }}">
                <span class="sl-card-top">
                    <span class="sl-card-label">{{ $label }}</span>
                    <span class="sl-card-icon"><i class="{{ $icon }}"></i></span>
                </span>
                <span class="sl-card-count">{{ number_format($value) }}</span>
                <span class="sl-card-hint">{{ $hint }}</span>
            </button>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        {{-- Filters --}}
        <div class="card-header bg-white border-bottom p-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-lg-3">
                    <label class="filter-label" for="stuSearch">Search</label>
                    <div class="position-relative">
                        <i class="ti ti-search position-absolute top-50 translate-middle-y text-muted" style="left: 12px;"></i>
                        <input id="stuSearch" type="search" class="form-control form-control-sm ps-5" placeholder="Name, email, phone, ER no."
                               wire:model.debounce.400ms="search">
                    </div>
                </div>
                @if($isSuperAdmin)
                    <div class="col-6 col-md-4 col-lg">
                        <label class="filter-label" for="fInstitute">Institute</label>
                        <select id="fInstitute" class="form-select form-select-sm" wire:model="institute">
                            <option value="">All institutes</option>
                            @foreach($institutes as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                        </select>
                    </div>
                @endif
                <div class="col-6 col-md-4 col-lg">
                    <label class="filter-label" for="fCourse">Course</label>
                    <select id="fCourse" class="form-select form-select-sm" wire:model="course">
                        <option value="">All courses</option>
                        @foreach($courses as $c)<option value="{{ $c->id }}">{{ $c->code }} — {{ \Illuminate\Support\Str::limit($c->name, 30) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4 col-lg">
                    <label class="filter-label" for="fStatus">Status</label>
                    <select id="fStatus" class="form-select form-select-sm" wire:model="status">
                        <option value="">All statuses</option>
                        @foreach($statuses as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4 col-lg">
                    <label class="filter-label" for="fDocs">Docs approval</label>
                    <select id="fDocs" class="form-select form-select-sm" wire:model="docsGate">
                        <option value="">Any</option>
                        @foreach(\App\Http\Livewire\Admin\Students\StudentsComponent::GATE_FILTERS as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4 col-lg">
                    <label class="filter-label" for="fFees">Fees approval</label>
                    <select id="fFees" class="form-select form-select-sm" wire:model="feesGate">
                        <option value="">Any</option>
                        @foreach(\App\Http\Livewire\Admin\Students\StudentsComponent::GATE_FILTERS as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4 col-lg">
                    <label class="filter-label" for="fPayment">Payment</label>
                    <select id="fPayment" class="form-select form-select-sm" wire:model="payment">
                        <option value="">Any</option>
                        @foreach(\App\Http\Livewire\Admin\Students\StudentsComponent::PAYMENT_FILTERS as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
            </div>

            @if($activeFilters)
                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                    <span class="small text-muted">Filtered by:</span>
                    @foreach($activeFilters as $key => $label)
                        <span class="filter-chip">
                            {{ $label }}
                            <button type="button" class="btn-close" style="font-size: 8px;" wire:click="clearFilter('{{ $key }}')" aria-label="Remove filter"></button>
                        </span>
                    @endforeach
                    <button type="button" class="btn btn-link btn-sm p-0 ms-1" wire:click="clearAll">Clear all</button>
                </div>
            @endif
        </div>

        {{-- Table --}}
        <div class="table-responsive position-relative">
            <div wire:loading.delay.flex class="table-loading"><span class="spinner-border spinner-border-sm text-primary"></span></div>
            <table class="table table-hover align-middle mb-0 students-table">
                <thead>
                    <tr>
                        <th style="width: 56px;"><a href="javascript:void(0);" wire:click="sortBy('id')"># <i class="ti {{ $sortIcon('id') }}"></i></a></th>
                        <th><a href="javascript:void(0);" wire:click="sortBy('first_name')">Student <i class="ti {{ $sortIcon('first_name') }}"></i></a></th>
                        @if($isSuperAdmin)<th>Institute</th>@endif
                        <th>Course</th>
                        <th><a href="javascript:void(0);" wire:click="sortBy('er_number')">ER / Dates <i class="ti {{ $sortIcon('er_number') }}"></i></a></th>
                        <th>Status</th>
                        <th>Fees</th>
                        <th>Approvals</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($students as $student)
                        <tr wire:key="student-{{ $student->id }}">
                            <td class="text-muted small">{{ $student->id }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="stu-avatar">{{ $student->initials }}</span>
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.students.edit', $student->id) }}" class="fw-semibold text-dark d-block text-truncate">{{ $student->full_name }}</a>
                                        <div class="small text-muted text-truncate">{{ $student->email }}</div>
                                        <div class="small text-muted">{{ $student->phone }}</div>
                                    </div>
                                </div>
                            </td>
                            @if($isSuperAdmin)
                                <td>
                                    <div class="fw-medium">{{ optional($student->institute)->name }}</div>
                                    @if(optional($student->institute)->code)
                                        <span class="institute-code">{{ $student->institute->code }}</span>
                                    @endif
                                </td>
                            @endif
                            <td>
                                <div class="fw-medium">{{ optional($student->course)->code ?? '—' }}</div>
                                <div class="small text-muted text-truncate" style="max-width: 180px;" title="{{ optional($student->course)->name }}">{{ optional($student->course)->name }}</div>
                                @if($student->batch)<span class="batch-code" title="Batch: {{ $student->batch->name }}"><i class="ti ti-users-group"></i> {{ $student->batch->code }}</span>@endif
                            </td>
                            <td>{!! $student->er_cell_html !!}</td>
                            <td>{!! $student->status_html !!}</td>
                            <td>{!! $student->fee_status_html !!}</td>
                            <td>{!! $student->approvals_html !!}</td>
                            <td>
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    <a href="{{ route('admin.students.edit', $student->id) }}" class="btn btn-sm btn-outline-warning" data-bs-toggle="tooltip" title="Open onboarding"><i class="ti ti-edit"></i></a>
                                    @if($canFees)
                                        <a href="{{ route('admin.students.fees', $student->id) }}" class="btn btn-sm btn-outline-success" data-bs-toggle="tooltip" title="Fees & payments"><i class="ti ti-cash"></i></a>
                                    @endif
                                    <a href="{{ route('admin.students.enrollment', $student->id) }}" class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" title="Gates, ER & ID card"><i class="ti ti-id"></i></a>
                                    @if($canDelete)
                                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="confirmDelete({{ $student->id }})" data-bs-toggle="tooltip"
                                                title="{{ $student->status === 'draft' ? 'Delete' : 'Delete (only draft students can be deleted)' }}"><i class="ti ti-trash"></i></button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isSuperAdmin ? 9 : 8 }}" class="text-center text-muted py-5">
                                <i class="ti ti-users-minus fs-1 d-block mb-2 opacity-50"></i>
                                {{ $activeFilters ? 'No students match these filters.' : 'No students yet.' }}
                                @if($activeFilters)<button type="button" class="btn btn-link btn-sm" wire:click="clearAll">Clear filters</button>@endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Footer --}}
        <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2 small text-muted">
                <span>Show</span>
                <select class="form-select form-select-sm w-auto" wire:model="perPage" aria-label="Rows per page">
                    @foreach([10, 15, 25, 50] as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach
                </select>
                <span>· {{ $students->firstItem() ?? 0 }}–{{ $students->lastItem() ?? 0 }} of {{ $students->total() }}</span>
            </div>
            <div>{{ $students->links() }}</div>
        </div>
    </div>

    {{-- Delete Modal --}}
    <div class="modal fade" id="studentDeleteModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <i class="ti ti-alert-triangle text-danger fs-1 mb-3"></i>
                    <h5>Delete Student</h5>
                    <p class="text-muted mb-4">Only draft students can be deleted. Their uploaded documents are removed too.</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-danger px-4" wire:click="delete">Yes, Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .students-list .filter-label { font-size: 12px; font-weight: 500; color: #6B7280; margin-bottom: 4px; display: block; }
        .students-list .filter-chip { display: inline-flex; align-items: center; gap: 6px; padding: 3px 6px 3px 10px; border-radius: 999px; background: #F3F4F6; border: 1px solid #E5E7EB; font-size: 12px; color: #374151; }
        .students-list .students-table thead th { background: #F9FAFB; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; color: #6B7280; border-bottom: 1px solid #E5E7EB; white-space: nowrap; padding: 12px 14px; }
        .students-list .students-table thead th a { color: inherit; text-decoration: none; }
        .students-list .students-table td { padding: 12px 14px; border-color: #F1F2F4; font-size: 14px; }
        .students-list .stu-avatar { width: 36px; height: 36px; border-radius: 50%; background: #FEF0E7; color: #F26522; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
        /* ER number: green badge once issued, faint dashed placeholder until then */
        .students-list .er-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px 3px 8px; border-radius: 8px; background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%); border: 1px solid #A7F3D0; color: #047857; font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 12.5px; font-weight: 600; letter-spacing: .02em; white-space: nowrap; box-shadow: 0 1px 2px rgba(4, 120, 87, .08); }
        .students-list .er-badge i { font-size: 14px; color: #059669; }
        .students-list .er-badge-pending { background: #F9FAFB; border: 1px dashed #D1D5DB; color: #9CA3AF; font-family: inherit; font-weight: 500; font-size: 11.5px; box-shadow: none; padding: 2px 8px; }
        /* header */
        .students-list .min-w-0 { min-width: 0; }
        .students-list .sl-hero { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; padding: 18px 22px; border-radius: 14px; border: 1px solid #E5E7EB;
            background: radial-gradient(circle at 100% 0, rgba(242, 101, 34, .12), transparent 45%), linear-gradient(135deg, #FFFFFF 0%, #FFF8F3 100%); }
        .students-list .sl-hero-text { flex: 1 1 420px; min-width: 0; }
        .students-list .sl-hero h2 { font-size: 22px; color: #111827; }
        .students-list .sl-hero-sub { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 10px; font-size: 13px; }
        .students-list .sl-total { min-width: 28px; height: 24px; padding: 0 9px; border-radius: 999px; background: #FEF0E7; color: #F26522; border: 1px solid #FDDCC6; font-size: 12.5px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }
        .students-list .sl-hero-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .students-list .sl-pill { display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; border: 0; }
        .students-list .sl-pill-ok { background: #DCFCE7; color: #15803D; }
        .students-list .sl-pill-bad { background: #FEE2E2; color: #DC2626; cursor: pointer; }
        .students-list .sl-pill-bad:hover, .students-list .sl-pill-bad.is-on { background: #FECACA; box-shadow: 0 0 0 2px #FCA5A5; }
        .students-list .sl-pill-muted { background: #F3F4F6; color: #6B7280; }
        .students-list .sl-hero-btn { border-radius: 999px; padding: 9px 16px; font-size: 14px; }
        .students-list .sl-hero-cta { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 999px; background: #F26522; color: #fff; font-size: 14px; font-weight: 500; text-decoration: none; box-shadow: 0 6px 16px rgba(242, 101, 34, .3); transition: transform .15s; }
        .students-list .sl-hero-cta:hover { color: #fff; transform: translateY(-1px); }
        /* summary cards */
        .students-list .sl-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; }
        .students-list .sl-card { --tone: #4338CA; --tone-soft: #EEF2FF; position: relative; overflow: hidden; display: flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: 14px 16px 16px; background: #fff; border: 1px solid #E5E7EB; border-radius: 14px; text-align: left; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); transition: box-shadow .15s, transform .15s, border-color .15s; }
        .students-list .sl-card::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: var(--tone); opacity: 0; transition: opacity .15s; }
        .students-list .sl-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16, 24, 40, .08); }
        .students-list .sl-card.is-active { border-color: var(--tone); background: linear-gradient(180deg, var(--tone-soft) 0%, #fff 70%); }
        .students-list .sl-card.is-active::after { opacity: 1; }
        .students-list .sl-card-top { display: flex; justify-content: space-between; align-items: center; width: 100%; }
        .students-list .sl-card-label { font-size: 13px; font-weight: 600; color: #374151; }
        .students-list .sl-card-icon { width: 38px; height: 38px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 19px; background: var(--tone-soft); color: var(--tone); }
        .students-list .sl-card-count { font-size: 26px; font-weight: 700; line-height: 1.15; color: #111827; }
        .students-list .sl-card-hint { font-size: 12px; color: #6B7280; }
        .students-list .sl-card-all { --tone: #4338CA; --tone-soft: #EEF2FF; } .students-list .sl-card-warn { --tone: #D97706; --tone-soft: #FEF3C7; }
        .students-list .sl-card-ok { --tone: #16A34A; --tone-soft: #DCFCE7; } .students-list .sl-card-bad { --tone: #DC2626; --tone-soft: #FEE2E2; }
        .students-list .batch-code { display: inline-flex; align-items: center; gap: 3px; margin-top: 3px; padding: 1px 7px; border-radius: 6px; background: #FFF7ED; border: 1px solid #FED7AA; color: #C2410C; font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 11px; font-weight: 600; }
        .students-list .institute-code { display: inline-block; margin-top: 3px; padding: 1px 8px; border-radius: 6px; background: #EEF2FF; color: #4338CA; font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 11.5px; font-weight: 500; letter-spacing: .02em; }
        .students-list .min-w-0 { min-width: 0; max-width: 240px; }
        /* Hidden by default. While a request runs, Livewire sets an inline display:flex (wire:loading.delay.flex),
           which overrides this. (Livewire 2's own CSS does not hide the combined ".delay.flex" attribute.) */
        .students-list .table-loading { display: none; position: absolute; inset: 0; background: rgba(255, 255, 255, .6); align-items: flex-start; justify-content: center; padding-top: 48px; z-index: 2; }
    </style>
</div>

<script>
document.addEventListener('livewire:load', function () {
    const tips = () => document.querySelectorAll('.students-list [data-bs-toggle="tooltip"]').forEach(el => bootstrap.Tooltip.getOrCreateInstance(el));
    tips();
    Livewire.hook('message.processed', () => {
        document.querySelectorAll('.tooltip').forEach(t => t.remove());
        tips();
    });

    window.addEventListener('open-student-delete-modal', () => {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('studentDeleteModal')).show();
    });
    window.addEventListener('close-student-delete-modal', () => {
        const instance = bootstrap.Modal.getInstance(document.getElementById('studentDeleteModal'));
        if (instance) {
            instance.hide();
        }
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
        document.body.classList.remove('modal-open');
    });
});
</script>
