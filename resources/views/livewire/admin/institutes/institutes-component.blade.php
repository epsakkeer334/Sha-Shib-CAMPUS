<div class="content in-ui">
    {{-- Header --}}
    <div class="in-hero mb-3">
        <div class="min-w-0">
            <h2 class="mb-1 fw-bold">Institutes Management</h2>
            <nav>
                <ol class="breadcrumb mb-1 sl-crumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Institute Management</li>
                    <li class="breadcrumb-item active">Institutes</li>
                </ol>
            </nav>
            <div class="text-muted small">Every institute of the group — its code, contacts, courses, staff and students.</div>
        </div>
        <button type="button" wire:click="openModal" class="in-hero-cta"><i class="ti ti-circle-plus"></i> Add Institute</button>
    </div>

    {{-- Summary (click to filter) --}}
    <div class="in-cards mb-3">
        @foreach([
            ['All', 'Institutes', $stats['total'], 'ti ti-building-community', 'all', 'In the group'],
            ['Active', 'Active', $stats['active'], 'ti ti-circle-check', 'ok', 'Open for admission'],
            ['Inactive', 'Inactive', $stats['inactive'], 'ti ti-circle-off', 'muted', 'Logins & admission closed'],
        ] as [$key, $label, $count, $icon, $tone, $hint])
            <button type="button" class="in-card in-card-{{ $tone }} {{ $filter === $key ? 'is-active' : '' }}" wire:click="$set('filter', '{{ $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}">
                <span class="in-card-top"><span class="in-card-label">{{ $label }}</span><span class="in-card-icon"><i class="{{ $icon }}"></i></span></span>
                <span class="in-card-count">{{ number_format($count) }}</span>
                <span class="in-card-hint">{{ $hint }}</span>
            </button>
        @endforeach
        <div class="in-card in-card-info is-static">
            <span class="in-card-top"><span class="in-card-label">Students</span><span class="in-card-icon"><i class="ti ti-school"></i></span></span>
            <span class="in-card-count">{{ number_format($stats['students']) }}</span>
            <span class="in-card-hint">Across all institutes</span>
        </div>
    </div>

    <div class="in-panel">
        {{-- Toolbar --}}
        <div class="in-toolbar">
            <div class="in-search">
                <i class="ti ti-search"></i>
                <input type="search" class="form-control form-control-sm" placeholder="Name, code, city, email, phone…" wire:model.debounce.400ms="search" aria-label="Search institutes">
            </div>
            <div class="in-seg" role="group" aria-label="Status">
                @foreach(['All', 'Active', 'Inactive'] as $f)
                    <button type="button" class="{{ $filter === $f ? 'is-active' : '' }}" wire:click="$set('filter', '{{ $f }}')">{{ $f }}</button>
                @endforeach
            </div>
            <select class="form-select form-select-sm in-per-page" wire:model="perPage" aria-label="Rows per page">
                @foreach([10, 25, 50] as $n)<option value="{{ $n }}">{{ $n }} / page</option>@endforeach
            </select>
            <span class="ms-auto small text-muted">{{ number_format($institutes->total()) }} {{ \Illuminate\Support\Str::plural('institute', $institutes->total()) }}</span>
        </div>

        @php
            $sortIcon = fn ($field) => $sortField === $field ? ($sortDirection === 'asc' ? 'ti ti-arrow-up' : 'ti ti-arrow-down') : 'ti ti-arrows-sort';
        @endphp
        <div class="table-responsive position-relative">
            <div class="in-loading" wire:loading.delay.flex wire:target="search, filter, perPage, sortBy, gotoPage, nextPage, previousPage, toggleStatus, delete">
                <span class="spinner-border spinner-border-sm text-secondary"></span>
            </div>
            <table class="table align-middle mb-0 in-table">
                <thead>
                    <tr>
                        <th><button type="button" class="in-sort" wire:click="sortBy('name')">Institute <i class="{{ $sortIcon('name') }}"></i></button></th>
                        <th><button type="button" class="in-sort" wire:click="sortBy('code')">Code <i class="{{ $sortIcon('code') }}"></i></button></th>
                        <th class="text-center"><button type="button" class="in-sort" wire:click="sortBy('established_year')">Established <i class="{{ $sortIcon('established_year') }}"></i></button></th>
                        <th>Contact</th>
                        <th class="text-center">Courses</th>
                        <th class="text-center">Staff</th>
                        <th class="text-center">Students</th>
                        <th><button type="button" class="in-sort" wire:click="sortBy('status')">Status <i class="{{ $sortIcon('status') }}"></i></button></th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($institutes as $inst)
                        @php [$codePrefix, $codeYear, $codeNumber] = array_pad(explode('/', (string) $inst->code, 3), 3, null); @endphp
                        <tr wire:key="inst-{{ $inst->id }}" class="{{ $inst->status ? '' : 'in-row-off' }}">
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    @if($inst->logo)
                                        <img src="{{ $inst->logo_url }}" alt="" class="in-logo" loading="lazy">
                                    @else
                                        <span class="in-logo in-logo-text">{{ mb_strtoupper(mb_substr($inst->name, 0, 1)) }}</span>
                                    @endif
                                    <div class="min-w-0">
                                        <div class="in-name text-truncate" title="{{ $inst->name }}">{{ $inst->name }}</div>
                                        <div class="in-sub">
                                            <i class="ti ti-map-pin"></i>
                                            {{ collect([$inst->city, optional($inst->state)->name, optional($inst->country)->name])->filter()->implode(', ') ?: '—' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($inst->code)
                                    <span class="in-code" title="Prefix / established year / number">
                                        <span class="in-code-prefix">{{ $codePrefix }}</span><span class="in-code-sep">/</span><span class="in-code-year">{{ $codeYear }}</span><span class="in-code-sep">/</span><span class="in-code-num">{{ $codeNumber }}</span>
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($inst->established_year)
                                    <span class="in-year"><i class="ti ti-calendar-event"></i> {{ $inst->established_year }}</span>
                                    <div class="in-sub justify-content-center mt-1">{{ now()->year - $inst->established_year }} {{ \Illuminate\Support\Str::plural('year', now()->year - $inst->established_year) }}</div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="in-contact">
                                    @if($inst->contact_person)<div class="fw-medium text-truncate"><i class="ti ti-user"></i> {{ $inst->contact_person }}</div>@endif
                                    <div class="text-truncate"><i class="ti ti-mail"></i> <a href="mailto:{{ $inst->email }}">{{ $inst->email }}</a></div>
                                    <div><i class="ti ti-phone"></i> {{ $inst->phone }}</div>
                                </div>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.institute-courses.institute', $inst->id) }}" class="in-count in-count-indigo" title="Manage courses"><i class="ti ti-books"></i> {{ $inst->courses_count }}</a>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.institute-users.institute', $inst->id) }}" class="in-count in-count-sky" title="Manage users"><i class="ti ti-users"></i> {{ $inst->staff_count }}</a>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.students', ['institute' => $inst->id]) }}" class="in-count in-count-green" title="View students"><i class="ti ti-school"></i> {{ $inst->students_count }}</a>
                            </td>
                            <td>
                                <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
                                    <input class="form-check-input in-switch" type="checkbox" role="switch" id="instStatus{{ $inst->id }}" @checked($inst->status)
                                           wire:click="toggleStatus({{ $inst->id }})" aria-label="{{ $inst->status ? 'Deactivate' : 'Activate' }} {{ $inst->name }}">
                                    <label class="small {{ $inst->status ? 'text-success fw-semibold' : 'text-muted' }}" for="instStatus{{ $inst->id }}">{{ $inst->status ? 'Active' : 'Inactive' }}</label>
                                </div>
                            </td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-warning" wire:click="edit({{ $inst->id }})" data-bs-toggle="tooltip" title="Edit"><i class="ti ti-edit"></i></button>
                                <a href="{{ route('admin.institute-payment-settings', ['institute' => $inst->id]) }}" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="Payment settings"><i class="ti ti-qrcode"></i></a>
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="confirmDelete({{ $inst->id }})" data-bs-toggle="tooltip" title="Delete"><i class="ti ti-trash"></i></button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="in-empty">
                                <i class="ti ti-building-off"></i>
                                <div class="fw-semibold">{{ $search || $filter !== 'All' ? 'No institutes match these filters' : 'No institutes yet' }}</div>
                                @unless($search || $filter !== 'All')
                                    <button type="button" class="btn btn-sm btn-primary mt-2" wire:click="openModal"><i class="ti ti-circle-plus me-1"></i> Add the first institute</button>
                                @endunless
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($institutes->hasPages())<div class="in-foot">{{ $institutes->links() }}</div>@endif
    </div>

    <style>
        .in-ui { --in-border: #E5E7EB; --in-soft: #F1F2F4; --in-ink: #111827; --in-muted: #6B7280; --in-accent: #F26522; }
        .in-ui .min-w-0 { min-width: 0; }
        .in-ui .in-hero { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; padding: 18px 22px; border-radius: 14px; border: 1px solid var(--in-border);
            background: radial-gradient(circle at 100% 0, rgba(242, 101, 34, .12), transparent 45%), linear-gradient(135deg, #FFFFFF 0%, #FFF8F3 100%); }
        .in-ui .in-hero h2 { font-size: 22px; color: var(--in-ink); }
        .in-ui .in-hero-cta { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 999px; border: 0; background: var(--in-accent); color: #fff; font-size: 14px; font-weight: 500; box-shadow: 0 6px 16px rgba(242, 101, 34, .3); transition: transform .15s; }
        .in-ui .in-hero-cta:hover { transform: translateY(-1px); }
        /* cards */
        .in-ui .in-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
        .in-ui .in-card { --tone: #4338CA; --tone-soft: #EEF2FF; position: relative; overflow: hidden; display: flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: 14px 16px 16px; background: #fff; border: 1px solid var(--in-border); border-radius: 14px; text-align: left; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); transition: box-shadow .15s, transform .15s, border-color .15s; }
        .in-ui .in-card::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: var(--tone); opacity: 0; }
        .in-ui button.in-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16, 24, 40, .08); }
        .in-ui .in-card.is-active { border-color: var(--tone); background: linear-gradient(180deg, var(--tone-soft) 0%, #fff 70%); }
        .in-ui .in-card.is-active::after { opacity: 1; }
        .in-ui .in-card-top { display: flex; justify-content: space-between; align-items: center; width: 100%; }
        .in-ui .in-card-label { font-size: 13px; font-weight: 600; color: #374151; }
        .in-ui .in-card-icon { width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 19px; background: var(--tone-soft); color: var(--tone); }
        .in-ui .in-card-count { font-size: 26px; font-weight: 700; line-height: 1.15; color: var(--in-ink); }
        .in-ui .in-card-hint { font-size: 12px; color: var(--in-muted); }
        .in-ui .in-card-all { --tone: #4338CA; --tone-soft: #EEF2FF; } .in-ui .in-card-ok { --tone: #16A34A; --tone-soft: #DCFCE7; }
        .in-ui .in-card-muted { --tone: #6B7280; --tone-soft: #F3F4F6; } .in-ui .in-card-info { --tone: #0284C7; --tone-soft: #E0F2FE; }
        /* panel & toolbar */
        .in-ui .in-panel { background: #fff; border: 1px solid var(--in-border); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); overflow: hidden; }
        .in-ui .in-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--in-soft); background: #FCFCFD; }
        .in-ui .in-search { position: relative; width: 300px; max-width: 100%; }
        .in-ui .in-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
        .in-ui .in-search input { padding-left: 32px; border-radius: 8px; }
        .in-ui .in-seg { display: inline-flex; gap: 2px; padding: 3px; background: #F3F4F6; border-radius: 9px; }
        .in-ui .in-seg button { border: 0; background: transparent; padding: 4px 12px; border-radius: 7px; font-size: 13px; color: #4B5563; font-weight: 500; }
        .in-ui .in-seg button.is-active { background: #fff; color: var(--in-ink); box-shadow: 0 1px 2px rgba(16, 24, 40, .1); }
        .in-ui .in-per-page { width: auto; border-radius: 8px; }
        .in-ui .in-foot { padding: 12px 16px; border-top: 1px solid var(--in-soft); }
        .in-ui .in-foot .pagination { margin: 0; }
        /* table */
        .in-ui .in-table thead th { background: #F9FAFB; font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--in-muted); border-bottom: 1px solid var(--in-border); padding: 10px 14px; white-space: nowrap; }
        .in-ui .in-sort { border: 0; background: transparent; padding: 0; font: inherit; color: inherit; text-transform: inherit; letter-spacing: inherit; display: inline-flex; align-items: center; gap: 4px; }
        .in-ui .in-sort i { font-size: 13px; opacity: .7; }
        .in-ui .in-table td { padding: 14px; border-color: var(--in-soft); font-size: 14px; vertical-align: middle; }
        .in-ui .in-table tbody tr:hover td { background: #FAFAFB; }
        .in-ui .in-table tbody tr:last-child td { border-bottom: 0; }
        .in-ui .in-row-off td { background: #FAFAFA; }
        .in-ui .in-row-off .in-name, .in-ui .in-row-off .in-logo { opacity: .6; }
        .in-ui .in-logo { width: 46px; height: 46px; border-radius: 12px; object-fit: contain; background: #fff; border: 1px solid var(--in-border); padding: 4px; flex-shrink: 0; }
        .in-ui .in-logo-text { display: inline-flex; align-items: center; justify-content: center; background: #FEF0E7; color: var(--in-accent); font-weight: 700; font-size: 18px; border-color: #FDDCC6; }
        .in-ui .in-name { font-weight: 600; color: var(--in-ink); max-width: 280px; }
        .in-ui .in-sub { font-size: 12px; color: var(--in-muted); display: flex; align-items: center; gap: 4px; }
        /* highlighted institute code: PREFIX / YEAR / NUMBER */
        .in-ui .in-code { display: inline-flex; align-items: center; padding: 5px 12px; border-radius: 9px; font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 13.5px; font-weight: 600; letter-spacing: .02em; white-space: nowrap;
            background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%); border: 1px solid #C7D2FE; color: #3730A3; box-shadow: 0 1px 2px rgba(67, 56, 202, .12); }
        .in-ui .in-code-prefix { color: #F26522; font-weight: 700; }
        .in-ui .in-code-sep { color: #A5B4FC; margin: 0 3px; }
        .in-ui .in-code-year { color: #4338CA; }
        .in-ui .in-code-num { color: #111827; font-weight: 700; }
        .in-ui .in-year { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 8px; background: #FFF7ED; border: 1px solid #FED7AA; color: #9A3412; font-weight: 600; font-size: 13px; font-variant-numeric: tabular-nums; }
        .in-ui .in-contact { font-size: 12.5px; color: #4B5563; max-width: 240px; display: flex; flex-direction: column; gap: 2px; }
        .in-ui .in-contact i { color: #9CA3AF; font-size: 13px; }
        .in-ui .in-contact a { color: inherit; }
        .in-ui .in-count { display: inline-flex; align-items: center; gap: 5px; min-width: 52px; justify-content: center; padding: 4px 10px; border-radius: 999px; font-weight: 600; font-size: 13px; text-decoration: none; transition: transform .12s; }
        .in-ui .in-count:hover { transform: translateY(-1px); }
        .in-ui .in-count-indigo { background: #EEF2FF; color: #4338CA; } .in-ui .in-count-sky { background: #E0F2FE; color: #0369A1; } .in-ui .in-count-green { background: #DCFCE7; color: #15803D; }
        .in-ui .in-switch { width: 2.4em; height: 1.3em; cursor: pointer; }
        .in-ui .in-switch:checked { background-color: #16A34A; border-color: #16A34A; }
        .in-ui .in-empty { text-align: center; color: var(--in-muted); padding: 48px 12px !important; }
        .in-ui .in-empty > i { font-size: 36px; color: #D1D5DB; display: block; margin-bottom: 6px; }
        .in-ui .in-loading { display: none; position: absolute; inset: 0; z-index: 2; background: rgba(255, 255, 255, .6); align-items: center; justify-content: center; }
        @media (max-width: 575.98px) { .in-ui .in-search { width: 100%; } }
    </style>

    <!-- Add/Edit Modal -->
    <div class="modal fade" id="instituteModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <!-- Header -->
                <div class="modal-header bg-primary bg-opacity-10 border-bottom">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md bg-soft-primary bg-opacity-10 rounded-circle me-3 d-flex align-items-center justify-content-center">
                            <i class="ti ti-building-bank text-primary fs-14"></i>
                        </div>
                        <div>
                            <h4 class="modal-title fw-semibold mb-0">{{ $isEdit ? 'Edit Institute' : 'Add New Institute' }}</h4>
                            <small class="text-muted">{{ $isEdit ? 'Update the institute details below' : 'Fill in the details to create a new institute' }}</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" wire:click="closeModal"></button>
                </div>

                <!-- Body -->
                <div class="modal-body p-4">
                    <!-- Section: Basic Information -->
                    <div class="card border-0 bg-light bg-opacity-50 mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <i class="ti ti-info-circle text-primary me-2 fs-14"></i>
                                <h6 class="fw-semibold mb-0">Basic Information</h6>
                            </div>
                            <div class="row g-3">
                                <!-- Institute Name -->
                                <div class="col-md-5">
                                    <label class="form-label fw-medium small">Institute Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder="Enter institute name">
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <!-- Code Prefix -->
                                <div class="col-md-2">
                                    <label class="form-label fw-medium small">Code Prefix @unless($isEdit)<span class="text-danger">*</span>@endunless</label>
                                    <input type="text" class="form-control text-uppercase @error('code_prefix') is-invalid @elseif($this->prefixAvailable) is-valid @enderror" wire:model.debounce.300ms="code_prefix"
                                           maxlength="6" placeholder="e.g. SHA" autocomplete="off" @if($isEdit) readonly @endif style="font-family: 'IBM Plex Mono', ui-monospace, monospace; letter-spacing: .05em;">
                                    @error('code_prefix') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    @if(!$errors->has('code_prefix') && $this->prefixAvailable)
                                        <small class="text-success" style="font-size: 0.7rem;"><i class="ti ti-circle-check"></i> Available</small>
                                    @else
                                        <small class="text-muted" style="font-size: 0.7rem;">2–6 letters, unique</small>
                                    @endif
                                </div>

                                <!-- Established Year -->
                                <div class="col-md-2">
                                    <label class="form-label fw-medium small">Established Year <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control @error('established_year') is-invalid @enderror" wire:model="established_year"
                                        min="1800" max="{{ date('Y') }}" placeholder="e.g., {{ date('Y') }}">
                                    @error('established_year') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <!-- Institute Code -->
                                <div class="col-md-3">
                                    <label class="form-label fw-medium small">Institute Code</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="ti ti-hash fs-14"></i></span>
                                        <input type="text" class="form-control bg-light" value="{{ $code }}" readonly
                                               placeholder="Auto-generated">
                                    </div>
                                    <small class="text-muted" style="font-size: 0.7rem;">
                                        {{ $isEdit ? 'Code cannot be changed.' : 'Prefix / established year / number. Final number is assigned on save.' }}
                                    </small>
                                </div>

                                <!-- Description -->
                                <div class="col-12">
                                    <label class="form-label fw-medium small">Description</label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" wire:model="description" rows="2"
                                            placeholder="Brief description about the institute"></textarea>
                                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Address Information -->
                    <div class="card border-0 bg-light bg-opacity-50 mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <i class="ti ti-map-pin text-primary me-2 fs-14"></i>
                                <h6 class="fw-semibold mb-0">Address Information</h6>
                            </div>
                            <div class="row g-3">
                                <!-- Address -->
                                <div class="col-12">
                                    <label class="form-label fw-medium small">Street Address</label>
                                    <textarea class="form-control @error('address') is-invalid @enderror" wire:model="address" rows="2"
                                            placeholder="Enter street address"></textarea>
                                    @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <!-- Country -->
                                <div class="col-md-4">
                                    <label class="form-label fw-medium small">Country <span class="text-danger">*</span></label>
                                    <select class="form-select @error('country_id') is-invalid @enderror" wire:model="country_id">
                                        <option value="">Select Country</option>
                                        @foreach($countries as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    @error('country_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <!-- State -->
                                <div class="col-md-4">
                                    <label class="form-label fw-medium small">State <span class="text-danger">*</span></label>
                                    <select class="form-select @error('state_id') is-invalid @enderror" wire:model="state_id" {{ empty($states) ? 'disabled' : '' }}>
                                        <option value="">Select State</option>
                                        @foreach($states as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    @error('state_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    @if(empty($states) && $country_id)
                                        <small class="text-muted" style="font-size: 0.7rem;">No states available</small>
                                    @endif
                                </div>

                                <!-- City -->
                                <div class="col-md-2">
                                    <label class="form-label fw-medium small">City</label>
                                    <input type="text" class="form-control @error('city') is-invalid @enderror" wire:model="city" placeholder="City">
                                    @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <!-- Postal Code -->
                                <div class="col-md-2">
                                    <label class="form-label fw-medium small">Postal Code</label>
                                    <input type="text" class="form-control @error('postal_code') is-invalid @enderror" wire:model="postal_code" placeholder="Postal">
                                    @error('postal_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Contact Information -->
                    <div class="card border-0 bg-light bg-opacity-50 mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <i class="ti ti-phone text-primary me-2 fs-14"></i>
                                <h6 class="fw-semibold mb-0">Contact Information</h6>
                            </div>
                            <div class="row g-3">
                                <!-- Contact Person -->
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Contact Person</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="ti ti-user text-muted fs-14"></i></span>
                                        <input type="text" class="form-control @error('contact_person') is-invalid @enderror" wire:model="contact_person" placeholder="Full name">
                                    </div>
                                    @error('contact_person') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <!-- Email -->
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Email <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="ti ti-mail text-muted fs-14"></i></span>
                                        <input type="email" class="form-control @error('email') is-invalid @enderror" wire:model="email" placeholder="institute@example.com">
                                    </div>
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <!-- Phone -->
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Phone <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="ti ti-phone text-muted fs-14"></i></span>
                                        <input type="tel" class="form-control @error('phone') is-invalid @enderror" wire:model="phone" placeholder="Phone number">
                                    </div>
                                    @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <!-- Website -->
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Website</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="ti ti-world text-muted fs-14"></i></span>
                                        <input type="url" class="form-control @error('website') is-invalid @enderror" wire:model="website" placeholder="https://example.com">
                                    </div>
                                    @error('website') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Media -->
                    <div class="card border-0 bg-light bg-opacity-50 mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <i class="ti ti-photo text-primary me-2 fs-14"></i>
                                <h6 class="fw-semibold mb-0">Media</h6>
                            </div>
                            <div class="row g-3">
                                <!-- Logo Upload -->
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Institute Logo <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control @error('logo') is-invalid @enderror" wire:model="logo" accept="image/*">
                                    @error('logo') <div class="invalid-feedback">{{ $message }}</div> @enderror

                                    <div class="mt-2">
                                        @if ($logo && !is_string($logo))
                                            <div class="d-flex align-items-center gap-2 p-2 bg-white rounded border">
                                                <img src="{{ $logo->temporaryUrl() }}" class="rounded" style="height: 60px; width: 60px; object-fit: cover;">
                                                <div>
                                                    <span class="badge bg-success bg-opacity-10 text-success small">New Logo</span>
                                                    <p class="mb-0 text-muted small">Preview ready</p>
                                                </div>
                                            </div>
                                        @elseif($isEdit && $recordId)
                                            @php $institute = \App\Models\Admin\Institute::find($recordId) @endphp
                                            @if($institute && $institute->logo)
                                                <div class="d-flex align-items-center gap-2 p-2 bg-white rounded border">
                                                    <img src="{{ $institute->logo_url }}" class="rounded" style="height: 60px; width: 60px; object-fit: cover;">
                                                    <div>
                                                        <span class="badge bg-info bg-opacity-10 text-info small">Current Logo</span>
                                                        <p class="mb-0 text-muted small">Upload new to replace</p>
                                                    </div>
                                                </div>
                                            @endif
                                        @else
                                            <div class="p-2 bg-white rounded border text-center">
                                                <i class="ti ti-cloud-upload text-muted fs-3"></i>
                                                <p class="mb-0 text-muted small">Recommended: 200x200px</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Banner Upload -->
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Institute Banner</label>
                                    <input type="file" class="form-control @error('banner') is-invalid @enderror" wire:model="banner" accept="image/*">
                                    @error('banner') <div class="invalid-feedback">{{ $message }}</div> @enderror

                                    <div class="mt-2">
                                        @if ($banner && !is_string($banner))
                                            <div class="p-2 bg-white rounded border">
                                                <img src="{{ $banner->temporaryUrl() }}" class="rounded w-100" style="height: 60px; object-fit: cover;">
                                                <span class="badge bg-success bg-opacity-10 text-success small mt-1">New Banner</span>
                                            </div>
                                        @elseif($isEdit && $recordId)
                                            @php $institute = \App\Models\Admin\Institute::find($recordId) @endphp
                                            @if($institute && $institute->banner)
                                                <div class="p-2 bg-white rounded border">
                                                    <img src="{{ $institute->banner_url }}" class="rounded w-100" style="height: 60px; object-fit: cover;">
                                                    <span class="badge bg-info bg-opacity-10 text-info small mt-1">Current Banner</span>
                                                </div>
                                            @endif
                                        @else
                                            <div class="p-2 bg-white rounded border text-center">
                                                <i class="ti ti-photo text-muted fs-3"></i>
                                                <p class="mb-0 text-muted small">Recommended: 1200x400px</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: About Institute -->
                    <div class="card border-0 bg-light bg-opacity-50 mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <i class="ti ti-file-description text-primary me-2 fs-14"></i>
                                <h6 class="fw-semibold mb-0">About Institute</h6>
                            </div>
                            <div wire:ignore>
                                <textarea id="summernote" class="form-control @error('about') is-invalid @enderror"
                                        rows="10" placeholder="Enter detailed information about the institute"></textarea>
                            </div>
                            @error('about') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            <small class="text-muted mt-1">Use the toolbar to format text, add images, links, and more.</small>
                        </div>
                    </div>

                    <!-- Section: Status -->
                    <div class="card border-0 bg-light bg-opacity-50">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <i class="ti ti-toggle-right text-primary me-2 fs-14"></i>
                                    <div>
                                        <h6 class="fw-semibold mb-0">Status</h6>
                                        <small class="text-muted">Set institute active or inactive</small>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="statusSwitch"
                                            wire:model="status" value="1" @if($status == 1) checked @endif>
                                        <label class="form-check-label fw-medium" for="statusSwitch">
                                            {{ $status == 1 ? 'Active' : 'Inactive' }}
                                        </label>
                                    </div>
                                    <span class="badge {{ $status == 1 ? 'bg-success' : 'bg-danger' }} bg-opacity-10 {{ $status == 1 ? 'text-success' : 'text-danger' }}">
                                        {{ $status == 1 ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="modal-footer bg-light border-top">
                    <div class="d-flex justify-content-between w-100 align-items-center">
                        <small class="text-muted">
                            <i class="ti ti-info-circle me-1 fs-16"></i>
                            Fields marked with <span class="text-danger">*</span> are required
                        </small>
                        <div>
                            <button class="btn btn-light px-4 me-2" wire:click="closeModal">
                                <i class="ti ti-x me-1 fs-16"></i> Cancel
                            </button>
                            <button type="button" class="btn btn-primary px-4" onclick="saveInstitute()" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="save,update">
                                    <i class="ti ti-device-floppy me-1 fs-16"></i> {{ $isEdit ? 'Update Institute' : 'Save Institute' }}
                                </span>
                                <span wire:loading wire:target="save,update">
                                    <span class="spinner-border spinner-border-sm me-1"></span> Saving...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <i class="ti ti-alert-triangle text-danger fs-1 mb-3"></i>
                    <h5>Confirm Delete</h5>
                    <p class="text-muted mb-4">Are you sure you want to delete this institute? This action cannot be undone.</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-danger px-4" wire:click="delete">Yes, Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
document.addEventListener('livewire:load', function () {
    let summernoteInitialized = false;
    let currentContent = '';

    // Initialize Summernote
    function initSummernote(content = '') {
        if ($('#summernote').length && !summernoteInitialized) {
            $('#summernote').summernote({
                height: 300,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['fontname', ['fontname']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture', 'video']],
                    ['view', ['fullscreen', 'codeview', 'help']],
                ],
                callbacks: {
                    onChange: function(contents) {
                        currentContent = contents;
                        @this.set('about', contents);
                    },
                    onInit: function() {
                        if (content) {
                            $('#summernote').summernote('code', content);
                            currentContent = content;
                            @this.set('about', content);
                        }
                    }
                }
            });
            summernoteInitialized = true;

            // Set content after initialization
            if (content) {
                setTimeout(() => {
                    $('#summernote').summernote('code', content);
                }, 100);
            }
        } else if (summernoteInitialized && content !== undefined && content !== currentContent) {
            $('#summernote').summernote('code', content);
            currentContent = content;
        }
    }

    // Destroy Summernote
    function destroySummernote() {
        if ($('#summernote').length && summernoteInitialized) {
            $('#summernote').summernote('destroy');
            summernoteInitialized = false;
            currentContent = '';
        }
    }

    // Reinitialize Summernote after Livewire updates
    function reinitSummernote() {
        if (summernoteInitialized) {
            destroySummernote();
        }
        setTimeout(() => {
            initSummernote(@this.about);
        }, 50);
    }

    // Save institute function
    window.saveInstitute = function() {
        if (@this.isEdit) {
            @this.call('update');
        } else {
            @this.call('save');
        }
    }

    // Modal management
    const modalConfigs = [
        { name: 'institute', id: 'instituteModal' },
        { name: 'delete', id: 'deleteModal' }
    ];

    modalConfigs.forEach(config => {
        window.addEventListener(`open-${config.name}-modal`, () => {
            const modal = new bootstrap.Modal(document.getElementById(config.id));
            modal.show();

            // Initialize Summernote when institute modal opens
            if (config.name === 'institute') {
                setTimeout(() => {
                    initSummernote(@this.about);
                }, 300);
            }
        });

        window.addEventListener(`close-${config.name}-modal`, () => {
            const modalElement = document.getElementById(config.id);
            const modalInstance = bootstrap.Modal.getInstance(modalElement);

            if (modalInstance) {
                modalInstance.hide();
            }

            document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
            document.body.classList.remove('modal-open');

            // Destroy summernote when modal closes
            if (config.name === 'institute') {
                destroySummernote();
            }
        });
    });

    // Reinitialize Summernote when Livewire updates the about content
    window.addEventListener('init-summernote', (event) => {
        setTimeout(() => {
            initSummernote(event.detail.content);
        }, 100);
    });

    // Watch for Livewire updates that might affect the DOM
    Livewire.hook('message.processed', () => {
        if ($('#instituteModal').hasClass('show') && !summernoteInitialized) {
            setTimeout(() => {
                initSummernote(@this.about);
            }, 100);
        }
    });

    // Initialize tooltips
    const tooltipElements = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipElements.forEach(element => {
        new bootstrap.Tooltip(element);
    });
});

// Additional listener for when modal is fully shown
document.addEventListener('shown.bs.modal', function (event) {
    if (event.target.id === 'instituteModal') {
        setTimeout(() => {
            if (typeof initSummernote === 'function' && !window.summernoteInitialized) {
                initSummernote(window.Livewire.find('institutes-component').get('about'));
            }
        }, 100);
    }
});
</script>
