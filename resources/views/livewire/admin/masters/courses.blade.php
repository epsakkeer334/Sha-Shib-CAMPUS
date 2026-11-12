<div class="content cr-ui">
    {{-- Header --}}
    <div class="cr-hero mb-3">
        <div class="min-w-0">
            <h2 class="mb-1 fw-bold">Courses</h2>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Master Data</li>
                    <li class="breadcrumb-item">Academic</li>
                    <li class="breadcrumb-item active">Courses</li>
                </ol>
            </nav>
            <div class="text-muted small">Courses of the whole group. Assign them to institutes in Institute Management → Institute Courses.</div>
        </div>
        <button type="button" class="cr-hero-cta" wire:click="openModal"><i class="ti ti-circle-plus"></i> Add Course</button>
    </div>

    {{-- Summary (click to filter) --}}
    <div class="cr-cards mb-3">
        @foreach([
            ['', 'Courses', $stats['total'], 'ti ti-books', 'all', 'All courses'],
            ['active', 'Active', $stats['active'], 'ti ti-circle-check', 'ok', 'Can be offered'],
            ['inactive', 'Inactive', $stats['inactive'], 'ti ti-circle-off', 'muted', 'Hidden from new choices'],
            ['unassigned', 'Not offered', $stats['unassigned'], 'ti ti-building-off', 'warn', 'No institute offers it'],
        ] as [$cardKey, $cardLabel, $cardCount, $cardIcon, $cardTone, $cardHint])
            <button type="button" class="cr-card cr-card-{{ $cardTone }} {{ $filterStatus === $cardKey ? 'is-active' : '' }}" wire:click="$set('filterStatus', '{{ $cardKey }}')">
                <span class="cr-card-top"><span class="cr-card-label">{{ $cardLabel }}</span><span class="cr-card-icon"><i class="{{ $cardIcon }}"></i></span></span>
                <span class="cr-card-count">{{ number_format($cardCount) }}</span>
                <span class="cr-card-hint">{{ $cardHint }}</span>
            </button>
        @endforeach
        <div class="cr-card cr-card-info">
            <span class="cr-card-top"><span class="cr-card-label">Students</span><span class="cr-card-icon"><i class="ti ti-school"></i></span></span>
            <span class="cr-card-count">{{ number_format($stats['students']) }}</span>
            <span class="cr-card-hint">Across all courses</span>
        </div>
    </div>

    <div class="cr-panel">
        <div class="cr-toolbar">
            <div class="cr-search">
                <i class="ti ti-search"></i>
                <input type="search" class="form-control form-control-sm" placeholder="Course name, code or description" wire:model.debounce.400ms="search" aria-label="Search courses">
            </div>
            <select class="form-select form-select-sm" wire:model="filterStatus" aria-label="Show">
                <option value="">Any status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="unassigned">Not offered by any institute</option>
            </select>
            @if($hasFilters)
                <button type="button" class="btn btn-sm btn-light" wire:click="clearFilters"><i class="ti ti-x me-1"></i> Clear</button>
            @endif
            <span class="ms-auto small text-muted">{{ number_format($courses->total()) }} {{ \Illuminate\Support\Str::plural('course', $courses->total()) }}</span>
        </div>

        <div class="table-responsive position-relative">
            <div class="cr-loading" wire:loading.delay.flex wire:target="search, filterStatus, clearFilters, toggleStatus, delete, gotoPage, nextPage, previousPage">
                <span class="spinner-border spinner-border-sm text-secondary"></span>
            </div>
            <table class="table align-middle mb-0 cr-table">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Course Code</th>
                        <th>Structure</th>
                        <th>Offered at</th>
                        <th>12th eligibility</th>
                        <th class="text-center">Students</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($courses as $course)
                        @php $inUse = $course->offerings_count || $course->students_count; @endphp
                        <tr wire:key="course-{{ $course->id }}" class="{{ $course->status ? '' : 'cr-row-off' }}">
                            <td style="min-width: 240px;">
                                <div class="cr-name">
                                    <span class="cr-name-icon"><i class="ti ti-book-2"></i></span>
                                    <span class="min-w-0">
                                        <span class="d-block fw-semibold">{{ $course->name }}</span>
                                        @if($course->description)
                                            <span class="cr-sub d-block text-truncate" style="max-width: 280px;" title="{{ $course->description }}">{{ $course->description }}</span>
                                        @endif
                                    </span>
                                </div>
                            </td>
                            <td><span class="cr-code">{{ $course->code ?: '—' }}</span></td>
                            <td class="text-nowrap">
                                <div class="small fw-medium"><i class="ti ti-clock text-muted"></i> {{ $course->duration_months ? $course->duration_months . ' months' : 'Not set' }}</div>
                                <div class="cr-sub">{{ $course->period_summary }}</div>
                            </td>
                            <td style="min-width: 170px;">
                                @if($course->offerings_count)
                                    <div class="cr-chips">
                                        @foreach($course->instituteCourses->take(3) as $link)
                                            <a href="{{ route('admin.institute-courses.institute', $link->institute_id) }}" class="cr-chip {{ $link->status ? '' : 'is-off' }}"
                                               title="{{ optional($link->institute)->name }}{{ $link->status ? '' : ' (inactive)' }}">{{ optional($link->institute)->code ?? optional($link->institute)->name }}</a>
                                        @endforeach
                                        @if($course->offerings_count > 3)<span class="cr-chip is-more">+{{ $course->offerings_count - 3 }}</span>@endif
                                    </div>
                                    <div class="cr-sub mt-1">{{ $course->active_offerings_count }} of {{ $course->offerings_count }} active</div>
                                @else
                                    <a href="{{ route('admin.institute-courses') }}" class="cr-flag"><i class="ti ti-building-off"></i> Not offered yet</a>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.masters.course-eligibility', ['course' => $course->id]) }}" class="cr-flag {{ $course->eligibility_criteria_count ? 'is-ok' : 'is-none' }}">
                                    @if($course->eligibility_criteria_count)
                                        <i class="ti ti-list-check"></i> {{ $course->eligibility_criteria_count }} {{ \Illuminate\Support\Str::plural('rule', $course->eligibility_criteria_count) }}
                                    @else
                                        <i class="ti ti-plus"></i> Set criteria
                                    @endif
                                </a>
                            </td>
                            <td class="text-center">
                                @if($course->students_count)
                                    <a href="{{ route('admin.students', ['course' => $course->id]) }}" class="cr-count" title="Open these students">{{ number_format($course->students_count) }}</a>
                                @else
                                    <span class="cr-count is-zero">0</span>
                                @endif
                            </td>
                            <td>
                                <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
                                    <input class="form-check-input cr-switch" type="checkbox" role="switch" id="courseStatus{{ $course->id }}" @checked($course->status)
                                           wire:click="toggleStatus({{ $course->id }})" wire:loading.attr="disabled" wire:target="toggleStatus"
                                           aria-label="{{ $course->status ? 'Deactivate' : 'Activate' }} {{ $course->code }}">
                                    <label class="small {{ $course->status ? 'text-success fw-semibold' : 'text-muted' }}" for="courseStatus{{ $course->id }}">{{ $course->status ? 'Active' : 'Inactive' }}</label>
                                </div>
                            </td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-warning" wire:click="edit({{ $course->id }})" title="Edit"><i class="ti ti-edit"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="confirmDelete({{ $course->id }})"
                                        title="{{ $inUse ? 'In use: set it Inactive instead' : 'Delete' }}" @disabled($inUse)><i class="ti ti-trash"></i></button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="cr-empty">
                                <i class="ti ti-books"></i>
                                <div class="fw-semibold">{{ $hasFilters ? 'No courses match these filters' : 'No courses yet' }}</div>
                                @unless($hasFilters)
                                    <button type="button" class="btn btn-sm btn-primary mt-2" wire:click="openModal"><i class="ti ti-circle-plus me-1"></i> Add the first course</button>
                                @endunless
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($courses->hasPages())<div class="cr-foot">{{ $courses->links() }}</div>@endif
    </div>

    @include('livewire.admin.masters.partials.master-modals')

    <style>
        .cr-ui { --cr-border: #E5E7EB; --cr-soft: #F1F2F4; --cr-ink: #111827; --cr-muted: #6B7280; --cr-accent: #F26522; }
        .cr-ui .min-w-0 { min-width: 0; }
        .cr-ui .cr-hero { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; padding: 18px 22px; border-radius: 14px; border: 1px solid var(--cr-border);
            background: radial-gradient(circle at 100% 0, rgba(242, 101, 34, .12), transparent 45%), linear-gradient(135deg, #FFFFFF 0%, #FFF8F3 100%); }
        .cr-ui .cr-hero h2 { font-size: 22px; color: var(--cr-ink); }
        .cr-ui .cr-hero-cta { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 999px; border: 0; background: var(--cr-accent); color: #fff; font-size: 14px; font-weight: 500; box-shadow: 0 6px 16px rgba(242, 101, 34, .3); }
        .cr-ui .cr-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
        .cr-ui .cr-card { --tone: #4338CA; --tone-soft: #EEF2FF; position: relative; overflow: hidden; display: flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: 14px 16px 16px; background: #fff; border: 1px solid var(--cr-border); border-radius: 14px; text-align: left; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); transition: box-shadow .15s, transform .15s; }
        .cr-ui button.cr-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16, 24, 40, .08); }
        .cr-ui .cr-card::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: var(--tone); opacity: 0; }
        .cr-ui .cr-card.is-active { border-color: var(--tone); background: linear-gradient(180deg, var(--tone-soft) 0%, #fff 70%); }
        .cr-ui .cr-card.is-active::after { opacity: 1; }
        .cr-ui .cr-card-top { display: flex; justify-content: space-between; align-items: center; width: 100%; }
        .cr-ui .cr-card-label { font-size: 13px; font-weight: 600; color: #374151; }
        .cr-ui .cr-card-icon { width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 19px; background: var(--tone-soft); color: var(--tone); }
        .cr-ui .cr-card-count { font-size: 26px; font-weight: 700; color: var(--cr-ink); line-height: 1.15; }
        .cr-ui .cr-card-hint { font-size: 12px; color: var(--cr-muted); }
        .cr-ui .cr-card-ok { --tone: #16A34A; --tone-soft: #DCFCE7; } .cr-ui .cr-card-muted { --tone: #6B7280; --tone-soft: #F3F4F6; }
        .cr-ui .cr-card-warn { --tone: #D97706; --tone-soft: #FEF3C7; } .cr-ui .cr-card-info { --tone: #0284C7; --tone-soft: #E0F2FE; }
        .cr-ui .cr-panel { background: #fff; border: 1px solid var(--cr-border); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); overflow: hidden; }
        .cr-ui .cr-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--cr-soft); background: #FCFCFD; }
        .cr-ui .cr-toolbar .form-select { width: auto; min-width: 150px; max-width: 260px; border-radius: 8px; }
        .cr-ui .cr-search { position: relative; width: 300px; max-width: 100%; }
        .cr-ui .cr-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
        .cr-ui .cr-search input { padding-left: 32px; border-radius: 8px; }
        .cr-ui .cr-table thead th { background: #F9FAFB; font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--cr-muted); border-bottom: 1px solid var(--cr-border); padding: 10px 14px; white-space: nowrap; }
        .cr-ui .cr-table td { padding: 12px 14px; border-color: var(--cr-soft); font-size: 14px; }
        .cr-ui .cr-table tbody tr:hover td { background: #FAFAFB; }
        .cr-ui .cr-row-off td { background: #FAFAFA; color: #9CA3AF; }
        .cr-ui .cr-name { display: flex; align-items: center; gap: 10px; }
        .cr-ui .cr-name-icon { flex-shrink: 0; width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; background: #EEF2FF; color: #4338CA; font-size: 18px; }
        .cr-ui .cr-row-off .cr-name-icon { background: #F3F4F6; color: #9CA3AF; }
        .cr-ui .cr-code { display: inline-flex; padding: 4px 11px; border-radius: 8px; font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 13px; font-weight: 700; letter-spacing: .03em; white-space: nowrap;
            background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%); border: 1px solid #FED7AA; color: #C2410C; }
        .cr-ui .cr-row-off .cr-code { background: #F3F4F6; border-color: #E5E7EB; color: #6B7280; }
        .cr-ui .cr-sub { font-size: 12px; color: var(--cr-muted); }
        .cr-ui .cr-chips { display: flex; flex-wrap: wrap; gap: 5px; }
        .cr-ui .cr-chip { padding: 1px 8px; border-radius: 6px; background: #F3F4F6; color: #374151; font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 11px; font-weight: 600; text-decoration: none; border: 1px solid #E5E7EB; }
        .cr-ui a.cr-chip:hover { background: #E5E7EB; }
        .cr-ui .cr-chip.is-off { color: #9CA3AF; text-decoration: line-through; }
        .cr-ui .cr-chip.is-more { background: #fff; color: var(--cr-muted); }
        .cr-ui .cr-flag { display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 500; white-space: nowrap; text-decoration: none; background: #FFFBEB; border: 1px solid #FDE68A; color: #B45309; }
        .cr-ui .cr-flag.is-ok { background: #ECFDF3; border-color: #BBF7D0; color: #15803D; }
        .cr-ui .cr-flag.is-none { background: #F9FAFB; border-color: #E5E7EB; color: #6B7280; }
        .cr-ui a.cr-flag:hover { text-decoration: underline; }
        .cr-ui .cr-count { display: inline-flex; min-width: 34px; justify-content: center; padding: 2px 10px; border-radius: 999px; background: #E0F2FE; color: #0369A1; font-weight: 600; font-size: 13px; text-decoration: none; }
        .cr-ui a.cr-count:hover { background: #BAE6FD; }
        .cr-ui .cr-count.is-zero { background: #F3F4F6; color: #9CA3AF; }
        .cr-ui .cr-switch { width: 2.4em; height: 1.3em; cursor: pointer; }
        .cr-ui .cr-switch:checked { background-color: #16A34A; border-color: #16A34A; }
        .cr-ui .cr-empty { text-align: center; color: var(--cr-muted); padding: 48px 12px !important; }
        .cr-ui .cr-empty > i { font-size: 36px; color: #D1D5DB; display: block; margin-bottom: 6px; }
        .cr-ui .cr-loading { display: none; position: absolute; inset: 0; z-index: 2; background: rgba(255, 255, 255, .6); align-items: center; justify-content: center; }
        .cr-ui .cr-foot { padding: 12px 16px; border-top: 1px solid var(--cr-soft); }
        .cr-ui .cr-foot .pagination { margin: 0; }
        @media (max-width: 575.98px) {
            .cr-ui .cr-hero { padding: 14px 16px; }
            .cr-ui .cr-hero-cta { width: 100%; justify-content: center; }
            .cr-ui .cr-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .cr-ui .cr-search, .cr-ui .cr-toolbar .form-select { width: 100%; max-width: none; }
        }
    </style>
</div>

@include('livewire.admin.masters.partials.master-modals-assets')
