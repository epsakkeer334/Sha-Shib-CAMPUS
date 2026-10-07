<div class="content bt-ui">
    {{-- Header --}}
    <div class="bt-hero mb-3">
        <div class="min-w-0">
            <h2 class="mb-1 fw-bold">Batches</h2>
            <nav>
                <ol class="breadcrumb mb-1 sl-crumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Institute Management</li>
                    <li class="breadcrumb-item active">Batches</li>
                </ol>
            </nav>
            <div class="text-muted small">Batches of each institute course. Students choose their batch when they register.</div>
        </div>
        <button type="button" class="bt-hero-cta" wire:click="create"><i class="ti ti-circle-plus"></i> Add Batch</button>
    </div>

    {{-- Summary (click to filter) --}}
    <div class="bt-cards mb-3">
        @foreach([
            ['', 'Batches', $stats['total'], 'ti ti-users-group', 'all', 'All batches'],
            ['open', 'Open', $stats['open'], 'ti ti-door-enter', 'ok', 'Accepting students'],
            ['closed', 'Closed', $stats['closed'], 'ti ti-door-off', 'muted', 'Not shown at registration'],
        ] as [$key, $label, $count, $icon, $tone, $hint])
            <button type="button" class="bt-card bt-card-{{ $tone }} {{ (string) $filterStatus === $key ? 'is-active' : '' }}" wire:click="$set('filterStatus', '{{ $key }}')">
                <span class="bt-card-top"><span class="bt-card-label">{{ $label }}</span><span class="bt-card-icon"><i class="{{ $icon }}"></i></span></span>
                <span class="bt-card-count">{{ number_format($count) }}</span>
                <span class="bt-card-hint">{{ $hint }}</span>
            </button>
        @endforeach
        <div class="bt-card bt-card-info">
            <span class="bt-card-top"><span class="bt-card-label">Students in batches</span><span class="bt-card-icon"><i class="ti ti-school"></i></span></span>
            <span class="bt-card-count">{{ number_format($stats['students']) }}</span>
            <span class="bt-card-hint">Assigned to a batch</span>
        </div>
    </div>

    <div class="bt-panel">
        <div class="bt-toolbar">
            <div class="bt-search">
                <i class="ti ti-search"></i>
                <input type="search" class="form-control form-control-sm" placeholder="Batch name, code or course" wire:model.debounce.400ms="search" aria-label="Search batches">
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
            @if($hasFilters)
                <button type="button" class="btn btn-sm btn-light" wire:click="clearFilters"><i class="ti ti-x me-1"></i> Clear</button>
            @endif
            <span class="ms-auto small text-muted">{{ number_format($batches->total()) }} {{ \Illuminate\Support\Str::plural('batch', $batches->total()) }}</span>
        </div>

        <div class="table-responsive position-relative">
            <div class="bt-loading" wire:loading.delay.flex wire:target="search, filterInstitute, filterCourse, filterStatus, clearFilters, toggleStatus, delete, gotoPage, nextPage, previousPage">
                <span class="spinner-border spinner-border-sm text-secondary"></span>
            </div>
            <table class="table align-middle mb-0 bt-table">
                <thead>
                    <tr>
                        <th>Batch code</th>
                        <th>Batch</th>
                        @if($isSuperAdmin)<th>Institute</th>@endif
                        <th>Course</th>
                        <th>Period</th>
                        <th>Seats</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($batches as $batch)
                        @php
                            $fill = $batch->capacity ? min(100, round($batch->seats_taken / $batch->capacity * 100)) : null;
                            $full = $batch->capacity !== null && $batch->seats_taken >= $batch->capacity;
                        @endphp
                        <tr wire:key="batch-{{ $batch->id }}" class="{{ $batch->status ? '' : 'bt-row-off' }}">
                            <td><span class="bt-code">{{ $batch->code }}</span></td>
                            <td>
                                <div class="fw-semibold">{{ $batch->name }}</div>
                                @if($batch->remarks)<div class="bt-sub text-truncate" style="max-width: 220px;" title="{{ $batch->remarks }}">{{ $batch->remarks }}</div>@endif
                            </td>
                            @if($isSuperAdmin)
                                <td>
                                    <div class="small fw-medium">{{ optional($batch->institute)->name }}</div>
                                    @if(optional($batch->institute)->code)<span class="bt-inst-code">{{ $batch->institute->code }}</span>@endif
                                </td>
                            @endif
                            <td>
                                <span class="bt-course">{{ optional($batch->course)->code }}</span>
                                <div class="bt-sub">{{ optional($batch->course)->name }}</div>
                            </td>
                            <td class="small text-nowrap">
                                @if($batch->start_date || $batch->end_date)
                                    {{ optional($batch->start_date)->format('d M Y') ?? '—' }} <span class="text-muted">→</span> {{ optional($batch->end_date)->format('d M Y') ?? 'open' }}
                                @else
                                    <span class="text-muted">Not set</span>
                                @endif
                            </td>
                            <td style="min-width: 130px;">
                                @if($batch->capacity)
                                    <div class="d-flex justify-content-between small"><span class="{{ $full ? 'text-danger fw-semibold' : '' }}">{{ $batch->seats_taken }} / {{ $batch->capacity }}</span>@if($full)<span class="text-danger fw-semibold">Full</span>@endif</div>
                                    <div class="bt-bar"><span class="{{ $full ? 'is-full' : '' }}" style="width: {{ $fill }}%;"></span></div>
                                @else
                                    <span class="small">{{ $batch->seats_taken }} <span class="text-muted">· no limit</span></span>
                                @endif
                            </td>
                            <td>
                                <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
                                    <input class="form-check-input bt-switch" type="checkbox" role="switch" id="batchStatus{{ $batch->id }}" @checked($batch->status)
                                           wire:click="toggleStatus({{ $batch->id }})" aria-label="{{ $batch->status ? 'Close' : 'Open' }} batch {{ $batch->code }}">
                                    <label class="small {{ $batch->status ? 'text-success fw-semibold' : 'text-muted' }}" for="batchStatus{{ $batch->id }}">{{ $batch->status ? 'Open' : 'Closed' }}</label>
                                </div>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.students', ['search' => $batch->code]) }}" class="btn btn-sm btn-outline-info" title="Students of this batch"><i class="ti ti-users"></i></a>
                                <button type="button" class="btn btn-sm btn-outline-warning" wire:click="edit({{ $batch->id }})" title="Edit"><i class="ti ti-edit"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="confirmDelete({{ $batch->id }})" title="Delete"><i class="ti ti-trash"></i></button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isSuperAdmin ? 8 : 7 }}" class="bt-empty">
                                <i class="ti ti-users-group"></i>
                                <div class="fw-semibold">{{ $hasFilters ? 'No batches match these filters' : 'No batches yet' }}</div>
                                @unless($hasFilters)
                                    <button type="button" class="btn btn-sm btn-primary mt-2" wire:click="create"><i class="ti ti-circle-plus me-1"></i> Add the first batch</button>
                                @endunless
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($batches->hasPages())<div class="bt-foot">{{ $batches->links() }}</div>@endif
    </div>

    {{-- Add / edit --}}
    <div class="modal fade" id="batchModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bt-modal">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="bt-modal-icon"><i class="ti ti-{{ $editingId ? 'edit' : 'users-group' }}"></i></span>
                        <h5 class="modal-title mb-0">{{ $editingId ? 'Edit batch' : 'Add batch' }}</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        @if($isSuperAdmin)
                            <div class="col-12">
                                <label class="form-label fw-medium small" for="btInstitute">Institute <span class="text-danger">*</span></label>
                                <select id="btInstitute" class="form-select @error('instituteId') is-invalid @enderror" wire:model="instituteId" @disabled($editingHasStudents)>
                                    <option value="">Select institute</option>
                                    @foreach($institutes as $inst)<option value="{{ $inst->id }}">{{ $inst->name }} ({{ $inst->code }})</option>@endforeach
                                </select>
                                @error('instituteId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @endif
                        <div class="col-12">
                            <label class="form-label fw-medium small" for="btCourse">Course <span class="text-danger">*</span></label>
                            <select id="btCourse" class="form-select @error('courseId') is-invalid @enderror" wire:model="courseId" @disabled($editingHasStudents || !$instituteId)>
                                <option value="">{{ $instituteId ? ($modalCourses->isEmpty() ? 'No courses assigned to this institute' : 'Select course') : 'Select institute first' }}</option>
                                @foreach($modalCourses as $c)<option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>@endforeach
                            </select>
                            @error('courseId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @if($editingHasStudents)<small class="text-muted">Students are in this batch, so its institute and course stay fixed.</small>@endif
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-medium small" for="btName">Batch name <span class="text-danger">*</span></label>
                            <input id="btName" type="text" class="form-control @error('name') is-invalid @enderror" wire:model.defer="name" placeholder="e.g. June 2026 Batch">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-medium small" for="btCode">Batch code <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input id="btCode" type="text" class="form-control bt-code-input text-uppercase @error('code') is-invalid @elseif($codeAvailable) is-valid @enderror"
                                       wire:model.debounce.400ms="code" maxlength="30" placeholder="SHA-B11-2026" autocomplete="off">
                                <button type="button" class="btn btn-outline-secondary" wire:click="suggestCode" title="Suggest a code" @disabled(!$instituteId || !$courseId)><i class="ti ti-wand"></i></button>
                            </div>
                            @error('code') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            @if(!$errors->has('code') && $codeAvailable)<small class="text-success"><i class="ti ti-circle-check"></i> Available</small>@endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium small" for="btStart">Start date</label>
                            <input id="btStart" type="date" class="form-control @error('start_date') is-invalid @enderror" wire:model.defer="start_date">
                            @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium small" for="btEnd">End date</label>
                            <input id="btEnd" type="date" class="form-control @error('end_date') is-invalid @enderror" wire:model.defer="end_date">
                            @error('end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <small class="text-muted">After this date the batch is no longer offered at registration.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium small" for="btCapacity">Capacity (seats)</label>
                            <input id="btCapacity" type="number" min="1" class="form-control @error('capacity') is-invalid @enderror" wire:model.defer="capacity" placeholder="No limit">
                            @error('capacity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="btStatus" wire:model="status" value="1">
                                <label class="form-check-label" for="btStatus">{{ $status ? 'Open for admission' : 'Closed' }}</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium small" for="btRemarks">Remarks</label>
                            <textarea id="btRemarks" rows="2" class="form-control @error('remarks') is-invalid @enderror" wire:model.defer="remarks" placeholder="Optional"></textarea>
                            @error('remarks') <div class="invalid-feedback">{{ $message }}</div> @enderror
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

    {{-- Delete --}}
    <div class="modal fade" id="batchDeleteModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <i class="ti ti-alert-triangle text-danger fs-1 mb-3"></i>
                    <h5>Delete batch</h5>
                    <p class="text-muted mb-4">Delete this batch? A batch with students cannot be deleted — close it instead.</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-danger px-4" wire:click="delete">Yes, delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .bt-ui { --bt-border: #E5E7EB; --bt-soft: #F1F2F4; --bt-ink: #111827; --bt-muted: #6B7280; --bt-accent: #F26522; }
        .bt-ui .min-w-0 { min-width: 0; }
        .bt-ui .bt-hero { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; padding: 18px 22px; border-radius: 14px; border: 1px solid var(--bt-border);
            background: radial-gradient(circle at 100% 0, rgba(242, 101, 34, .12), transparent 45%), linear-gradient(135deg, #FFFFFF 0%, #FFF8F3 100%); }
        .bt-ui .bt-hero h2 { font-size: 22px; color: var(--bt-ink); }
        .bt-ui .bt-hero-cta { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 999px; border: 0; background: var(--bt-accent); color: #fff; font-size: 14px; font-weight: 500; box-shadow: 0 6px 16px rgba(242, 101, 34, .3); }
        .bt-ui .bt-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
        .bt-ui .bt-card { --tone: #4338CA; --tone-soft: #EEF2FF; position: relative; overflow: hidden; display: flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: 14px 16px 16px; background: #fff; border: 1px solid var(--bt-border); border-radius: 14px; text-align: left; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); transition: box-shadow .15s, transform .15s; }
        .bt-ui button.bt-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16, 24, 40, .08); }
        .bt-ui .bt-card::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: var(--tone); opacity: 0; }
        .bt-ui .bt-card.is-active { border-color: var(--tone); background: linear-gradient(180deg, var(--tone-soft) 0%, #fff 70%); }
        .bt-ui .bt-card.is-active::after { opacity: 1; }
        .bt-ui .bt-card-top { display: flex; justify-content: space-between; align-items: center; width: 100%; }
        .bt-ui .bt-card-label { font-size: 13px; font-weight: 600; color: #374151; }
        .bt-ui .bt-card-icon { width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 19px; background: var(--tone-soft); color: var(--tone); }
        .bt-ui .bt-card-count { font-size: 26px; font-weight: 700; color: var(--bt-ink); line-height: 1.15; }
        .bt-ui .bt-card-hint { font-size: 12px; color: var(--bt-muted); }
        .bt-ui .bt-card-all { --tone: #4338CA; --tone-soft: #EEF2FF; } .bt-ui .bt-card-ok { --tone: #16A34A; --tone-soft: #DCFCE7; }
        .bt-ui .bt-card-muted { --tone: #6B7280; --tone-soft: #F3F4F6; } .bt-ui .bt-card-info { --tone: #0284C7; --tone-soft: #E0F2FE; }
        .bt-ui .bt-panel { background: #fff; border: 1px solid var(--bt-border); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); overflow: hidden; }
        .bt-ui .bt-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--bt-soft); background: #FCFCFD; }
        .bt-ui .bt-toolbar .form-select { width: auto; min-width: 150px; max-width: 240px; border-radius: 8px; }
        .bt-ui .bt-search { position: relative; width: 260px; max-width: 100%; }
        .bt-ui .bt-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
        .bt-ui .bt-search input { padding-left: 32px; border-radius: 8px; }
        .bt-ui .bt-table thead th { background: #F9FAFB; font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--bt-muted); border-bottom: 1px solid var(--bt-border); padding: 10px 14px; white-space: nowrap; }
        .bt-ui .bt-table td { padding: 12px 14px; border-color: var(--bt-soft); font-size: 14px; }
        .bt-ui .bt-table tbody tr:hover td { background: #FAFAFB; }
        .bt-ui .bt-row-off td { background: #FAFAFA; color: #9CA3AF; }
        .bt-ui .bt-code { display: inline-flex; padding: 4px 11px; border-radius: 8px; font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 13px; font-weight: 700; letter-spacing: .03em; white-space: nowrap;
            background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%); border: 1px solid #FED7AA; color: #C2410C; }
        .bt-ui .bt-course { padding: 1px 7px; border-radius: 6px; background: #EEF2FF; color: #4338CA; font-weight: 600; font-size: 11.5px; }
        .bt-ui .bt-inst-code { padding: 1px 8px; border-radius: 6px; background: #F3F4F6; color: #374151; font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 11px; }
        .bt-ui .bt-sub { font-size: 12px; color: var(--bt-muted); }
        .bt-ui .bt-bar { height: 6px; border-radius: 999px; background: #F3F4F6; overflow: hidden; margin-top: 4px; }
        .bt-ui .bt-bar span { display: block; height: 100%; background: #22C55E; border-radius: 999px; }
        .bt-ui .bt-bar span.is-full { background: #EF4444; }
        .bt-ui .bt-switch { width: 2.4em; height: 1.3em; cursor: pointer; }
        .bt-ui .bt-switch:checked { background-color: #16A34A; border-color: #16A34A; }
        .bt-ui .bt-empty { text-align: center; color: var(--bt-muted); padding: 48px 12px !important; }
        .bt-ui .bt-empty > i { font-size: 36px; color: #D1D5DB; display: block; margin-bottom: 6px; }
        .bt-ui .bt-loading { display: none; position: absolute; inset: 0; z-index: 2; background: rgba(255, 255, 255, .6); align-items: center; justify-content: center; }
        .bt-ui .bt-foot { padding: 12px 16px; border-top: 1px solid var(--bt-soft); }
        .bt-ui .bt-foot .pagination { margin: 0; }
        .bt-ui .bt-modal { border: 0; border-radius: 14px; }
        .bt-ui .bt-modal-icon { width: 34px; height: 34px; border-radius: 9px; background: #FEF0E7; color: var(--bt-accent); display: inline-flex; align-items: center; justify-content: center; font-size: 17px; }
        .bt-ui .bt-code-input { font-family: 'IBM Plex Mono', ui-monospace, monospace; letter-spacing: .04em; }
    </style>
</div>

<script>
document.addEventListener('livewire:load', function () {
    [['batch', 'batchModal'], ['batch-delete', 'batchDeleteModal']].forEach(function ([name, id]) {
        window.addEventListener('open-' + name + '-modal', () => bootstrap.Modal.getOrCreateInstance(document.getElementById(id)).show());
        window.addEventListener('close-' + name + '-modal', () => {
            const instance = bootstrap.Modal.getInstance(document.getElementById(id));
            if (instance) { instance.hide(); }
        });
    });
});
</script>
