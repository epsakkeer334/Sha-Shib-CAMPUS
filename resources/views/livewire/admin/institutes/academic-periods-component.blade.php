<div class="content ap-ui">
    {{-- Header --}}
    <div class="ap-hero mb-3">
        <div class="min-w-0">
            <h2 class="mb-1 fw-bold">Academic Periods</h2>
            <nav>
                <ol class="breadcrumb mb-1 sl-crumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Institute Management</li>
                    <li class="breadcrumb-item active">Academic Periods</li>
                </ol>
            </nav>
            <div class="text-muted small">Semesters / terms of each course per intake. Students start period 1 when their ER number is issued; promotion follows results (Module 5).</div>
        </div>
        <button type="button" class="ap-hero-cta" wire:click="openGenerate"><i class="ti ti-calendar-plus"></i> Generate periods</button>
    </div>

    <div class="ap-cards mb-3">
        @foreach([
            ['Calendars', $stats['calendars'], 'ti ti-calendar-stats', 'all', 'Course + intake / batch'],
            ['Periods', $stats['periods'], 'ti ti-calendar-time', 'info', 'Semesters, terms, modules'],
            ['Running now', $stats['ongoing'], 'ti ti-player-play', 'ok', 'Today is inside the period'],
            ['Students placed', $stats['students'], 'ti ti-school', 'warn', 'In a current period'],
        ] as [$label, $value, $icon, $tone, $hint])
            <div class="ap-card ap-card-{{ $tone }}">
                <span class="ap-card-top"><span class="ap-card-label">{{ $label }}</span><span class="ap-card-icon"><i class="{{ $icon }}"></i></span></span>
                <span class="ap-card-count">{{ number_format($value) }}</span>
                <span class="ap-card-hint">{{ $hint }}</span>
            </div>
        @endforeach
    </div>

    <div class="ap-panel">
        <div class="ap-toolbar">
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
            <select class="form-select form-select-sm" wire:model="filterIntake" aria-label="Intake year">
                <option value="">All intakes</option>
                @foreach($intakes as $ay)<option value="{{ $ay->id }}">{{ $ay->name }}</option>@endforeach
            </select>
            @if($hasFilters)<button type="button" class="btn btn-sm btn-light" wire:click="$set('filterInstitute', ''); $set('filterCourse', ''); $set('filterIntake', '')"><i class="ti ti-x me-1"></i> Clear</button>@endif
            <span class="ms-auto small text-muted">{{ number_format($calendars->total()) }} {{ \Illuminate\Support\Str::plural('calendar', $calendars->total()) }}</span>
        </div>

        <div class="ap-list position-relative">
            <div class="ap-loading" wire:loading.delay.flex wire:target="filterInstitute, filterCourse, filterIntake, removeCalendar, gotoPage, nextPage, previousPage">
                <span class="spinner-border spinner-border-sm text-secondary"></span>
            </div>
            @forelse($calendars as $cal)
                @php
                    $key = "{$cal->institute_id}-{$cal->course_id}-{$cal->intake_academic_year_id}-" . ($cal->batch_id ?: 0);
                    $periods = $periodsByCalendar->get($key, collect());
                    $first = $periods->first();
                    $placed = $periods->sum('students_current');
                @endphp
                <section class="ap-cal" wire:key="cal-{{ $key }}">
                    <div class="ap-cal-head">
                        <span class="ap-cal-icon"><i class="ti ti-calendar-stats"></i></span>
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="ap-code">{{ optional($first->course)->code }}</span>
                                <span class="fw-semibold">{{ optional($first->course)->name }}</span>
                                @if($first->batch)
                                    <span class="ap-batch"><i class="ti ti-users-group"></i> {{ $first->batch->code }}</span>
                                @else
                                    <span class="ap-chip ap-chip-info">Intake {{ optional($first->intakeYear)->name }}</span>
                                @endif
                            </div>
                            <div class="ap-sub">
                                @if($isSuperAdmin)<i class="ti ti-building"></i> {{ optional($first->institute)->name }} · @endif
                                {{ optional($first->course)->period_summary }} · {{ $periods->count() }} periods ·
                                {{ $periods->first()->start_date->format('d M Y') }} → {{ $periods->last()->end_date->format('d M Y') }}
                            </div>
                        </div>
                        <span class="ap-chip ap-chip-muted" title="Students currently in one of these periods"><i class="ti ti-school"></i> {{ $placed }}</span>
                        @unless($placed)
                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeCalendar({{ $first->id }})"
                                    onclick="confirm('Remove all periods of this calendar?') || event.stopImmediatePropagation()" title="Remove these periods"><i class="ti ti-trash"></i></button>
                        @endunless
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 ap-table">
                            <thead><tr><th>Period</th><th>Year of study</th><th>Academic year</th><th>Start</th><th>End</th><th>Status</th><th>Students</th><th class="text-end"></th></tr></thead>
                            <tbody>
                                @foreach($periods as $p)
                                    @php $st = $p->display_status; @endphp
                                    <tr wire:key="period-{{ $p->id }}" class="{{ $st === 'ongoing' ? 'ap-row-now' : '' }}">
                                        <td class="fw-semibold">{{ $p->label }}</td>
                                        <td>Year {{ $p->year_of_study }}</td>
                                        <td>@if($p->academicYear)<span class="ap-ay">{{ $p->academicYear->name }}</span>@else<span class="text-danger small">No academic year</span>@endif</td>
                                        <td class="small">{{ $p->start_date->format('d M Y') }}</td>
                                        <td class="small">{{ $p->end_date->format('d M Y') }}</td>
                                        <td><span class="ap-chip ap-chip-{{ ['ongoing' => 'ok', 'completed' => 'muted', 'planned' => 'info'][$st] ?? 'info' }}">{{ \App\Models\Admin\CoursePeriod::STATUSES[$st] ?? ucfirst($st) }}</span></td>
                                        <td class="small">{{ $p->students_current ?: '—' }}</td>
                                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-warning" wire:click="editPeriod({{ $p->id }})" title="Edit dates / status"><i class="ti ti-edit"></i></button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @empty
                <div class="ap-empty">
                    <i class="ti ti-calendar-off"></i>
                    <div class="fw-semibold">{{ $hasFilters ? 'No periods match these filters' : 'No academic periods yet' }}</div>
                    <div class="small text-muted">Generate the periods of a course from its period structure (Master Data → Courses).</div>
                    @unless($hasFilters)<button type="button" class="btn btn-sm btn-primary mt-2" wire:click="openGenerate"><i class="ti ti-calendar-plus me-1"></i> Generate periods</button>@endunless
                </div>
            @endforelse
        </div>
        @if($calendars->hasPages())<div class="ap-foot">{{ $calendars->links() }}</div>@endif
    </div>

    {{-- Generate --}}
    <div class="modal fade" id="periodsModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content ap-modal">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-2"><span class="ap-cal-icon"><i class="ti ti-calendar-plus"></i></span><h5 class="modal-title mb-0">Generate academic periods</h5></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        @if($isSuperAdmin)
                            <div class="col-md-6">
                                <label class="form-label fw-medium small" for="apInst">Institute <span class="text-danger">*</span></label>
                                <select id="apInst" class="form-select @error('instituteId') is-invalid @enderror" wire:model="instituteId">
                                    <option value="">Select institute</option>
                                    @foreach($institutes as $inst)<option value="{{ $inst->id }}">{{ $inst->name }}</option>@endforeach
                                </select>
                                @error('instituteId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @endif
                        <div class="col-md-6">
                            <label class="form-label fw-medium small" for="apCourse">Course <span class="text-danger">*</span></label>
                            <select id="apCourse" class="form-select @error('courseId') is-invalid @enderror" wire:model="courseId" @disabled(!$instituteId)>
                                <option value="">{{ $instituteId ? 'Select course' : 'Select institute first' }}</option>
                                @foreach($modalCourses as $c)<option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>@endforeach
                            </select>
                            @error('courseId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @if($modalCourse)<small class="text-muted">{{ $modalCourse->period_summary }}</small>@endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium small" for="apBatch">Batch <span class="text-muted fw-normal">(optional)</span></label>
                            <select id="apBatch" class="form-select @error('batchId') is-invalid @enderror" wire:model="batchId" @disabled(!$courseId)>
                                <option value="">Whole intake (no specific batch)</option>
                                @foreach($modalBatches as $b)<option value="{{ $b->id }}">{{ $b->code }} · {{ $b->name }}</option>@endforeach
                            </select>
                            @error('batchId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium small" for="apStart">Period 1 starts on <span class="text-danger">*</span></label>
                            <input id="apStart" type="date" class="form-control @error('startDate') is-invalid @enderror" wire:model="startDate">
                            @error('startDate') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    @if($preview)
                        <div class="ap-preview mt-3">
                            <div class="fw-semibold small mb-2"><i class="ti ti-eye"></i> Will create {{ count($preview) }} {{ \Illuminate\Support\Str::plural('period', count($preview)) }}</div>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>Period</th><th>Year</th><th>Academic year</th><th>Start</th><th>End</th></tr></thead>
                                    <tbody>
                                        @foreach($preview as $row)
                                            <tr>
                                                <td class="fw-medium">{{ $row['label'] }}</td><td>{{ $row['year'] }}</td>
                                                <td>@if($row['academic_year']){{ $row['academic_year'] }}@else<span class="text-danger small">not set up</span>@endif</td>
                                                <td>{{ $row['from']->format('d M Y') }}</td><td>{{ $row['to']->format('d M Y') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="generate" wire:loading.attr="disabled"><i class="ti ti-check me-1"></i> Generate</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit one period --}}
    <div class="modal fade" id="periodEditModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content ap-modal">
                <div class="modal-header"><h5 class="modal-title mb-0">Edit period</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium small" for="apPStart">Start date</label>
                            <input id="apPStart" type="date" class="form-control @error('periodStart') is-invalid @enderror" wire:model.defer="periodStart">
                            @error('periodStart') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium small" for="apPEnd">End date</label>
                            <input id="apPEnd" type="date" class="form-control @error('periodEnd') is-invalid @enderror" wire:model.defer="periodEnd">
                            @error('periodEnd') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium small" for="apPStatus">Status</label>
                            <select id="apPStatus" class="form-select" wire:model.defer="periodStatus">
                                @foreach(\App\Models\Admin\CoursePeriod::STATUSES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                            </select>
                            <small class="text-muted">"Completed" is normally set when results are published (Module 5).</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="savePeriod" wire:loading.attr="disabled"><i class="ti ti-check me-1"></i> Save</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .ap-ui { --ap-border: #E5E7EB; --ap-soft: #F1F2F4; --ap-ink: #111827; --ap-muted: #6B7280; --ap-accent: #F26522; }
        .ap-ui .min-w-0 { min-width: 0; }
        .ap-ui .ap-hero { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; padding: 18px 22px; border-radius: 14px; border: 1px solid var(--ap-border);
            background: radial-gradient(circle at 100% 0, rgba(242, 101, 34, .12), transparent 45%), linear-gradient(135deg, #FFFFFF 0%, #FFF8F3 100%); }
        .ap-ui .ap-hero h2 { font-size: 22px; color: var(--ap-ink); }
        .ap-ui .ap-hero-cta { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 999px; border: 0; background: var(--ap-accent); color: #fff; font-size: 14px; font-weight: 500; box-shadow: 0 6px 16px rgba(242, 101, 34, .3); }
        .ap-ui .ap-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
        .ap-ui .ap-card { --tone: #4338CA; --tone-soft: #EEF2FF; display: flex; flex-direction: column; gap: 2px; padding: 14px 16px 16px; background: #fff; border: 1px solid var(--ap-border); border-top: 3px solid var(--tone); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
        .ap-ui .ap-card-top { display: flex; justify-content: space-between; align-items: center; }
        .ap-ui .ap-card-label { font-size: 13px; font-weight: 600; color: #374151; }
        .ap-ui .ap-card-icon { width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 19px; background: var(--tone-soft); color: var(--tone); }
        .ap-ui .ap-card-count { font-size: 26px; font-weight: 700; color: var(--ap-ink); line-height: 1.15; }
        .ap-ui .ap-card-hint { font-size: 12px; color: var(--ap-muted); }
        .ap-ui .ap-card-all { --tone: #4338CA; --tone-soft: #EEF2FF; } .ap-ui .ap-card-info { --tone: #0284C7; --tone-soft: #E0F2FE; }
        .ap-ui .ap-card-ok { --tone: #16A34A; --tone-soft: #DCFCE7; } .ap-ui .ap-card-warn { --tone: #D97706; --tone-soft: #FEF3C7; }
        .ap-ui .ap-panel { background: #fff; border: 1px solid var(--ap-border); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); overflow: hidden; }
        .ap-ui .ap-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--ap-soft); background: #FCFCFD; }
        .ap-ui .ap-toolbar .form-select { width: auto; min-width: 150px; max-width: 240px; border-radius: 8px; }
        .ap-ui .ap-list { padding: 14px; display: flex; flex-direction: column; gap: 12px; background: #FCFCFD; min-height: 80px; }
        .ap-ui .ap-cal { background: #fff; border: 1px solid var(--ap-border); border-radius: 12px; overflow: hidden; }
        .ap-ui .ap-cal-head { display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: linear-gradient(90deg, #FFF7F2 0%, #FFFFFF 55%); border-bottom: 1px solid var(--ap-soft); }
        .ap-ui .ap-cal-icon { width: 38px; height: 38px; border-radius: 10px; background: #FEF0E7; color: var(--ap-accent); display: inline-flex; align-items: center; justify-content: center; font-size: 19px; flex-shrink: 0; }
        .ap-ui .ap-code { padding: 1px 7px; border-radius: 6px; background: #EEF2FF; color: #4338CA; font-weight: 600; font-size: 11.5px; }
        .ap-ui .ap-batch { display: inline-flex; align-items: center; gap: 3px; padding: 1px 8px; border-radius: 6px; background: #FFF7ED; border: 1px solid #FED7AA; color: #C2410C; font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 11.5px; font-weight: 600; }
        .ap-ui .ap-sub { font-size: 12px; color: var(--ap-muted); margin-top: 2px; }
        .ap-ui .ap-ay { padding: 1px 8px; border-radius: 6px; background: #F3F4F6; font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 12px; }
        .ap-ui .ap-chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
        .ap-ui .ap-chip-ok { background: #DCFCE7; color: #15803D; } .ap-ui .ap-chip-info { background: #E0F2FE; color: #0369A1; } .ap-ui .ap-chip-muted { background: #F3F4F6; color: var(--ap-muted); }
        .ap-ui .ap-table thead th { background: #fff; font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--ap-muted); padding: 9px 14px; white-space: nowrap; }
        .ap-ui .ap-table td { padding: 9px 14px; border-color: var(--ap-soft); font-size: 13.5px; }
        .ap-ui .ap-row-now td { background: #F0FDF4; }
        .ap-ui .ap-row-now td:first-child { box-shadow: inset 3px 0 0 #22C55E; }
        .ap-ui .ap-empty { text-align: center; color: var(--ap-muted); padding: 48px 12px; }
        .ap-ui .ap-empty > i { font-size: 36px; color: #D1D5DB; display: block; margin-bottom: 6px; }
        .ap-ui .ap-loading { display: none; position: absolute; inset: 0; z-index: 2; background: rgba(255, 255, 255, .6); align-items: center; justify-content: center; }
        .ap-ui .ap-foot { padding: 12px 16px; border-top: 1px solid var(--ap-soft); }
        .ap-ui .ap-foot .pagination { margin: 0; }
        .ap-ui .ap-modal { border: 0; border-radius: 14px; }
        .ap-ui .ap-preview { border: 1px dashed #FDBA8C; border-radius: 10px; padding: 12px; background: #FFFBF7; max-height: 320px; overflow: auto; }
    </style>
</div>

<script>
document.addEventListener('livewire:load', function () {
    [['periods', 'periodsModal'], ['period-edit', 'periodEditModal']].forEach(function ([name, id]) {
        window.addEventListener('open-' + name + '-modal', () => bootstrap.Modal.getOrCreateInstance(document.getElementById(id)).show());
        window.addEventListener('close-' + name + '-modal', () => {
            const instance = bootstrap.Modal.getInstance(document.getElementById(id));
            if (instance) { instance.hide(); }
        });
    });
});
</script>
