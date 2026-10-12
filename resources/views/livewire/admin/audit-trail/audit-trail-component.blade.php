@php
    use App\Http\Livewire\Admin\AuditTrail\AuditTrailComponent as AT;
    $fmt = fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (is_bool($v) ? ($v ? 'true' : 'false') : (string) $v);
    $dayLabel = fn ($d) => $d->isToday() ? 'Today' : ($d->isYesterday() ? 'Yesterday' : $d->format('l, d M Y'));
    $lastDay = null;
@endphp
<div class="content mon-ui">
    {{-- Header --}}
    <div class="mon-head mb-3">
        <div class="min-w-0">
            <h2 class="mb-1 fw-bold">Audit Trail</h2>
            <nav>
                <ol class="breadcrumb mb-1 sl-crumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Monitoring</li>
                    <li class="breadcrumb-item active">Audit Trail</li>
                </ol>
            </nav>
            <div class="text-muted small">Every change, approval and sign-off, with who did it, when and from where. Click an entry for the full details.</div>
        </div>
        <span class="mon-count"><i class="ti ti-history"></i> {{ number_format($entries->total()) }} {{ \Illuminate\Support\Str::plural('entry', $entries->total()) }}</span>
    </div>

    <div class="mon-panel">
        {{-- Filters --}}
        <div class="mon-filters">
            <div class="mon-search">
                <i class="ti ti-search"></i>
                <input type="search" class="form-control" placeholder="Search description, user, record no. or IP" wire:model.debounce.400ms="search" aria-label="Search">
            </div>
            <div class="mon-filter-grid">
                @if($isSuperAdmin)
                    <select class="form-select form-select-sm" wire:model="filterInstitute" aria-label="Institute">
                        <option value="">All institutes</option>
                        <option value="central">Central (no institute)</option>
                        @foreach($institutes as $inst)<option value="{{ $inst->id }}">{{ $inst->name }}</option>@endforeach
                    </select>
                @endif
                <select class="form-select form-select-sm" wire:model="filterUser" aria-label="User">
                    <option value="">All users</option>
                    <option value="system">System (automatic)</option>
                    @foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                </select>
                <select class="form-select form-select-sm" wire:model="filterModule" aria-label="Module">
                    <option value="">All modules</option>
                    @foreach($modules as $m)<option value="{{ $m }}">{{ $label($m) }}</option>@endforeach
                </select>
                <select class="form-select form-select-sm" wire:model="filterAction" aria-label="Action">
                    <option value="">All actions</option>
                    @foreach($actions as $a)<option value="{{ $a }}">{{ $label($a) }}</option>@endforeach
                </select>
                <div class="mon-dates">
                    <input type="date" class="form-control form-control-sm" wire:model="dateFrom" aria-label="From date" max="{{ $dateTo ?: today()->toDateString() }}">
                    <span class="text-muted small">to</span>
                    <input type="date" class="form-control form-control-sm" wire:model="dateTo" aria-label="To date" min="{{ $dateFrom }}" max="{{ today()->toDateString() }}">
                </div>
            </div>
            <div class="mon-quick">
                <span class="small text-muted me-1">Quick:</span>
                <button type="button" class="mon-pill" wire:click="range('today')">Today</button>
                <button type="button" class="mon-pill" wire:click="range('7d')">Last 7 days</button>
                <button type="button" class="mon-pill" wire:click="range('30d')">Last 30 days</button>
                @if($hasFilters)<button type="button" class="btn btn-sm btn-light ms-auto" wire:click="clearFilters"><i class="ti ti-x me-1"></i> Clear filters</button>@endif
            </div>
        </div>

        {{-- Entries --}}
        <div class="table-responsive position-relative">
            <div class="mon-loading" wire:loading.delay.flex wire:target="search, filterInstitute, filterUser, filterModule, filterAction, dateFrom, dateTo, range, clearFilters, gotoPage, nextPage, previousPage">
                <span class="spinner-border spinner-border-sm text-secondary"></span>
            </div>
            <table class="table align-middle mb-0 mon-table">
                <thead>
                    <tr>
                        <th style="width: 110px;">Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>What happened</th>
                        <th class="d-none d-xl-table-cell">IP address</th>
                        <th style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $e)
                        @php $day = $e->created_at->copy()->startOfDay(); @endphp
                        @if(!$lastDay || !$lastDay->equalTo($day))
                            @php $lastDay = $day; @endphp
                            <tr class="mon-day"><td colspan="6"><i class="ti ti-calendar"></i> {{ $dayLabel($day) }}</td></tr>
                        @endif
                        @php $initials = $e->user ? collect(preg_split('/\s+/', trim($e->user->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') : null; @endphp
                        <tr class="mon-row" wire:click="view({{ $e->id }})" wire:key="audit-{{ $e->id }}" title="View details">
                            <td class="text-nowrap">
                                <div class="fw-semibold">{{ $e->created_at->format('h:i A') }}</div>
                                <div class="mon-sub">#{{ $e->id }}</div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2 min-w-0">
                                    <span class="mon-avatar {{ $e->user ? '' : 'is-system' }}">{!! $initials ? e($initials) : '<i class="ti ti-robot"></i>' !!}</span>
                                    <div class="min-w-0">
                                        <div class="fw-medium text-truncate" style="max-width: 190px;">{{ optional($e->user)->name ?? 'System' }}</div>
                                        <div class="mon-sub text-truncate" style="max-width: 190px;">{{ optional($e->institute)->name ?? 'Central' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-nowrap">
                                <span class="mon-chip mon-tone-{{ AT::actionTone($e->action) }}">{{ $label($e->action) }}</span>
                                <div class="mon-sub mt-1">{{ $label($e->module) }}</div>
                            </td>
                            <td>
                                <div class="mon-desc">{{ $e->meta['description'] ?? ($label($e->action) . ' ' . $label($e->module)) }}</div>
                                <div class="mon-sub">{{ $e->reference_label }}@if(!empty($e->meta['reason'])) · <span class="text-warning-emphasis">Reason: {{ \Illuminate\Support\Str::limit($e->meta['reason'], 60) }}</span>@endif</div>
                            </td>
                            <td class="d-none d-xl-table-cell"><span class="mon-mono">{{ $e->ip_address ?: '—' }}</span></td>
                            <td class="text-end"><i class="ti ti-chevron-right text-muted"></i></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="mon-empty"><i class="ti ti-history-off"></i><div class="fw-semibold">{{ $hasFilters ? 'No entries match these filters' : 'No audit entries yet' }}</div>
                            @if($hasFilters)<button type="button" class="btn btn-sm btn-light mt-2" wire:click="clearFilters">Clear filters</button>@endif</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($entries->hasPages())<div class="mon-foot">{{ $entries->links() }}</div>@endif
    </div>

    {{-- Details pop-up --}}
    <div class="modal fade" id="auditModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 mon-modal">
                @if($selected)
                    <div class="modal-header">
                        <div class="d-flex align-items-start gap-3 min-w-0">
                            <span class="mon-modal-icon mon-tone-{{ AT::actionTone($selected['action']) }}"><i class="ti ti-history"></i></span>
                            <div class="min-w-0">
                                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                    <span class="mon-chip mon-tone-{{ AT::actionTone($selected['action']) }}">{{ $label($selected['action']) }}</span>
                                    <span class="mon-sub">{{ $label($selected['module']) }} · entry #{{ $selected['id'] }}</span>
                                </div>
                                <h5 class="modal-title mb-0">{{ $selected['description'] ?? ($label($selected['action']) . ' ' . $label($selected['module'])) }}</h5>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mon-facts">
                            <div><span>When</span><strong>{{ $selected['when'] }}</strong><small>{{ $selected['ago'] }}</small></div>
                            <div><span>By</span><strong>{{ $selected['user'] }}</strong>@if($selected['email'])<small>{{ $selected['email'] }}</small>@endif</div>
                            <div><span>Institute</span><strong>{{ $selected['institute'] }}</strong></div>
                            <div><span>Record</span><strong>{{ $selected['reference'] }}</strong></div>
                            <div><span>IP address</span><strong class="mon-mono">{{ $selected['ip'] ?: '—' }}</strong></div>
                        </div>

                        @if($selected['reason'])
                            <div class="mon-callout"><i class="ti ti-message-2"></i><div><strong>Reason</strong><div>{{ $selected['reason'] }}</div></div></div>
                        @endif

                        @if($selected['old'] || $selected['new'])
                            @php $fields = array_unique(array_merge(array_keys($selected['old']), array_keys($selected['new']))); @endphp
                            <h6 class="mon-section">Changes</h6>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0 mon-diff">
                                    <thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead>
                                    <tbody>
                                        @foreach($fields as $field)
                                            @php $before = $selected['old'][$field] ?? null; $after = $selected['new'][$field] ?? null; $changed = $fmt($before) !== $fmt($after); @endphp
                                            <tr class="{{ $changed ? 'is-changed' : '' }}">
                                                <td><code>{{ $field }}</code></td>
                                                <td class="text-break mon-before">{{ $before === null ? '—' : \Illuminate\Support\Str::limit($fmt($before), 300) }}</td>
                                                <td class="text-break mon-after">{{ $after === null ? '—' : \Illuminate\Support\Str::limit($fmt($after), 300) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if($selected['extra'])
                            <h6 class="mon-section">More details</h6>
                            <dl class="mon-kv">
                                @foreach($selected['extra'] as $k => $v)
                                    <dt>{{ $label($k) }}</dt><dd class="text-break">{{ \Illuminate\Support\Str::limit($fmt($v), 500) }}</dd>
                                @endforeach
                            </dl>
                        @endif
                    </div>
                @else
                    <div class="modal-body text-center py-5"><span class="spinner-border spinner-border-sm text-secondary"></span></div>
                @endif
            </div>
        </div>
    </div>

    @include('livewire.admin.monitoring.styles')
</div>

<script>
document.addEventListener('livewire:load', function () {
    window.addEventListener('open-audit-modal', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('auditModal')).show());
});
</script>
