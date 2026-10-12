@php
    use App\Http\Livewire\Admin\Notifications\NotificationLogComponent as NL;
    $fmt = fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (is_bool($v) ? ($v ? 'true' : 'false') : (string) $v);
    $dayLabel = fn ($d) => $d->isToday() ? 'Today' : ($d->isYesterday() ? 'Yesterday' : $d->format('l, d M Y'));
    $lastDay = null;
    $total = $statusCounts->sum();
@endphp
<div class="content mon-ui">
    {{-- Header --}}
    <div class="mon-head mb-3">
        <div class="min-w-0">
            <h2 class="mb-1 fw-bold">Notification Log</h2>
            <nav>
                <ol class="breadcrumb mb-1 sl-crumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Monitoring</li>
                    <li class="breadcrumb-item active">Notification Log</li>
                </ol>
            </nav>
            <div class="text-muted small">Emails and SMS messages sent to students and staff. Click a message to read it in full.</div>
        </div>
        {{-- Status tabs (counts within the institute filter) --}}
        <div class="nl-tabs" role="tablist">
            <button type="button" class="{{ $filterStatus === '' ? 'is-active' : '' }}" wire:click="$set('filterStatus', '')">All <span>{{ number_format($total) }}</span></button>
            @foreach(NL::STATUSES as $key => [$label, $tone])
                <button type="button" class="nl-tab-{{ $tone }} {{ $filterStatus === $key ? 'is-active' : '' }}" wire:click="$set('filterStatus', '{{ $key }}')">{{ $label }} <span>{{ number_format($statusCounts[$key] ?? 0) }}</span></button>
            @endforeach
        </div>
    </div>

    @if(($statusCounts['pending'] ?? 0) > 0 && $filterStatus !== 'pending')
        <div class="nl-note mb-3"><i class="ti ti-info-circle"></i>
            <span>{{ number_format($statusCounts['pending']) }} {{ \Illuminate\Support\Str::plural('message', $statusCounts['pending']) }} not sent yet — email sending is switched off until the mail service (SMTP2GO) is set up, and SMS until a gateway is chosen.</span>
        </div>
    @endif

    <div class="mon-panel">
        {{-- Filters --}}
        <div class="mon-filters">
            <div class="mon-search">
                <i class="ti ti-search"></i>
                <input type="search" class="form-control" placeholder="Search recipient, student name, ER number, subject or message" wire:model.debounce.400ms="search" aria-label="Search">
            </div>
            <div class="mon-filter-grid">
                @if($isSuperAdmin)
                    <select class="form-select form-select-sm" wire:model="filterInstitute" aria-label="Institute">
                        <option value="">All institutes</option>
                        <option value="central">Central (no institute)</option>
                        @foreach($institutes as $inst)<option value="{{ $inst->id }}">{{ $inst->name }}</option>@endforeach
                    </select>
                @endif
                <select class="form-select form-select-sm" wire:model="filterChannel" aria-label="Channel">
                    <option value="">All channels</option>
                    @foreach(NL::CHANNELS as $key => [$label])<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
                <select class="form-select form-select-sm" wire:model="filterEvent" aria-label="Event">
                    <option value="">All events</option>
                    @foreach($events as $ev)<option value="{{ $ev }}">{{ NL::eventLabel($ev) }}</option>@endforeach
                </select>
                <select class="form-select form-select-sm" wire:model="filterStatus" aria-label="Status">
                    <option value="">Any status</option>
                    @foreach(NL::STATUSES as $key => [$label])<option value="{{ $key }}">{{ $label }}</option>@endforeach
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
                <span class="ms-auto small text-muted">{{ number_format($logs->total()) }} {{ \Illuminate\Support\Str::plural('message', $logs->total()) }}</span>
                @if($hasFilters)<button type="button" class="btn btn-sm btn-light" wire:click="clearFilters"><i class="ti ti-x me-1"></i> Clear filters</button>@endif
            </div>
        </div>

        {{-- Messages --}}
        <div class="table-responsive position-relative">
            <div class="mon-loading" wire:loading.delay.flex wire:target="search, filterInstitute, filterChannel, filterEvent, filterStatus, dateFrom, dateTo, range, clearFilters, gotoPage, nextPage, previousPage">
                <span class="spinner-border spinner-border-sm text-secondary"></span>
            </div>
            <table class="table align-middle mb-0 mon-table">
                <thead>
                    <tr>
                        <th style="width: 110px;">Time</th>
                        <th>Recipient</th>
                        <th>Event</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        @php
                            $day = $log->created_at->copy()->startOfDay();
                            [$name, $type] = $who($log);
                            [$statusLabel, $statusTone] = NL::STATUSES[$log->status] ?? [ucfirst($log->status), 'slate'];
                            [$channelLabel, $channelIcon] = NL::CHANNELS[$log->channel] ?? [strtoupper($log->channel), 'ti ti-send'];
                        @endphp
                        @if(!$lastDay || !$lastDay->equalTo($day))
                            @php $lastDay = $day; @endphp
                            <tr class="mon-day"><td colspan="6"><i class="ti ti-calendar"></i> {{ $dayLabel($day) }}</td></tr>
                        @endif
                        <tr class="mon-row" wire:click="view({{ $log->id }})" wire:key="nl-{{ $log->id }}" title="Read the full message">
                            <td class="text-nowrap">
                                <div class="fw-semibold">{{ $log->created_at->format('h:i A') }}</div>
                                <div class="mon-sub">#{{ $log->id }}</div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2 min-w-0">
                                    <span class="nl-channel nl-channel-{{ $log->channel }}" title="{{ $channelLabel }}"><i class="{{ $channelIcon }}"></i></span>
                                    <div class="min-w-0">
                                        <div class="fw-medium text-truncate" style="max-width: 220px;">{{ $name }}</div>
                                        <div class="mon-sub text-truncate" style="max-width: 220px;">{{ $log->recipient ?: 'No address' }}@if($isSuperAdmin && $log->institute) · {{ $log->institute->name }}@endif</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-nowrap">
                                <span class="mon-chip mon-tone-violet">{{ NL::eventLabel($log->event_type) }}</span>
                                <div class="mon-sub mt-1">{{ $type }}</div>
                            </td>
                            <td><div class="mon-desc">{{ $log->payload['subject'] ?? \Illuminate\Support\Str::limit($log->payload['message'] ?? '—', 90) }}</div></td>
                            <td class="text-nowrap">
                                <span class="mon-chip mon-tone-{{ $statusTone }}">{{ $statusLabel }}</span>
                                @if($log->status === 'sent' && $log->sent_at)<div class="mon-sub mt-1">{{ $log->sent_at->format('h:i A') }}</div>@endif
                                @if($log->error)<div class="mon-sub mt-1 text-truncate" style="max-width: 180px;" title="{{ $log->error }}">{{ $log->error }}</div>@endif
                            </td>
                            <td class="text-end"><i class="ti ti-chevron-right text-muted"></i></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="mon-empty"><i class="ti ti-mail-off"></i><div class="fw-semibold">{{ $hasFilters ? 'No messages match these filters' : 'No messages yet' }}</div>
                            @if($hasFilters)<button type="button" class="btn btn-sm btn-light mt-2" wire:click="clearFilters">Clear filters</button>@endif</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())<div class="mon-foot">{{ $logs->links() }}</div>@endif
    </div>

    {{-- Full details pop-up --}}
    <div class="modal fade" id="notificationLogModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 mon-modal">
                @if($selected)
                    @php
                        [$sLabel, $sTone] = NL::STATUSES[$selected['status']] ?? [ucfirst($selected['status']), 'slate'];
                        [$cLabel, $cIcon] = NL::CHANNELS[$selected['channel']] ?? [strtoupper($selected['channel']), 'ti ti-send'];
                    @endphp
                    <div class="modal-header">
                        <div class="d-flex align-items-start gap-3 min-w-0">
                            <span class="mon-modal-icon mon-tone-violet"><i class="{{ $cIcon }}"></i></span>
                            <div class="min-w-0">
                                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                    <span class="mon-chip mon-tone-violet">{{ $selected['event'] }}</span>
                                    <span class="mon-chip mon-tone-{{ $sTone }}">{{ $sLabel }}</span>
                                    <span class="mon-sub">{{ $cLabel }} · message #{{ $selected['id'] }}</span>
                                </div>
                                <h5 class="modal-title mb-0">{{ $selected['subject'] ?? $selected['event'] }}</h5>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mon-facts">
                            <div><span>To</span><strong>{{ $selected['name'] }}</strong><small>{{ $selected['type'] }}</small></div>
                            <div><span>{{ $selected['channel'] === 'sms' ? 'Phone' : 'Email' }}</span><strong>{{ $selected['recipient'] ?: '—' }}</strong></div>
                            <div><span>Institute</span><strong>{{ $selected['institute'] }}</strong></div>
                            <div><span>Created</span><strong>{{ $selected['created'] }}</strong><small>{{ $selected['ago'] }}</small></div>
                            <div><span>Sent</span><strong>{{ $selected['sent'] ?? 'Not sent' }}</strong></div>
                        </div>

                        @if($selected['error'])
                            <div class="mon-callout {{ $selected['status'] === 'failed' ? 'is-error' : '' }}"><i class="ti ti-alert-triangle"></i>
                                <div><strong>{{ $selected['status'] === 'failed' ? 'Error' : 'Note' }}</strong><div class="text-break">{{ $selected['error'] }}</div></div>
                            </div>
                        @endif

                        <h6 class="mon-section">Message</h6>
                        @if($selected['subject'])<div class="mon-subject">{{ $selected['subject'] }}</div>@endif
                        <pre class="mon-message">{{ $selected['message'] ?? 'No message text was stored.' }}</pre>

                        @if($selected['extra'])
                            <h6 class="mon-section">More details</h6>
                            <dl class="mon-kv">
                                @foreach($selected['extra'] as $k => $v)
                                    <dt>{{ \Illuminate\Support\Str::of($k)->replace('_', ' ')->title() }}</dt><dd class="text-break">{{ \Illuminate\Support\Str::limit($fmt($v), 500) }}</dd>
                                @endforeach
                            </dl>
                        @endif

                        @if($selected['link'])
                            <div class="mt-3 text-end"><a href="{{ $selected['link'] }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-external-link me-1"></i> Open student's fees &amp; payments</a></div>
                        @endif
                    </div>
                @else
                    <div class="modal-body text-center py-5"><span class="spinner-border spinner-border-sm text-secondary"></span></div>
                @endif
            </div>
        </div>
    </div>

    @include('livewire.admin.monitoring.styles')
    <style>
        .mon-ui .nl-tabs { display: inline-flex; padding: 4px; border-radius: 12px; background: #F3F4F6; gap: 2px; flex-wrap: wrap; }
        .mon-ui .nl-tabs button { border: 0; background: transparent; padding: 7px 12px; border-radius: 9px; font-size: 13px; font-weight: 600; color: var(--mon-muted); display: inline-flex; align-items: center; gap: 6px; }
        .mon-ui .nl-tabs button span { padding: 0 7px; border-radius: 999px; background: rgba(0, 0, 0, .06); font-size: 11.5px; }
        .mon-ui .nl-tabs button.is-active { background: #fff; color: var(--mon-ink); box-shadow: 0 1px 3px rgba(16, 24, 40, .12); }
        .mon-ui .nl-tabs .nl-tab-green.is-active span { background: #DCFCE7; color: #15803D; }
        .mon-ui .nl-tabs .nl-tab-amber.is-active span { background: #FEF3C7; color: #B45309; }
        .mon-ui .nl-tabs .nl-tab-red.is-active span { background: #FEE2E2; color: #B91C1C; }
        .mon-ui .nl-note { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 12px; background: #FFFBEB; border: 1px solid #FDE68A; font-size: 13px; color: #78350F; }
        .mon-ui .nl-note i { font-size: 18px; color: #D97706; }
        .mon-ui .nl-channel { width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 16px; }
        .mon-ui .nl-channel-email { background: #E0F2FE; color: #0369A1; }
        .mon-ui .nl-channel-sms { background: #DCFCE7; color: #15803D; }
    </style>
</div>

<script>
document.addEventListener('livewire:load', function () {
    window.addEventListener('open-notification-log-modal', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('notificationLogModal')).show());
});
</script>
