{{-- One notification row. Params: $n (DatabaseNotification), $compact (bool) --}}
@php
    $d = $n->data;
    $unread = is_null($n->read_at);
@endphp
<div class="nt-item {{ $unread ? 'is-unread' : '' }} {{ !empty($compact) ? 'nt-compact' : '' }}">
    <span class="nt-icon nt-tone-{{ $d['tone'] ?? 'indigo' }}"><i class="{{ $d['icon'] ?? 'ti ti-bell' }}"></i></span>
    <div class="min-w-0 flex-grow-1">
        <div class="d-flex justify-content-between align-items-start gap-2">
            <span class="nt-title">{{ $d['title'] ?? 'Notification' }}</span>
            @if($unread)<span class="nt-dot" title="Unread"></span>@endif
        </div>
        <div class="nt-message">{{ $d['message'] ?? '' }}</div>
        <div class="nt-meta">
            @if(!empty($d['stage_label']))<span class="nt-stage nt-tone-{{ $d['tone'] ?? 'indigo' }}">{{ $d['stage_label'] }}</span>@endif
            @if(!empty($d['student_name']) && ($d['audience'] ?? 'staff') === 'staff')
                <span class="nt-student"><i class="ti ti-user"></i> {{ $d['student_name'] }}@if(!empty($d['er_number'])) · {{ $d['er_number'] }}@endif</span>
            @endif
            <span title="{{ $n->created_at->format('d M Y, h:i A') }}"><i class="ti ti-clock"></i> {{ $n->created_at->diffForHumans() }}</span>
            @if(empty($compact) && !empty($d['actor_name']))<span><i class="ti ti-user-check"></i> by {{ $d['actor_name'] }}</span>@endif
        </div>
    </div>
</div>
