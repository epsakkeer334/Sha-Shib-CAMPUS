@php
    $statusInfo = ['sent' => ['Sent', 'ok', 'ti-circle-check'], 'pending' => ['Waiting', 'warn', 'ti-clock'], 'failed' => ['Failed', 'bad', 'ti-alert-circle']];
@endphp
<div class="content sm-ui">
    <div class="sm-head">
        <div class="min-w-0">
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Communication</li>
                    @if($send)
                        <li class="breadcrumb-item"><a href="#" wire:click.prevent="back">Sent Emails</a></li>
                        <li class="breadcrumb-item active">#{{ $send->id }}</li>
                    @else
                        <li class="breadcrumb-item active">Sent Emails</li>
                    @endif
                </ol>
            </nav>
            <h2 class="sm-title"><span class="sm-title-icon"><i class="ti ti-mail-opened"></i></span> {{ $send ? \Illuminate\Support\Str::limit($send->subject, 70) : 'Sent Emails' }}</h2>
            <p class="sm-lead">{{ $send ? $send->filters_summary : 'Every message sent from Send Email — who sent it, to whom, and how many arrived.' }}</p>
        </div>
        <div class="sm-head-side">
            @if($send)
                <button type="button" class="btn btn-light btn-sm" wire:click="back"><i class="ti ti-arrow-left me-1"></i> All sent emails</button>
                <a href="{{ route('admin.emails.send', ['from' => $send->id]) }}" class="btn btn-light btn-sm"><i class="ti ti-copy me-1"></i> Use again</a>
            @endif
            <a href="{{ route('admin.emails.send') }}" class="btn btn-primary btn-sm"><i class="ti ti-mail-forward me-1"></i> Send Email</a>
        </div>
    </div>

    @if($send)
        @php $total = array_sum($counts); $pct = $total ? round($counts['sent'] / $total * 100) : 0; @endphp

        @if($justSent === $send->id)
            <div class="sm-note ok mb-3"><i class="ti ti-circle-check"></i>
                <span><b>Sending started.</b> {{ number_format($send->recipients_total) }} {{ \Illuminate\Support\Str::plural('email', $send->recipients_total) }} are being sent in the background — refresh in a minute to see them arrive.</span></div>
        @endif

        <div class="sm-stats">
            <div class="sm-stat"><span class="sm-stat-icon"><i class="ti ti-users"></i></span><span><b>{{ number_format($send->recipients_total) }}</b><small>Receivers</small></span></div>
            <div class="sm-stat"><span class="sm-stat-icon is-green"><i class="ti ti-circle-check"></i></span><span><b>{{ number_format($counts['sent']) }}</b><small>Sent · {{ $pct }}%</small></span></div>
            <div class="sm-stat"><span class="sm-stat-icon is-amber"><i class="ti ti-clock"></i></span><span><b>{{ number_format($counts['pending']) }}</b><small>Waiting</small></span></div>
            <div class="sm-stat"><span class="sm-stat-icon is-red"><i class="ti ti-alert-circle"></i></span><span><b>{{ number_format($counts['failed']) }}</b><small>Failed</small></span></div>
        </div>

        <div class="sm-detail">
            <div class="sm-panel">
                <div class="sm-panel-head">
                    <div class="min-w-0 flex-grow-1"><h5>Receivers</h5><p>Status of each email · updates every 15 s</p></div>
                    @if($counts['failed'])
                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="retryFailed" wire:loading.attr="disabled" wire:target="retryFailed"><i class="ti ti-refresh me-1"></i> Send failed again</button>
                    @endif
                </div>
                <div class="sm-toolbar">
                    <div class="sm-search"><i class="ti ti-search"></i><input type="text" class="form-control form-control-sm" wire:model.debounce.400ms="recipientSearch" placeholder="Search name, ER number or email…" aria-label="Search receivers"></div>
                    <div class="sm-seg">
                        <button type="button" class="{{ $recipientStatus === '' ? 'on' : '' }}" wire:click="$set('recipientStatus', '')">All</button>
                        @foreach($statusInfo as $key => [$label])
                            <button type="button" class="{{ $recipientStatus === $key ? 'on' : '' }}" wire:click="$set('recipientStatus', '{{ $key }}')">{{ $label }} <em>{{ number_format($counts[$key]) }}</em></button>
                        @endforeach
                    </div>
                </div>
                <div class="table-responsive" wire:poll.15s>
                    <table class="table sm-table mb-0">
                        <thead><tr><th>Receiver</th><th>Email</th><th>Status</th><th class="text-end">Sent</th></tr></thead>
                        <tbody>
                            @forelse($logs as $log)
                                @php [$name, $sub] = \App\Http\Livewire\Admin\Emails\SentEmailsComponent::who($log); [$sLabel, $sClass, $sIcon] = $statusInfo[$log->status] ?? $statusInfo['pending']; @endphp
                                <tr wire:key="sl-{{ $log->id }}">
                                    <td><b class="d-block">{{ $name }}</b><small class="text-muted">{{ $sub }}</small></td>
                                    <td class="text-break">{{ $log->recipient ?: '—' }}</td>
                                    <td>
                                        <span class="sm-badge {{ $sClass }}"><i class="ti {{ $sIcon }}"></i> {{ $sLabel }}</span>
                                        @if($log->error)<small class="d-block sm-err" title="{{ $log->error }}">{{ \Illuminate\Support\Str::limit($log->error, 70) }}</small>@endif
                                    </td>
                                    <td class="text-end text-nowrap"><small class="text-muted">{{ $log->sent_at ? $log->sent_at->format('d M, h:i A') : '—' }}</small></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No receivers match.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($logs->hasPages())<div class="sm-pager">{{ $logs->links() }}</div>@endif
            </div>

            <div class="sm-panel sm-side">
                <div class="sm-panel-head"><div class="min-w-0"><h5>Details</h5></div></div>
                <dl class="sm-dl">
                    <dt>Sent by</dt><dd>{{ optional($send->sender)->name ?? '—' }}</dd>
                    <dt>When</dt><dd>{{ $send->created_at->format('d M Y, h:i A') }} <small>({{ $send->created_at->diffForHumans() }})</small></dd>
                    <dt>To</dt><dd>{{ $send->filters_summary }}</dd>
                    @if($send->institute)<dt>Institute</dt><dd>{{ $send->institute->name }}</dd>@endif
                    @if($send->template_key)<dt>Template</dt><dd>{{ config("email_templates.templates.{$send->template_key}.name", $send->template_key) }}</dd>@endif
                    @if($send->skipped_no_email || $send->skipped_duplicate)
                        <dt>Left out</dt><dd>{{ $send->skipped_no_email }} without email · {{ $send->skipped_duplicate }} same address twice</dd>
                    @endif
                </dl>
                <div class="sm-progress"><span style="width: {{ $pct }}%"></span></div>
                <div class="sm-frame-head"><i class="ti ti-eye"></i> First email as sent</div>
                <div class="sm-frame">
                    @if($previewHtml)
                        <iframe class="sm-preview" srcdoc="{{ $previewHtml }}" title="Email as sent" sandbox=""></iframe>
                    @else
                        <div class="p-3 text-muted small">No email kept for this send.</div>
                    @endif
                </div>
                <div class="sm-side-foot">
                    <a href="{{ route('admin.notifications', ['channel' => 'email', 'event' => \App\Services\BulkEmailService::EVENT]) }}" class="small"><i class="ti ti-mail-check me-1"></i> Open the Email &amp; SMS Log</a>
                </div>
            </div>
        </div>
    @else
        <div class="sm-panel">
            <div class="sm-toolbar is-top">
                <div class="sm-search"><i class="ti ti-search"></i><input type="text" class="form-control form-control-sm" wire:model.debounce.400ms="search" placeholder="Search subject or receivers…" aria-label="Search sent emails"></div>
                <div class="sm-seg">
                    <button type="button" class="{{ $filterAudience === '' ? 'on' : '' }}" wire:click="$set('filterAudience', '')">All</button>
                    @foreach(\App\Models\Admin\EmailCampaign::AUDIENCES as $key => [$label, $icon])
                        <button type="button" class="{{ $filterAudience === $key ? 'on' : '' }}" wire:click="$set('filterAudience', '{{ $key }}')"><i class="{{ $icon }}"></i> {{ $label }}</button>
                    @endforeach
                </div>
            </div>
            <div class="sm-list">
                @forelse($campaigns as $c)
                    @php $t = $c->sent_count + $c->pending_count + $c->failed_count; $p = $t ? round($c->sent_count / $t * 100) : 0; @endphp
                    <button type="button" class="sm-item" wire:click="open({{ $c->id }})" wire:key="sc-{{ $c->id }}">
                        <span class="sm-item-icon {{ $c->audience }}"><i class="{{ \App\Models\Admin\EmailCampaign::AUDIENCES[$c->audience][1] ?? 'ti ti-mail' }}"></i></span>
                        <span class="min-w-0 flex-grow-1">
                            <b class="sm-item-subject">{{ $c->subject }}</b>
                            <small class="sm-item-sub">{{ $c->filters_summary }}</small>
                            <small class="sm-item-meta"><i class="ti ti-user"></i> {{ optional($c->sender)->name ?? '—' }} · {{ $c->created_at->format('d M Y, h:i A') }}</small>
                        </span>
                        <span class="sm-item-counts">
                            <span class="sm-item-total">{{ number_format($c->recipients_total) }} <small>{{ \Illuminate\Support\Str::plural('email', $c->recipients_total) }}</small></span>
                            <span class="sm-progress is-small"><span style="width: {{ $p }}%"></span></span>
                            <span class="sm-item-badges">
                                <span class="sm-badge ok">{{ $c->sent_count }} sent</span>
                                @if($c->pending_count)<span class="sm-badge warn">{{ $c->pending_count }} waiting</span>@endif
                                @if($c->failed_count)<span class="sm-badge bad">{{ $c->failed_count }} failed</span>@endif
                            </span>
                        </span>
                        <i class="ti ti-chevron-right sm-item-arrow"></i>
                    </button>
                @empty
                    <div class="sm-blank">
                        <span class="sm-blank-icon"><i class="ti ti-mail-forward"></i></span>
                        <b>{{ trim($search) || $filterAudience ? 'Nothing matches.' : 'Nothing sent yet.' }}</b>
                        <p>Messages sent from Send Email appear here with the status of every email.</p>
                        <a href="{{ route('admin.emails.send') }}" class="btn btn-primary btn-sm"><i class="ti ti-mail-forward me-1"></i> Send Email</a>
                    </div>
                @endforelse
            </div>
            @if($campaigns->hasPages())<div class="sm-pager">{{ $campaigns->links() }}</div>@endif
        </div>
    @endif

    <style>
        .sm-ui { --sm-line: #E6E8EE; --sm-ink: #0F172A; --sm-muted: #64748B; --sm-soft: #F8FAFC; --sm-accent: #F26522; --sm-radius: 14px; }
        .sm-ui .min-w-0 { min-width: 0; }
        .sm-ui .sm-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
        .sm-ui .sm-title { display: flex; align-items: center; gap: 10px; font-size: 22px; font-weight: 700; color: var(--sm-ink); margin: 2px 0 4px; word-break: break-word; }
        .sm-ui .sm-title-icon { width: 38px; height: 38px; flex-shrink: 0; border-radius: 11px; display: inline-grid; place-items: center; background: linear-gradient(135deg, #2563EB, #7C3AED); color: #fff; font-size: 20px; box-shadow: 0 6px 14px rgba(79, 70, 229, .25); }
        .sm-ui .sm-lead { margin: 0; color: var(--sm-muted); font-size: 13.5px; word-break: break-word; }
        .sm-ui .sm-head-side { display: flex; gap: 8px; flex-wrap: wrap; }
        .sm-ui .sm-head-side .btn-light { background: #fff; border: 1px solid var(--sm-line); }
        .sm-ui .sm-note { display: flex; gap: 10px; padding: 11px 14px; border-radius: 12px; font-size: 13px; }
        .sm-ui .sm-note i { font-size: 17px; flex-shrink: 0; margin-top: 1px; }
        .sm-ui .sm-note.ok { background: #F0FDF4; color: #166534; border: 1px solid #BBF7D0; }

        .sm-ui .sm-panel { background: #fff; border: 1px solid var(--sm-line); border-radius: var(--sm-radius); overflow: hidden; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }
        .sm-ui .sm-panel-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 13px 18px; border-bottom: 1px solid var(--sm-line); }
        .sm-ui .sm-panel-head h5 { font-size: 15px; font-weight: 700; color: var(--sm-ink); margin: 0; }
        .sm-ui .sm-panel-head p { margin: 1px 0 0; font-size: 12px; color: var(--sm-muted); }
        .sm-ui .sm-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; padding: 10px 18px; border-bottom: 1px solid var(--sm-line); background: var(--sm-soft); }
        .sm-ui .sm-search { position: relative; flex: 1 1 240px; max-width: 360px; }
        .sm-ui .sm-search > i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94A3B8; }
        .sm-ui .sm-search .form-control { padding-left: 32px; }
        .sm-ui .sm-seg { display: inline-flex; padding: 3px; border-radius: 10px; background: #EEF1F6; gap: 2px; flex-wrap: wrap; }
        .sm-ui .sm-seg button { border: 0; background: transparent; padding: 5px 11px; border-radius: 7px; font-size: 12.5px; font-weight: 600; color: #475569; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; }
        .sm-ui .sm-seg button.on { background: #fff; color: var(--sm-ink); box-shadow: 0 1px 3px rgba(15, 23, 42, .12); }
        .sm-ui .sm-seg em { font-style: normal; font-size: 11px; color: #94A3B8; }

        .sm-ui .sm-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
        .sm-ui .sm-stat { display: flex; align-items: center; gap: 12px; padding: 12px 14px; background: #fff; border: 1px solid var(--sm-line); border-radius: var(--sm-radius); }
        .sm-ui .sm-stat b { display: block; font-size: 19px; line-height: 1.1; color: var(--sm-ink); }
        .sm-ui .sm-stat small { color: var(--sm-muted); font-size: 12px; }
        .sm-ui .sm-stat-icon { width: 38px; height: 38px; flex-shrink: 0; border-radius: 10px; display: grid; place-items: center; font-size: 19px; background: #EEF2FF; color: #4338CA; }
        .sm-ui .sm-stat-icon.is-green { background: #DCFCE7; color: #15803D; } .sm-ui .sm-stat-icon.is-amber { background: #FEF3C7; color: #B45309; } .sm-ui .sm-stat-icon.is-red { background: #FEF2F2; color: #B91C1C; }

        .sm-ui .sm-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
        .sm-ui .sm-badge.ok { background: #DCFCE7; color: #166534; } .sm-ui .sm-badge.warn { background: #FEF3C7; color: #92400E; } .sm-ui .sm-badge.bad { background: #FEE2E2; color: #991B1B; }
        .sm-ui .sm-err { color: #B91C1C; margin-top: 3px; font-size: 11.5px; }
        .sm-ui .sm-progress { display: block; height: 6px; border-radius: 999px; background: #EEF1F6; overflow: hidden; margin: 0 18px 14px; }
        .sm-ui .sm-progress > span { display: block; height: 100%; background: linear-gradient(90deg, #22C55E, #16A34A); border-radius: inherit; }
        .sm-ui .sm-progress.is-small { margin: 4px 0; height: 5px; width: 100%; }

        /* List */
        .sm-ui .sm-list { padding: 6px; }
        .sm-ui .sm-item { width: 100%; display: flex; align-items: center; gap: 14px; padding: 12px; border: 0; border-radius: 12px; background: transparent; text-align: left; border-bottom: 1px solid #F1F5F9; }
        .sm-ui .sm-item:last-child { border-bottom: 0; }
        .sm-ui .sm-item:hover { background: var(--sm-soft); }
        .sm-ui .sm-item-icon { width: 40px; height: 40px; flex-shrink: 0; border-radius: 12px; display: grid; place-items: center; font-size: 19px; background: #FFF1E8; color: #C2410C; }
        .sm-ui .sm-item-icon.staff { background: #EEF2FF; color: #4338CA; }
        .sm-ui .sm-item-subject { display: block; font-size: 14px; color: var(--sm-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sm-ui .sm-item-sub { display: block; font-size: 12.5px; color: #475569; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sm-ui .sm-item-meta { display: block; font-size: 11.5px; color: #94A3B8; margin-top: 2px; }
        .sm-ui .sm-item-counts { width: 220px; flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-end; }
        .sm-ui .sm-item-total { font-size: 15px; font-weight: 700; color: var(--sm-ink); }
        .sm-ui .sm-item-total small { font-size: 11.5px; color: var(--sm-muted); font-weight: 600; }
        .sm-ui .sm-item-badges { display: flex; gap: 4px; flex-wrap: wrap; justify-content: flex-end; }
        .sm-ui .sm-item-arrow { color: #CBD5E1; font-size: 18px; }
        .sm-ui .sm-blank { text-align: center; padding: 48px 16px; }
        .sm-ui .sm-blank-icon { width: 54px; height: 54px; border-radius: 16px; display: inline-grid; place-items: center; background: #EEF2FF; color: #4338CA; font-size: 26px; margin-bottom: 10px; }
        .sm-ui .sm-blank b { display: block; font-size: 15px; color: var(--sm-ink); }
        .sm-ui .sm-blank p { color: var(--sm-muted); font-size: 13px; margin: 4px 0 14px; }
        .sm-ui .sm-pager { padding: 10px 18px; border-top: 1px solid var(--sm-line); }
        .sm-ui .sm-pager nav { display: flex; justify-content: flex-end; }
        .sm-ui .sm-pager .pagination { margin: 0; }

        /* Detail */
        .sm-ui .sm-detail { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); gap: 16px; align-items: start; }
        .sm-ui .sm-table th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .04em; color: var(--sm-muted); font-weight: 700; background: #fff; padding: 10px 18px; border-bottom: 1px solid var(--sm-line); }
        .sm-ui .sm-table td { padding: 10px 18px; font-size: 13px; vertical-align: middle; border-color: #F1F5F9; }
        .sm-ui .sm-side { position: sticky; top: 80px; }
        .sm-ui .sm-dl { display: grid; grid-template-columns: 92px minmax(0, 1fr); gap: 8px 12px; margin: 0; padding: 14px 18px; font-size: 13px; }
        .sm-ui .sm-dl dt { color: var(--sm-muted); font-weight: 600; }
        .sm-ui .sm-dl dd { margin: 0; color: var(--sm-ink); word-break: break-word; }
        .sm-ui .sm-dl dd small { color: var(--sm-muted); }
        .sm-ui .sm-frame-head { padding: 9px 18px; font-size: 12.5px; font-weight: 600; color: #334155; border-top: 1px solid var(--sm-line); display: flex; align-items: center; gap: 6px; }
        .sm-ui .sm-frame { background: #EEF0F6; }
        .sm-ui .sm-preview { display: block; width: 100%; height: 440px; border: 0; background: #EEF0F6; }
        .sm-ui .sm-side-foot { padding: 10px 18px; border-top: 1px solid var(--sm-line); background: var(--sm-soft); }

        @media (max-width: 1199.98px) {
            .sm-ui .sm-detail { grid-template-columns: minmax(0, 1fr); }
            .sm-ui .sm-side { position: static; }
        }
        @media (max-width: 767.98px) {
            .sm-ui .sm-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .sm-ui .sm-item { flex-wrap: wrap; }
            .sm-ui .sm-item-counts { width: 100%; align-items: flex-start; padding-left: 54px; }
            .sm-ui .sm-item-badges { justify-content: flex-start; }
            .sm-ui .sm-item-arrow { display: none; }
            .sm-ui .sm-search { max-width: none; }
        }
    </style>
</div>
