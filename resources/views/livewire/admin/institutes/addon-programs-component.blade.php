<div class="content ap-ui">
    {{-- Header --}}
    <div class="ap-hero mb-3">
        <div class="min-w-0">
            <h2 class="mb-1 fw-bold">Add-on Programs</h2>
            <nav>
                <ol class="breadcrumb mb-1 sl-crumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Institute Management</li>
                    <li class="breadcrumb-item active">Add-on Programs</li>
                </ol>
            </nav>
            <div class="text-muted small">Optional extras for each course. Applicants choose them on the admissions portal; a paid add-on adds a fee to the student.</div>
        </div>
        <button type="button" class="ap-hero-cta" wire:click="create"><i class="ti ti-circle-plus"></i> Add add-on</button>
    </div>

    {{-- Summary --}}
    <div class="ap-cards mb-3">
        @foreach([
            ['Add-ons', $stats['total'], 'ti ti-puzzle', 'all', 'Across all courses'],
            ['Offered', $stats['active'], 'ti ti-circle-check', 'ok', 'Shown to applicants'],
            ['Paid', $stats['paid'], 'ti ti-currency-rupee', 'warn', 'Add a fee when chosen'],
            ['Students', $stats['students'], 'ti ti-school', 'info', 'Chose at least one'],
        ] as [$label, $count, $icon, $tone, $hint])
            <div class="ap-card ap-card-{{ $tone }}">
                <span class="ap-card-top"><span class="ap-card-label">{{ $label }}</span><span class="ap-card-icon"><i class="{{ $icon }}"></i></span></span>
                <span class="ap-card-count">{{ number_format($count) }}</span>
                <span class="ap-card-hint">{{ $hint }}</span>
            </div>
        @endforeach
    </div>

    <div class="ap-toolbar mb-3">
        <div class="ap-search">
            <i class="ti ti-search"></i>
            <input type="search" class="form-control form-control-sm" placeholder="Add-on or course" wire:model.debounce.400ms="search" aria-label="Search add-on programs">
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
        <span class="ms-auto small text-muted" wire:loading.delay wire:target="search, filterInstitute, filterCourse, clearFilters, toggleStatus, setMax, delete"><span class="spinner-border spinner-border-sm"></span></span>
    </div>

    {{-- One card per institute course --}}
    <div class="ap-courses">
        @forelse($instituteCourses as $ic)
            @php $list = $programs->get($ic->institute_id . '-' . $ic->course_id, collect()); @endphp
            <section class="ap-course {{ $ic->status ? '' : 'is-off' }}" wire:key="ic-{{ $ic->id }}">
                <header class="ap-course-head">
                    <div class="d-flex align-items-center gap-2 min-w-0">
                        <span class="ap-course-code">{{ optional($ic->course)->code }}</span>
                        <div class="min-w-0">
                            <div class="fw-semibold text-truncate">{{ optional($ic->course)->name }}</div>
                            <div class="ap-sub">
                                @if($isSuperAdmin){{ optional($ic->institute)->name }} · @endif
                                {{ $list->count() }} {{ \Illuminate\Support\Str::plural('add-on', $list->count()) }}
                                @unless($ic->status) · <span class="text-danger">course not offered now</span>@endunless
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <label class="ap-limit" title="How many add-ons an applicant may choose">
                            <i class="ti ti-list-check"></i>
                            <span>Choice limit</span>
                            <select class="form-select form-select-sm" wire:change="setMax({{ $ic->id }}, $event.target.value)" aria-label="Choice limit for {{ optional($ic->course)->code }}">
                                <option value="" @selected($ic->max_addon_programs === null)>No limit</option>
                                @for($n = 1; $n <= 10; $n++)<option value="{{ $n }}" @selected((int) $ic->max_addon_programs === $n)>Up to {{ $n }}</option>@endfor
                            </select>
                        </label>
                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="create({{ $ic->course_id }}, {{ $ic->institute_id }})"><i class="ti ti-plus me-1"></i> Add</button>
                    </div>
                </header>

                @if($list->isEmpty())
                    <div class="ap-empty-row">
                        <i class="ti ti-puzzle"></i>
                        <span>No add-ons for this course yet — applicants see nothing extra.</span>
                        <button type="button" class="btn btn-sm btn-link p-0" wire:click="create({{ $ic->course_id }}, {{ $ic->institute_id }})">Add the first one</button>
                    </div>
                @else
                    <ul class="ap-list">
                        @foreach($list as $p)
                            @php
                                $left = $p->capacity !== null ? max(0, $p->capacity - $p->chosen_count) : null;
                                $fill = $p->capacity ? min(100, round($p->chosen_count / $p->capacity * 100)) : null;
                            @endphp
                            <li class="ap-item {{ $p->status ? '' : 'is-off' }}" wire:key="ap-{{ $p->id }}">
                                <span class="ap-item-ico"><i class="ti ti-puzzle"></i></span>
                                <div class="min-w-0">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="fw-semibold">{{ $p->name }}</span>
                                        @if($p->code)<span class="ap-tag">{{ $p->code }}</span>@endif
                                        @unless($p->status)<span class="ap-tag ap-tag-off">Hidden</span>@endunless
                                    </div>
                                    @if($p->description)<div class="ap-sub text-truncate" style="max-width: 520px;" title="{{ $p->description }}">{{ $p->description }}</div>@endif
                                </div>
                                <div class="ap-fee {{ $p->isFree() ? 'is-free' : '' }}">
                                    {{ $p->feeLabel() }}
                                    @unless($p->isFree())<small>{{ $p->fee_due_days ? "due {$p->fee_due_days} days after joining" : 'due on joining' }}</small>@endunless
                                </div>
                                <div class="ap-seats">
                                    @if($p->capacity)
                                        <div class="d-flex justify-content-between small"><span>{{ $p->chosen_count }} / {{ $p->capacity }}</span><span class="{{ $left ? 'text-muted' : 'text-danger fw-semibold' }}">{{ $left ? "{$left} left" : 'Full' }}</span></div>
                                        <div class="ap-bar"><span class="{{ $left ? '' : 'is-full' }}" style="width: {{ $fill }}%;"></span></div>
                                    @else
                                        <span class="small">{{ $p->chosen_count }} <span class="text-muted">chosen · no limit</span></span>
                                    @endif
                                </div>
                                <div class="form-check form-switch m-0" title="{{ $p->status ? 'Offered' : 'Hidden' }}">
                                    <input class="form-check-input ap-switch" type="checkbox" role="switch" @checked($p->status) wire:click="toggleStatus({{ $p->id }})" aria-label="{{ $p->status ? 'Hide' : 'Offer' }} {{ $p->name }}">
                                </div>
                                <div class="text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-warning" wire:click="edit({{ $p->id }})" title="Edit"><i class="ti ti-edit"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="confirmDelete({{ $p->id }})" title="Delete"><i class="ti ti-trash"></i></button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @empty
            <div class="ap-empty">
                <i class="ti ti-books"></i>
                <div class="fw-semibold">{{ $hasFilters ? 'Nothing matches these filters' : 'No courses assigned yet' }}</div>
                @unless($hasFilters)<div class="small">Assign courses under Institute Management → Institute Courses first.</div>@endunless
            </div>
        @endforelse
    </div>

    {{-- Add / edit --}}
    <div class="modal fade" id="addonModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content ap-modal">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="ap-modal-icon"><i class="ti ti-{{ $editingId ? 'edit' : 'puzzle' }}"></i></span>
                        <h5 class="modal-title mb-0">{{ $editingId ? 'Edit add-on program' : 'Add add-on program' }}</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        @if($isSuperAdmin)
                            <div class="col-md-6">
                                <label class="form-label fw-medium small" for="apInstitute">Institute <span class="text-danger">*</span></label>
                                <select id="apInstitute" class="form-select @error('instituteId') is-invalid @enderror" wire:model="instituteId" @disabled($editingChosen)>
                                    <option value="">Select institute</option>
                                    @foreach($institutes as $inst)<option value="{{ $inst->id }}">{{ $inst->name }} ({{ $inst->code }})</option>@endforeach
                                </select>
                                @error('instituteId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @endif
                        <div class="{{ $isSuperAdmin ? 'col-md-6' : 'col-12' }}">
                            <label class="form-label fw-medium small" for="apCourse">Course <span class="text-danger">*</span></label>
                            <select id="apCourse" class="form-select @error('courseId') is-invalid @enderror" wire:model="courseId" @disabled($editingChosen || !$instituteId)>
                                <option value="">{{ $instituteId ? ($modalCourses->isEmpty() ? 'No courses assigned to this institute' : 'Select course') : 'Select institute first' }}</option>
                                @foreach($modalCourses as $c)<option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>@endforeach
                            </select>
                            @error('courseId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @if($editingChosen)<small class="text-muted">{{ $editingChosen }} {{ \Illuminate\Support\Str::plural('student', $editingChosen) }} chose this add-on, so its course stays fixed.</small>@endif
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-medium small" for="apName">Name <span class="text-danger">*</span></label>
                            <input id="apName" type="text" class="form-control @error('name') is-invalid @enderror" wire:model.defer="name" placeholder="e.g. Aviation English (ICAO Level 4)">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium small" for="apCode">Code</label>
                            <input id="apCode" type="text" class="form-control text-uppercase @error('code') is-invalid @enderror" wire:model.defer="code" maxlength="30" placeholder="Optional">
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium small" for="apDesc">Short description</label>
                            <textarea id="apDesc" rows="2" maxlength="500" class="form-control @error('description') is-invalid @enderror" wire:model.defer="description" placeholder="What the applicant gets — shown on the admissions portal"></textarea>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium small" for="apFee">Fee (₹)</label>
                            <input id="apFee" type="number" min="0" step="0.01" class="form-control @error('fee_amount') is-invalid @enderror" wire:model.defer="fee_amount" placeholder="Free">
                            @error('fee_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <small class="text-muted">Leave empty for a free add-on.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium small" for="apDue">Fee due (days after joining)</label>
                            <input id="apDue" type="number" min="0" class="form-control @error('fee_due_days') is-invalid @enderror" wire:model.defer="fee_due_days">
                            @error('fee_due_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium small" for="apCapacity">Seats</label>
                            <input id="apCapacity" type="number" min="1" class="form-control @error('capacity') is-invalid @enderror" wire:model.defer="capacity" placeholder="No limit">
                            @error('capacity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium small" for="apSort">Display order</label>
                            <input id="apSort" type="number" min="0" class="form-control @error('sort_order') is-invalid @enderror" wire:model.defer="sort_order">
                        </div>
                        <div class="col-md-8 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="apStatus" wire:model="status" value="1">
                                <label class="form-check-label" for="apStatus">{{ $status ? 'Offered to applicants' : 'Hidden from applicants' }}</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled" wire:target="save"><i class="ti ti-check me-1"></i> Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Delete --}}
    <div class="modal fade" id="addonDeleteModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <i class="ti ti-alert-triangle text-danger fs-1 mb-3"></i>
                    <h5>Delete add-on program</h5>
                    <p class="text-muted mb-4">Delete this add-on? One that students have chosen cannot be deleted — hide it instead.</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-danger px-4" wire:click="delete">Yes, delete</button>
                    </div>
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
        .ap-ui .ap-hero-cta { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 999px; border: 0; background: var(--ap-accent); color: #fff; font-size: 14px; font-weight: 500; box-shadow: 0 6px 16px rgba(242, 101, 34, .3); transition: transform .15s; }
        .ap-ui .ap-hero-cta:hover { transform: translateY(-1px); }
        .ap-ui .ap-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
        .ap-ui .ap-card { --tone: #4338CA; --tone-soft: #EEF2FF; display: flex; flex-direction: column; gap: 2px; padding: 14px 16px 16px; background: #fff; border: 1px solid var(--ap-border); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
        .ap-ui .ap-card-top { display: flex; justify-content: space-between; align-items: center; }
        .ap-ui .ap-card-label { font-size: 13px; font-weight: 600; color: #374151; }
        .ap-ui .ap-card-icon { width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 19px; background: var(--tone-soft); color: var(--tone); }
        .ap-ui .ap-card-count { font-size: 26px; font-weight: 700; color: var(--ap-ink); line-height: 1.15; }
        .ap-ui .ap-card-hint { font-size: 12px; color: var(--ap-muted); }
        .ap-ui .ap-card-ok { --tone: #16A34A; --tone-soft: #DCFCE7; } .ap-ui .ap-card-warn { --tone: #C2410C; --tone-soft: #FFEDD5; } .ap-ui .ap-card-info { --tone: #0284C7; --tone-soft: #E0F2FE; }
        .ap-ui .ap-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 12px 16px; border: 1px solid var(--ap-border); border-radius: 14px; background: #fff; }
        .ap-ui .ap-toolbar .form-select { width: auto; min-width: 150px; max-width: 260px; border-radius: 8px; }
        .ap-ui .ap-search { position: relative; width: 260px; max-width: 100%; }
        .ap-ui .ap-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
        .ap-ui .ap-search input { padding-left: 32px; border-radius: 8px; }
        .ap-ui .ap-courses { display: flex; flex-direction: column; gap: 14px; }
        .ap-ui .ap-course { background: #fff; border: 1px solid var(--ap-border); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); overflow: hidden; transition: box-shadow .15s; }
        .ap-ui .ap-course:hover { box-shadow: 0 8px 22px rgba(16, 24, 40, .07); }
        .ap-ui .ap-course.is-off { opacity: .75; }
        .ap-ui .ap-course-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 14px 16px; background: #FCFCFD; border-bottom: 1px solid var(--ap-soft); }
        .ap-ui .ap-course-code { display: inline-flex; align-items: center; height: 34px; padding: 0 12px; border-radius: 9px; background: linear-gradient(135deg, #EEF2FF, #E0E7FF); color: #4338CA; font-weight: 700; font-size: 13px; flex-shrink: 0; }
        .ap-ui .ap-sub { font-size: 12px; color: var(--ap-muted); }
        .ap-ui .ap-limit { display: inline-flex; align-items: center; gap: 6px; margin: 0; padding: 3px 4px 3px 10px; border: 1px solid var(--ap-border); border-radius: 10px; background: #fff; font-size: 12.5px; color: #374151; white-space: nowrap; }
        .ap-ui .ap-limit .form-select { border: 0; box-shadow: none; padding-top: 2px; padding-bottom: 2px; min-width: 110px; font-weight: 600; }
        .ap-ui .ap-list { list-style: none; margin: 0; padding: 0; }
        .ap-ui .ap-item { display: grid; grid-template-columns: 40px minmax(0, 1fr) 150px 170px auto auto; gap: 14px; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--ap-soft); transition: background-color .15s; }
        .ap-ui .ap-item:last-child { border-bottom: 0; }
        .ap-ui .ap-item:hover { background: #FAFAFB; }
        .ap-ui .ap-item.is-off { color: #9CA3AF; }
        .ap-ui .ap-item-ico { width: 40px; height: 40px; border-radius: 11px; background: #FEF0E7; color: var(--ap-accent); display: inline-flex; align-items: center; justify-content: center; font-size: 19px; }
        .ap-ui .ap-item.is-off .ap-item-ico { background: #F3F4F6; color: #9CA3AF; }
        .ap-ui .ap-tag { padding: 1px 8px; border-radius: 6px; background: #F3F4F6; color: #374151; font-size: 11px; font-weight: 600; font-family: 'IBM Plex Mono', ui-monospace, monospace; }
        .ap-ui .ap-tag-off { background: #FEE2E2; color: #B91C1C; font-family: inherit; }
        .ap-ui .ap-fee { display: flex; flex-direction: column; font-weight: 700; color: var(--ap-ink); }
        .ap-ui .ap-fee small { font-weight: 400; font-size: 11.5px; color: var(--ap-muted); }
        .ap-ui .ap-fee.is-free { color: #16A34A; }
        .ap-ui .ap-bar { height: 6px; border-radius: 999px; background: #F3F4F6; overflow: hidden; margin-top: 4px; }
        .ap-ui .ap-bar span { display: block; height: 100%; background: #22C55E; border-radius: 999px; }
        .ap-ui .ap-bar span.is-full { background: #EF4444; }
        .ap-ui .ap-switch { width: 2.4em; height: 1.3em; cursor: pointer; }
        .ap-ui .ap-switch:checked { background-color: #16A34A; border-color: #16A34A; }
        .ap-ui .ap-empty-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 16px; color: var(--ap-muted); font-size: 13.5px; }
        .ap-ui .ap-empty-row > i { font-size: 20px; color: #D1D5DB; }
        .ap-ui .ap-empty { text-align: center; color: var(--ap-muted); padding: 48px 12px; background: #fff; border: 1px dashed var(--ap-border); border-radius: 14px; }
        .ap-ui .ap-empty > i { font-size: 36px; color: #D1D5DB; display: block; margin-bottom: 6px; }
        .ap-ui .ap-modal { border: 0; border-radius: 14px; }
        .ap-ui .ap-modal-icon { width: 34px; height: 34px; border-radius: 9px; background: #FEF0E7; color: var(--ap-accent); display: inline-flex; align-items: center; justify-content: center; font-size: 17px; }
        @media (max-width: 992px) {
            .ap-ui .ap-item { grid-template-columns: 40px minmax(0, 1fr) auto; }
            .ap-ui .ap-item .ap-fee, .ap-ui .ap-item .ap-seats { grid-column: 2 / -1; }
        }
    </style>
</div>

<script>
document.addEventListener('livewire:load', function () {
    [['addon', 'addonModal'], ['addon-delete', 'addonDeleteModal']].forEach(function ([name, id]) {
        window.addEventListener('open-' + name + '-modal', () => bootstrap.Modal.getOrCreateInstance(document.getElementById(id)).show());
        window.addEventListener('close-' + name + '-modal', () => {
            const instance = bootstrap.Modal.getInstance(document.getElementById(id));
            if (instance) { instance.hide(); }
        });
    });
});
</script>
