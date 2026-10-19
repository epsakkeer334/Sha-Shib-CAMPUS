<div class="content ce-ui">
    @php $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.'); @endphp

    <!-- Page Header -->
    <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
        <div class="my-auto mb-2">
            <h2 class="mb-1 fw-semibold">Course Eligibility</h2>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Master Data</li>
                    <li class="breadcrumb-item">Academic</li>
                    <li class="breadcrumb-item active">Course Eligibility</li>
                </ol>
            </nav>
        </div>
        <button type="button" wire:click="openModal" class="btn btn-primary d-flex align-items-center shadow-sm">
            <i class="ti ti-circle-plus me-2"></i> Add Criterion
        </button>
    </div>

    <!-- Intro + stats -->
    <div class="ce-intro mb-3">
        <div class="ce-intro-text">
            <span class="ce-intro-icon"><i class="ti ti-school"></i></span>
            <div>
                <div class="fw-semibold">Higher Secondary (12th) eligibility per course</div>
                <div class="small text-muted">Students see these criteria when they choose the course and enter a mark per subject. If the marks fall short, the application cannot be submitted unless an admin allows it with a reason. CGPA is converted with the board's factor (Higher Secondary Boards).</div>
            </div>
        </div>
        <div class="ce-stats">
            <div><b>{{ $stats['with'] }}<small>/{{ $stats['courses'] }}</small></b><span>courses with criteria</span></div>
            <div><b>{{ $stats['criteria'] }}</b><span>criteria</span></div>
            <div><b>{{ $stats['groups'] }}</b><span>12th groups</span></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="ce-filters mb-3">
        <div class="ce-search">
            <i class="ti ti-search"></i>
            <input type="search" class="form-control" placeholder="Search course or group…" wire:model.debounce.400ms="search">
        </div>
        <select class="form-select" style="max-width: 320px;" wire:model="courseFilter">
            <option value="">All courses</option>
            @foreach($courses as $c)
                <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->code }}){{ $c->status ? '' : ' — inactive' }}</option>
            @endforeach
        </select>
        <a href="{{ route('admin.masters.qualifying-subject-groups') }}" class="btn btn-light border ms-md-auto"><i class="ti ti-books me-1"></i> 12th Subject Groups</a>
    </div>

    <!-- Criteria grouped by course -->
    @forelse($criteria as $courseId => $rows)
        @php $course = $rows->first()->course; @endphp
        <div class="ce-course mb-3" wire:key="course-{{ $courseId }}">
            <div class="ce-course-head">
                <div class="d-flex align-items-center gap-2 min-w-0">
                    <span class="ce-course-code">{{ optional($course)->code }}</span>
                    <span class="fw-semibold text-truncate">{{ optional($course)->name }}</span>
                    <span class="ce-muted-chip">{{ $rows->count() }} {{ \Illuminate\Support\Str::plural('stream', $rows->count()) }} accepted</span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openModal({{ $courseId }})"><i class="ti ti-plus me-1"></i> Add stream</button>
            </div>
            <div class="ce-crit-grid">
                @foreach($rows as $criterion)
                    <div class="ce-crit {{ $criterion->status ? '' : 'is-off' }}" wire:key="crit-{{ $criterion->id }}">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div class="min-w-0">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="ce-group">{{ optional($criterion->group)->code }}</span>
                                    <span class="ce-board"><i class="ti ti-building-bank"></i> {{ $criterion->board_label }}</span>
                                    @unless($criterion->status)<span class="ce-off">Inactive</span>@endunless
                                </div>
                                <div class="small text-muted mt-1 text-truncate">{{ optional($criterion->group)->name }}</div>
                            </div>
                            <div class="d-flex gap-1 flex-shrink-0">
                                <button type="button" class="btn btn-sm btn-icon btn-light" title="{{ $criterion->status ? 'Deactivate' : 'Activate' }}" wire:click="toggleStatus({{ $criterion->id }})">
                                    <i class="ti ti-{{ $criterion->status ? 'toggle-right text-success' : 'toggle-left text-muted' }}"></i></button>
                                <button type="button" class="btn btn-sm btn-icon btn-light" title="Edit" wire:click="edit({{ $criterion->id }})"><i class="ti ti-edit"></i></button>
                                <button type="button" class="btn btn-sm btn-icon btn-light text-danger" title="Delete" wire:click="confirmDelete({{ $criterion->id }})"><i class="ti ti-trash"></i></button>
                            </div>
                        </div>
                        <div class="ce-mins">
                            @foreach($criterion->subjects as $s)
                                <div class="ce-min"><span>{{ optional($s->subject)->name }}</span><b>≥ {{ $fmt($s->min_percent) }}%</b></div>
                            @endforeach
                            @if($criterion->min_aggregate !== null)
                                <div class="ce-min ce-min-agg"><span>Average of these</span><b>≥ {{ $fmt($criterion->min_aggregate) }}%</b></div>
                            @endif
                        </div>
                        @if($criterion->remarks)<div class="small text-muted mt-2"><i class="ti ti-note"></i> {{ $criterion->remarks }}</div>@endif
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="ce-empty">
            <i class="ti ti-school-off"></i>
            <div class="fw-semibold">No eligibility criteria{{ $courseFilter || $search ? ' match the filter' : ' yet' }}</div>
            <div class="small text-muted mb-3">Courses without criteria accept any 12th stream and are not checked.</div>
            <button type="button" class="btn btn-primary" wire:click="openModal"><i class="ti ti-circle-plus me-1"></i> Add Criterion</button>
        </div>
    @endforelse

    <!-- Add / Edit modal -->
    <div class="modal fade" id="eligibilityModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <div class="modal-header bg-primary bg-opacity-10 border-bottom">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md bg-soft-primary bg-opacity-10 rounded-circle me-3 d-flex align-items-center justify-content-center">
                            <i class="ti ti-school text-primary fs-14"></i>
                        </div>
                        <div>
                            <h4 class="modal-title fw-semibold mb-0">{{ $recordId ? 'Edit' : 'Add' }} Eligibility Criterion</h4>
                            <small style="color: rgba(255, 255, 255, .85);">Course → board → 12th subject group → minimum marks</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-medium small" for="ce_course">Course <span class="text-danger">*</span></label>
                            <select id="ce_course" class="form-select @error('course_id') is-invalid @enderror" wire:model.defer="course_id">
                                <option value="">Select course</option>
                                @foreach($courses as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->code }}){{ $c->status ? '' : ' — inactive' }}</option>
                                @endforeach
                            </select>
                            @error('course_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium small" for="ce_board">Higher secondary board</label>
                            <select id="ce_board" class="form-select @error('higher_secondary_board_id') is-invalid @enderror" wire:model.defer="higher_secondary_board_id">
                                <option value="">Any board</option>
                                @foreach($boards as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                            </select>
                            <small class="text-muted">A board's own criterion is used before "Any board".</small>
                            @error('higher_secondary_board_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium small" for="ce_group">12th subject group <span class="text-danger">*</span></label>
                            <select id="ce_group" class="form-select @error('qualifying_subject_group_id') is-invalid @enderror" wire:model="qualifying_subject_group_id">
                                <option value="">Select group</option>
                                @foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->code }} — {{ $g->name }}</option>@endforeach
                            </select>
                            @error('qualifying_subject_group_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <div class="ce-form-box">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fw-semibold small"><i class="ti ti-percentage text-primary"></i> Minimum % per subject</span>
                                    <small class="text-muted">Leave blank if a subject is not required</small>
                                </div>
                                @if($groupSubjects->isEmpty())
                                    <div class="small text-muted py-2">Choose a 12th subject group to list its subjects.</div>
                                @else
                                    <div class="ce-min-grid">
                                        @foreach($groupSubjects as $subject)
                                            <label class="ce-min-input" wire:key="min-{{ $subject->id }}">
                                                <span>{{ $subject->name }}</span>
                                                <div class="input-group input-group-sm">
                                                    <input type="number" step="0.01" min="0" max="100" class="form-control @error('minimums.' . $subject->id) is-invalid @enderror"
                                                           wire:model.defer="minimums.{{ $subject->id }}" placeholder="e.g. 50">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                                @error('minimums.' . $subject->id)<span class="text-danger small">{{ $message }}</span>@enderror
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                                @error('minimums')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-medium small" for="ce_agg">Minimum average (optional)</label>
                            <div class="input-group">
                                <input id="ce_agg" type="number" step="0.01" min="0" max="100" class="form-control @error('min_aggregate') is-invalid @enderror" wire:model.defer="min_aggregate" placeholder="e.g. 50">
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Average of the subjects above.</small>
                            @error('min_aggregate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-medium small" for="ce_remarks">Note for students (optional)</label>
                            <textarea id="ce_remarks" rows="2" class="form-control @error('remarks') is-invalid @enderror" wire:model.defer="remarks" placeholder="e.g. English must also be passed"></textarea>
                            @error('remarks')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <div class="d-flex align-items-center justify-content-between ce-form-box py-2">
                                <div><span class="fw-semibold small">Status</span><div class="small text-muted">Inactive criteria are not shown or checked</div></div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="ceStatus" wire:model="status" value="1">
                                    <label class="form-check-label fw-medium" for="ceStatus">{{ $status ? 'Active' : 'Inactive' }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top">
                    <button type="button" class="btn btn-light px-4 me-2" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i> Cancel</button>
                    <button type="button" class="btn btn-primary px-4" wire:click="save" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save"><i class="ti ti-device-floppy me-1"></i> {{ $recordId ? 'Update' : 'Save' }} Criterion</span>
                        <span wire:loading wire:target="save"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete modal -->
    <div class="modal fade" id="eligibilityDeleteModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <i class="ti ti-alert-triangle text-danger fs-1 mb-3"></i>
                    <h5>Delete this criterion?</h5>
                    <p class="text-muted mb-4">Students of this course will no longer be checked against it. To pause it instead, set it Inactive.</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-danger px-4" wire:click="delete">Yes, Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .ce-ui .min-w-0 { min-width: 0; }
        .ce-ui .ce-intro { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; padding: 16px 20px; border-radius: 14px; border: 1px solid #DBEAFE;
            background: radial-gradient(circle at 100% 0, rgba(37, 99, 235, .10), transparent 45%), linear-gradient(135deg, #FFFFFF 0%, #F5F9FF 100%); }
        .ce-ui .ce-intro-text { display: flex; gap: 12px; align-items: flex-start; flex: 1 1 420px; }
        .ce-ui .ce-intro-icon { width: 42px; height: 42px; border-radius: 12px; background: #DBEAFE; color: #1D4ED8; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
        .ce-ui .ce-stats { display: flex; gap: 8px; flex-wrap: wrap; }
        .ce-ui .ce-stats > div { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 8px 14px; display: flex; flex-direction: column; min-width: 110px; }
        .ce-ui .ce-stats b { font-size: 20px; color: #111827; } .ce-ui .ce-stats b small { font-size: 13px; color: #9CA3AF; }
        .ce-ui .ce-stats span { font-size: 12px; color: #6B7280; }
        .ce-ui .ce-filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .ce-ui .ce-search { position: relative; flex: 1 1 240px; max-width: 360px; }
        .ce-ui .ce-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
        .ce-ui .ce-search input { padding-left: 34px; }
        .ce-ui .ce-course { background: #fff; border: 1px solid #E5E7EB; border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
        .ce-ui .ce-course-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 12px 16px; border-bottom: 1px solid #F1F2F4; flex-wrap: wrap; }
        .ce-ui .ce-course-code { padding: 2px 8px; border-radius: 6px; background: #EEF2FF; color: #4338CA; font-weight: 700; font-size: 12px; }
        .ce-ui .ce-muted-chip { padding: 1px 8px; border-radius: 999px; background: #F3F4F6; color: #6B7280; font-size: 12px; white-space: nowrap; }
        .ce-ui .ce-crit-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(300px, 100%), 1fr)); gap: 12px; padding: 14px 16px; }
        .ce-ui .ce-crit { border: 1px solid #E5E7EB; border-radius: 12px; padding: 12px 14px; transition: border-color .15s, box-shadow .15s; }
        .ce-ui .ce-crit:hover { border-color: #BFDBFE; box-shadow: 0 4px 12px rgba(37, 99, 235, .08); }
        .ce-ui .ce-crit.is-off { opacity: .6; background: #FAFAFA; }
        .ce-ui .ce-group { padding: 2px 9px; border-radius: 6px; background: #1E3A8A; color: #fff; font-weight: 700; font-size: 12px; letter-spacing: .02em; }
        .ce-ui .ce-board { font-size: 12px; color: #4B5563; display: inline-flex; gap: 3px; align-items: center; }
        .ce-ui .ce-off { padding: 1px 8px; border-radius: 999px; background: #FEE2E2; color: #991B1B; font-size: 11.5px; font-weight: 600; }
        .ce-ui .ce-mins { display: flex; flex-direction: column; gap: 4px; margin-top: 10px; }
        .ce-ui .ce-min { display: flex; justify-content: space-between; gap: 8px; padding: 5px 10px; border-radius: 8px; background: #F0FDF4; font-size: 13px; color: #065F46; }
        .ce-ui .ce-min-agg { background: #EFF6FF; color: #1D4ED8; }
        .ce-ui .btn-icon { width: 30px; height: 30px; padding: 0; display: inline-flex; align-items: center; justify-content: center; }
        .ce-ui .ce-empty { text-align: center; padding: 48px 16px; background: #fff; border: 1px dashed #D1D5DB; border-radius: 14px; }
        .ce-ui .ce-empty > i { font-size: 40px; color: #9CA3AF; }
        .ce-ui .ce-form-box { border: 1px solid #E5E7EB; border-radius: 12px; padding: 12px 14px; background: #F9FAFB; }
        .ce-ui .ce-min-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(170px, 100%), 1fr)); gap: 10px; }
        .ce-ui .ce-min-input { display: flex; flex-direction: column; gap: 4px; font-size: 13px; font-weight: 600; }
    </style>

    <script>
        document.addEventListener('livewire:load', function () {
            [['eligibility', 'eligibilityModal'], ['eligibility-delete', 'eligibilityDeleteModal']].forEach(([name, id]) => {
                window.addEventListener(`open-${name}-modal`, () => bootstrap.Modal.getOrCreateInstance(document.getElementById(id)).show());
                window.addEventListener(`close-${name}-modal`, () => {
                    const instance = bootstrap.Modal.getInstance(document.getElementById(id));
                    if (instance) { instance.hide(); }
                });
            });
        });
    </script>
</div>
