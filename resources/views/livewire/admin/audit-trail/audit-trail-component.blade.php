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
                    @php
                        $tone = AT::actionTone($selected['action']);
                        $fields = array_values(array_unique(array_merge(array_keys($selected['old']), array_keys($selected['new']))));
                        $rows = collect($fields)->map(function ($f) use ($selected) {
                            $before = \App\Support\Readable::value($selected['old'][$f] ?? null, $f);
                            $after = \App\Support\Readable::value($selected['new'][$f] ?? null, $f);
                            $hasOld = array_key_exists($f, $selected['old']);
                            $hasNew = array_key_exists($f, $selected['new']);

                            return ['label' => \App\Support\Readable::label($f), 'before' => $before, 'after' => $after,
                                'changed' => !$hasOld || !$hasNew || $before !== $after, 'onlyNew' => !$hasOld, 'onlyOld' => !$hasNew];
                        });
                        $changed = $rows->where('changed', true);
                        $unchanged = $rows->where('changed', false);
                    @endphp
                    {{-- Summary --}}
                    <div class="am-hero mon-tone-{{ $tone }}">
                        <button type="button" class="btn-close am-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        <span class="am-hero-icon"><i class="ti ti-{{ ['green' => 'circle-plus', 'blue' => 'pencil', 'red' => 'circle-minus', 'teal' => 'circle-check', 'amber' => 'alert-circle'][$tone] ?? 'history' }}"></i></span>
                        <div class="min-w-0">
                            <div class="am-kicker">{{ $label($selected['action']) }} · {{ $label($selected['module']) }}</div>
                            <h5 class="am-title">{{ $selected['description'] ?? ($label($selected['action']) . ' ' . $label($selected['module'])) }}</h5>
                            <div class="am-byline">by <strong>{{ $selected['user'] }}</strong> · {{ $selected['ago'] }}</div>
                        </div>
                    </div>

                    <div class="modal-body">
                        {{-- Who, when, where --}}
                        <ul class="am-info">
                            <li><span class="am-info-icon"><i class="ti ti-user"></i></span>
                                <div><span>Done by</span><strong>{{ $selected['user'] }}</strong><small>{{ $selected['role'] }}@if($selected['email']) · {{ $selected['email'] }}@endif</small></div></li>
                            <li><span class="am-info-icon"><i class="ti ti-clock"></i></span>
                                <div><span>When</span><strong>{{ $selected['when'] }}</strong><small>{{ $selected['ago'] }}</small></div></li>
                            <li><span class="am-info-icon"><i class="ti ti-building"></i></span>
                                <div><span>Institute</span><strong>{{ $selected['institute'] }}</strong></div></li>
                            <li><span class="am-info-icon"><i class="ti ti-file-description"></i></span>
                                <div><span>Record</span><strong>{{ $selected['reference'] ?? 'Not linked to a record' }}</strong></div></li>
                        </ul>

                        @if($selected['reason'])
                            <div class="am-reason"><i class="ti ti-quote"></i><div><span>Reason given</span><p>{{ $selected['reason'] }}</p></div></div>
                        @endif

                        {{-- What changed --}}
                        @if($rows->isNotEmpty())
                            <div class="am-section-head">
                                <h6 class="mon-section mb-0">What changed <span class="am-count">{{ $changed->count() }}</span></h6>
                                @if($unchanged->isNotEmpty())
                                    <button type="button" class="am-link" wire:click="$toggle('showUnchanged')">{{ $showUnchanged ? 'Hide' : 'Show' }} {{ $unchanged->count() }} unchanged</button>
                                @endif
                            </div>
                            <div class="am-changes">
                                @foreach($changed as $row)
                                    <div class="am-change">
                                        <div class="am-field">{{ $row['label'] }}</div>
                                        <div class="am-values">
                                            @if($row['onlyNew'])
                                                <span class="am-new">{{ $row['after'] }}</span><span class="am-tag">added</span>
                                            @elseif($row['onlyOld'])
                                                <span class="am-old">{{ $row['before'] }}</span><span class="am-tag">removed</span>
                                            @else
                                                <span class="am-old">{{ $row['before'] }}</span>
                                                <i class="ti ti-arrow-narrow-right am-arrow"></i>
                                                <span class="am-new">{{ $row['after'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                                @if($changed->isEmpty())<div class="am-change text-muted small">No values changed.</div>@endif
                                @if($showUnchanged)
                                    @foreach($unchanged as $row)
                                        <div class="am-change is-same"><div class="am-field">{{ $row['label'] }}</div><div class="am-values"><span>{{ $row['after'] }}</span></div></div>
                                    @endforeach
                                @endif
                            </div>
                        @endif

                        {{-- Technical details --}}
                        <details class="am-tech">
                            <summary><i class="ti ti-code"></i> Technical details</summary>
                            <dl class="mon-kv">
                                <dt>Entry no.</dt><dd>#{{ $selected['id'] }}</dd>
                                <dt>Action code</dt><dd><code>{{ $selected['action'] }}</code> in <code>{{ $selected['module'] }}</code></dd>
                                <dt>IP address</dt><dd class="mon-mono">{{ $selected['ip'] ?: '—' }}</dd>
                                @foreach($selected['extra'] as $k => $v)
                                    <dt>{{ \App\Support\Readable::label($k) }}</dt><dd class="text-break">{{ \App\Support\Readable::value($v, $k) }}</dd>
                                @endforeach
                            </dl>
                        </details>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Close</button>
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
