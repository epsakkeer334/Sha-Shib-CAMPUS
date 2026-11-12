<div class="content ic-ui">
    {{-- Header --}}
    <div class="ic-hero mb-3">
        <div class="min-w-0">
            <h2 class="mb-1 fw-bold">Institute Courses</h2>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Institute Management</li>
                    @if($scopeInstituteName)
                        <li class="breadcrumb-item"><a href="{{ route('admin.institute-courses') }}">Institute Courses</a></li>
                        <li class="breadcrumb-item active">{{ $scopeInstituteName }}</li>
                    @else
                        <li class="breadcrumb-item active">Institute Courses</li>
                    @endif
                </ol>
            </nav>
            <div class="text-muted small">
                {{ $scopeInstituteName ? 'Courses offered by ' . $scopeInstituteName . '.' : 'Courses each institute offers.' }}
                Students can only join active courses of their institute.
            </div>
        </div>
        @if($canManage)
            <button type="button" class="ic-hero-cta" wire:click="openModal"><i class="ti ti-circle-plus"></i> Assign Courses</button>
        @endif
    </div>

    {{-- Summary (click to filter) --}}
    <div class="ic-cards mb-3">
        @foreach([
            ['', 'Offerings', $stats['total'], 'ti ti-books', 'all', 'All assigned courses'],
            ['active', 'Active', $stats['active'], 'ti ti-circle-check', 'ok', 'Open to new students'],
            ['inactive', 'Inactive', $stats['inactive'], 'ti ti-circle-off', 'muted', 'Not offered to new students'],
            ['setup', 'Needs fees', $stats['setup'], 'ti ti-alert-triangle', 'warn', 'Active, no fee structure yet'],
        ] as [$key, $label, $count, $icon, $tone, $hint])
            <button type="button" class="ic-card ic-card-{{ $tone }} {{ $filterStatus === $key ? 'is-active' : '' }}" wire:click="$set('filterStatus', '{{ $key }}')">
                <span class="ic-card-top"><span class="ic-card-label">{{ $label }}</span><span class="ic-card-icon"><i class="{{ $icon }}"></i></span></span>
                <span class="ic-card-count">{{ number_format($count) }}</span>
                <span class="ic-card-hint">{{ $hint }}</span>
            </button>
        @endforeach
        <div class="ic-card ic-card-info">
            <span class="ic-card-top"><span class="ic-card-label">Students</span><span class="ic-card-icon"><i class="ti ti-school"></i></span></span>
            <span class="ic-card-count">{{ number_format($stats['students']) }}</span>
            <span class="ic-card-hint">In these courses</span>
        </div>
    </div>

    <div class="ic-panel">
        <div class="ic-toolbar">
            <div class="ic-search">
                <i class="ti ti-search"></i>
                <input type="search" class="form-control form-control-sm" placeholder="Course code or name{{ $isSuperAdmin && !$scopeInstituteId ? ', institute' : '' }}" wire:model.debounce.400ms="search" aria-label="Search courses">
            </div>
            @if($isSuperAdmin && !$scopeInstituteId)
                <select class="form-select form-select-sm" wire:model="filterInstitute" aria-label="Institute">
                    <option value="">All institutes</option>
                    @foreach($institutes as $inst)<option value="{{ $inst->id }}">{{ $inst->name }}</option>@endforeach
                </select>
            @endif
            <select class="form-select form-select-sm" wire:model="filterStatus" aria-label="Status">
                <option value="">Any status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="setup">Needs fees</option>
            </select>
            @if($hasFilters)
                <button type="button" class="btn btn-sm btn-light" wire:click="clearFilters"><i class="ti ti-x me-1"></i> Clear</button>
            @endif
            <span class="ms-auto small text-muted">{{ number_format($offerings->total()) }} {{ \Illuminate\Support\Str::plural('course', $offerings->total()) }}</span>
        </div>

        <div class="table-responsive position-relative">
            <div class="ic-loading" wire:loading.delay.flex wire:target="search, filterInstitute, filterStatus, clearFilters, toggleStatus, delete, gotoPage, nextPage, previousPage">
                <span class="spinner-border spinner-border-sm text-secondary"></span>
            </div>
            <table class="table align-middle mb-0 ic-table">
                <thead>
                    <tr>
                        <th>Institute</th>
                        <th>Course</th>
                        <th>Course Code</th>
                        <th>Set-up</th>
                        <th class="text-center">Students</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($offerings as $row)
                        @php $course = $row->course; @endphp
                        <tr wire:key="ic-{{ $row->id }}" class="{{ $row->status ? '' : 'ic-row-off' }}">
                            <td style="min-width: 190px;">
                                <div class="ic-inst">
                                    <span class="ic-inst-icon"><i class="ti ti-building"></i></span>
                                    <span class="min-w-0">
                                        <span class="d-block fw-medium text-truncate" title="{{ optional($row->institute)->name }}">{{ optional($row->institute)->name ?? '—' }}</span>
                                        @if(optional($row->institute)->code)<span class="ic-inst-code">{{ $row->institute->code }}</span>@endif
                                    </span>
                                </div>
                            </td>
                            <td style="min-width: 210px;">
                                <div class="fw-semibold">{{ optional($course)->name ?? 'Course removed' }}</div>
                                @if($course)
                                    <div class="ic-sub">
                                        <i class="ti ti-clock"></i> {{ $course->duration_months ? $course->duration_months . ' months' : 'Duration not set' }}
                                        <span class="ic-dot">·</span> {{ $course->period_summary }}
                                    </div>
                                @endif
                            </td>
                            <td><span class="ic-code">{{ optional($course)->code ?: '—' }}</span></td>
                            <td>
                                <div class="ic-chips">
                                    @php
                                        $chips = [
                                            [$row->fee_heads_count, 'ti ti-receipt-2', $row->fee_heads_count . ' ' . \Illuminate\Support\Str::plural('fee head', $row->fee_heads_count), 'No fees', $links['fees'] ? route('admin.onboarding.fee-structure', ['institute' => $row->institute_id, 'course' => $row->course_id]) : null, true],
                                            [$row->open_batches_count, 'ti ti-users-group', $row->open_batches_count . ' open ' . \Illuminate\Support\Str::plural('batch', $row->open_batches_count) . ($row->batches_count > $row->open_batches_count ? ' of ' . $row->batches_count : ''), $row->batches_count ? 'No open batch' : 'No batches', $links['batches'] ? route('admin.batches', ['institute' => $row->institute_id, 'course' => $row->course_id]) : null, false],
                                            [$row->addons_count, 'ti ti-puzzle', $row->addons_count . ' ' . \Illuminate\Support\Str::plural('add-on', $row->addons_count), null, $links['addons'] ? route('admin.addon-programs', ['institute' => $row->institute_id, 'course' => $row->course_id]) : null, false],
                                        ];
                                    @endphp
                                    @foreach($chips as [$n, $icon, $yes, $no, $url, $required])
                                        @continue(!$n && $no === null)
                                        @php $tone = $n ? 'ok' : ($required && $row->status ? 'warn' : 'none'); @endphp
                                        @if($url)
                                            <a href="{{ $url }}" class="ic-chip ic-chip-{{ $tone }}"><i class="{{ $icon }}"></i> {{ $n ? $yes : $no }}</a>
                                        @else
                                            <span class="ic-chip ic-chip-{{ $tone }}"><i class="{{ $icon }}"></i> {{ $n ? $yes : $no }}</span>
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                            <td class="text-center">
                                @if($links['students'] && $row->students_count)
                                    <a href="{{ route('admin.students', array_filter(['institute' => $isSuperAdmin ? $row->institute_id : null, 'course' => $row->course_id])) }}" class="ic-count" title="Open these students">{{ number_format($row->students_count) }}</a>
                                @else
                                    <span class="ic-count {{ $row->students_count ? '' : 'is-zero' }}">{{ number_format($row->students_count) }}</span>
                                @endif
                            </td>
                            <td>
                                @if($canManage)
                                    <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
                                        <input class="form-check-input ic-switch" type="checkbox" role="switch" id="icStatus{{ $row->id }}" @checked($row->status)
                                               wire:click="toggleStatus({{ $row->id }})" wire:loading.attr="disabled" wire:target="toggleStatus"
                                               aria-label="{{ $row->status ? 'Deactivate' : 'Activate' }} {{ optional($course)->code }}">
                                        <label class="small {{ $row->status ? 'text-success fw-semibold' : 'text-muted' }}" for="icStatus{{ $row->id }}">{{ $row->status ? 'Active' : 'Inactive' }}</label>
                                    </div>
                                @else
                                    <span class="badge {{ $row->status ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $row->status ? 'Active' : 'Inactive' }}</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if($canManage)
                                    <button type="button" class="btn btn-sm btn-outline-warning" wire:click="edit({{ $row->id }})" title="Edit"><i class="ti ti-edit"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="confirmDelete({{ $row->id }})" title="Remove from institute" @disabled($row->students_count)><i class="ti ti-trash"></i></button>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="ic-empty">
                                <i class="ti ti-books"></i>
                                <div class="fw-semibold">{{ $hasFilters ? 'No courses match these filters' : 'No courses assigned yet' }}</div>
                                @if(!$hasFilters && $canManage)
                                    <button type="button" class="btn btn-sm btn-primary mt-2" wire:click="openModal"><i class="ti ti-circle-plus me-1"></i> Assign the first course</button>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($offerings->hasPages())<div class="ic-foot">{{ $offerings->links() }}</div>@endif
    </div>

    @if($canManage)
    <!-- Assign / Edit Modal (only for users who may manage) -->
    <div class="modal fade" id="instituteCourseModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <div class="modal-header bg-primary bg-opacity-10 border-bottom">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md bg-soft-primary bg-opacity-10 rounded-circle me-3 d-flex align-items-center justify-content-center">
                            <i class="ti ti-books text-primary fs-14"></i>
                        </div>
                        <div>
                            <h4 class="modal-title fw-semibold mb-0">{{ $isEdit ? 'Edit Course Offering' : 'Assign Courses to Institute' }}</h4>
                            <small class="text-muted">Students can only join active courses of their institute</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" wire:click="closeModal"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="card border-0 bg-light bg-opacity-50 mb-3">
                        <div class="card-body p-3">
                            @if($isEdit && $editing)
                                <dl class="row mb-0 small">
                                    <dt class="col-sm-3">Institute</dt>
                                    <dd class="col-sm-9">{{ $editing->institute->name ?? '—' }} ({{ $editing->institute->code ?? '' }})</dd>
                                    <dt class="col-sm-3">Course</dt>
                                    <dd class="col-sm-9 mb-0">{{ $editing->course->name ?? '—' }} ({{ $editing->course->code ?? '' }})</dd>
                                </dl>
                            @else
                                <div class="mb-3">
                                    <label class="form-label fw-medium small">Institute <span class="text-danger">*</span></label>
                                    @if($scopeInstituteId)
                                        <input type="text" class="form-control bg-light" value="{{ $scopeInstituteName }}" readonly>
                                    @else
                                        <select class="form-select @error('institute_id') is-invalid @enderror" wire:model="institute_id">
                                            <option value="">Select institute</option>
                                            @foreach($institutes as $institute)
                                                <option value="{{ $institute->id }}">{{ $institute->name }} ({{ $institute->code }})</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    @error('institute_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>

                                <label class="form-label fw-medium small">Courses <span class="text-danger">*</span></label>
                                @if(!$institute_id)
                                    <p class="text-muted small mb-0">Select an institute first.</p>
                                @elseif($availableCourses->isEmpty())
                                    <p class="text-muted small mb-0">
                                        This institute already offers every active course. Add new courses in Master Data → Academic → Courses.
                                    </p>
                                @else
                                    <div class="row g-2">
                                        @foreach($availableCourses as $course)
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="course{{ $course->id }}"
                                                           value="{{ $course->id }}" wire:model.defer="course_ids">
                                                    <label class="form-check-label" for="course{{ $course->id }}">
                                                        {{ $course->name }} <span class="text-muted">({{ $course->code }})</span>
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                @error('course_ids') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            @endif
                        </div>
                    </div>

                    <!-- Status (bottom of form) -->
                    <div class="card border-0 bg-light bg-opacity-50">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <i class="ti ti-toggle-right text-primary me-2 fs-14"></i>
                                <div>
                                    <h6 class="fw-semibold mb-0">Status</h6>
                                    <small class="text-muted">Inactive = not offered to new students</small>
                                </div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="instituteCourseStatus" wire:model="status" value="1">
                                <label class="form-check-label fw-medium" for="instituteCourseStatus">{{ $status ? 'Active' : 'Inactive' }}</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top">
                    <button class="btn btn-light px-4 me-2" wire:click="closeModal">
                        <i class="ti ti-x me-1 fs-16"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary px-4" wire:click="save" wire:loading.attr="disabled">
                        <i class="ti ti-device-floppy me-1 fs-16"></i> {{ $isEdit ? 'Update' : 'Assign Courses' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="instituteCourseDeleteModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <i class="ti ti-alert-triangle text-danger fs-1 mb-3"></i>
                    <h5>Remove Course</h5>
                    <p class="text-muted mb-4">Remove this course from the institute? You can assign it again later.</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-danger px-4" wire:click="delete">Yes, Remove</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <style>
        .ic-ui { --ic-border: #E5E7EB; --ic-soft: #F1F2F4; --ic-ink: #111827; --ic-muted: #6B7280; --ic-accent: #F26522; }
        .ic-ui .min-w-0 { min-width: 0; }
        .ic-ui .ic-hero { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; padding: 18px 22px; border-radius: 14px; border: 1px solid var(--ic-border);
            background: radial-gradient(circle at 100% 0, rgba(242, 101, 34, .12), transparent 45%), linear-gradient(135deg, #FFFFFF 0%, #FFF8F3 100%); }
        .ic-ui .ic-hero h2 { font-size: 22px; color: var(--ic-ink); }
        .ic-ui .ic-hero-cta { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 999px; border: 0; background: var(--ic-accent); color: #fff; font-size: 14px; font-weight: 500; box-shadow: 0 6px 16px rgba(242, 101, 34, .3); }
        .ic-ui .ic-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
        .ic-ui .ic-card { --tone: #4338CA; --tone-soft: #EEF2FF; position: relative; overflow: hidden; display: flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: 14px 16px 16px; background: #fff; border: 1px solid var(--ic-border); border-radius: 14px; text-align: left; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); transition: box-shadow .15s, transform .15s; }
        .ic-ui button.ic-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16, 24, 40, .08); }
        .ic-ui .ic-card::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: var(--tone); opacity: 0; }
        .ic-ui .ic-card.is-active { border-color: var(--tone); background: linear-gradient(180deg, var(--tone-soft) 0%, #fff 70%); }
        .ic-ui .ic-card.is-active::after { opacity: 1; }
        .ic-ui .ic-card-top { display: flex; justify-content: space-between; align-items: center; width: 100%; }
        .ic-ui .ic-card-label { font-size: 13px; font-weight: 600; color: #374151; }
        .ic-ui .ic-card-icon { width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 19px; background: var(--tone-soft); color: var(--tone); }
        .ic-ui .ic-card-count { font-size: 26px; font-weight: 700; color: var(--ic-ink); line-height: 1.15; }
        .ic-ui .ic-card-hint { font-size: 12px; color: var(--ic-muted); }
        .ic-ui .ic-card-ok { --tone: #16A34A; --tone-soft: #DCFCE7; } .ic-ui .ic-card-muted { --tone: #6B7280; --tone-soft: #F3F4F6; }
        .ic-ui .ic-card-warn { --tone: #D97706; --tone-soft: #FEF3C7; } .ic-ui .ic-card-info { --tone: #0284C7; --tone-soft: #E0F2FE; }
        .ic-ui .ic-panel { background: #fff; border: 1px solid var(--ic-border); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); overflow: hidden; }
        .ic-ui .ic-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--ic-soft); background: #FCFCFD; }
        .ic-ui .ic-toolbar .form-select { width: auto; min-width: 150px; max-width: 240px; border-radius: 8px; }
        .ic-ui .ic-search { position: relative; width: 280px; max-width: 100%; }
        .ic-ui .ic-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
        .ic-ui .ic-search input { padding-left: 32px; border-radius: 8px; }
        .ic-ui .ic-table thead th { background: #F9FAFB; font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--ic-muted); border-bottom: 1px solid var(--ic-border); padding: 10px 14px; white-space: nowrap; }
        .ic-ui .ic-table td { padding: 12px 14px; border-color: var(--ic-soft); font-size: 14px; }
        .ic-ui .ic-table tbody tr:hover td { background: #FAFAFB; }
        .ic-ui .ic-row-off td { background: #FAFAFA; color: #9CA3AF; }
        .ic-ui .ic-code { display: inline-flex; padding: 4px 11px; border-radius: 8px; font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 13px; font-weight: 700; letter-spacing: .03em; white-space: nowrap;
            background: #EEF2FF; border: 1px solid #C7D2FE; color: #4338CA; }
        .ic-ui .ic-row-off .ic-code { background: #F3F4F6; border-color: #E5E7EB; color: #6B7280; }
        .ic-ui .ic-inst { display: flex; align-items: center; gap: 10px; max-width: 260px; }
        .ic-ui .ic-inst-icon { flex-shrink: 0; width: 34px; height: 34px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; background: #FEF0E7; color: #F26522; font-size: 17px; }
        .ic-ui .ic-row-off .ic-inst-icon { background: #F3F4F6; color: #9CA3AF; }
        .ic-ui .ic-inst-code { padding: 1px 8px; border-radius: 6px; background: #F3F4F6; color: #374151; font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 11px; }
        .ic-ui .ic-sub { font-size: 12px; color: var(--ic-muted); margin-top: 2px; }
        .ic-ui .ic-dot { margin: 0 3px; }
        .ic-ui .ic-chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .ic-ui .ic-chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 500; white-space: nowrap; border: 1px solid transparent; text-decoration: none; }
        .ic-ui a.ic-chip:hover { filter: brightness(.96); text-decoration: underline; }
        .ic-ui .ic-chip-ok { background: #ECFDF3; border-color: #BBF7D0; color: #15803D; }
        .ic-ui .ic-chip-warn { background: #FFFBEB; border-color: #FDE68A; color: #B45309; }
        .ic-ui .ic-chip-none { background: #F9FAFB; border-color: #E5E7EB; color: #6B7280; }
        .ic-ui .ic-count { display: inline-flex; min-width: 34px; justify-content: center; padding: 2px 10px; border-radius: 999px; background: #E0F2FE; color: #0369A1; font-weight: 600; font-size: 13px; text-decoration: none; }
        .ic-ui a.ic-count:hover { background: #BAE6FD; }
        .ic-ui .ic-count.is-zero { background: #F3F4F6; color: #9CA3AF; }
        .ic-ui .ic-switch { width: 2.4em; height: 1.3em; cursor: pointer; }
        .ic-ui .ic-switch:checked { background-color: #16A34A; border-color: #16A34A; }
        .ic-ui .ic-empty { text-align: center; color: var(--ic-muted); padding: 48px 12px !important; }
        .ic-ui .ic-empty > i { font-size: 36px; color: #D1D5DB; display: block; margin-bottom: 6px; }
        .ic-ui .ic-loading { display: none; position: absolute; inset: 0; z-index: 2; background: rgba(255, 255, 255, .6); align-items: center; justify-content: center; }
        .ic-ui .ic-foot { padding: 12px 16px; border-top: 1px solid var(--ic-soft); }
        .ic-ui .ic-foot .pagination { margin: 0; }
        @media (max-width: 575.98px) {
            .ic-ui .ic-hero { padding: 14px 16px; }
            .ic-ui .ic-hero-cta { width: 100%; justify-content: center; }
            .ic-ui .ic-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .ic-ui .ic-search, .ic-ui .ic-toolbar .form-select { width: 100%; max-width: none; }
        }
    </style>
</div>

<script>
document.addEventListener('livewire:load', function () {
    [
        { name: 'institute-course', id: 'instituteCourseModal' },
        { name: 'institute-course-delete', id: 'instituteCourseDeleteModal' },
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
