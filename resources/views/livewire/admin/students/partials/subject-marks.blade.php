{{-- 12th marks per subject + live eligibility check. Params: entry (EligibilityService::subjectsToEnter), check (EligibilityService::check|null), markType --}}
@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $rows = collect($check['subjects'] ?? [])->keyBy('id');
@endphp
@if($entry['subjects'])
    <div class="mt-3">
        <label class="form-label fw-medium small d-block">
            Marks per subject @if($entry['required'])<span class="text-danger">*</span>@endif
            <span class="text-muted fw-normal">({{ $markType === 'cgpa' ? 'CGPA, 0–10' : '%, 0–100' }}{{ $entry['required'] ? '' : ' · optional: no criteria for this stream' }})</span>
        </label>
        <div class="el-marks">
            @foreach($entry['subjects'] as $subject)
                @php $row = $rows->get($subject['id']); $state = $row && $row['percent'] !== null && $subject['min'] !== null ? ($row['ok'] ? 'is-ok' : 'is-bad') : ''; @endphp
                <div class="el-mark {{ $state }}">
                    <label class="el-mark-name" for="sm_{{ $subject['id'] }}">
                        <span>{{ $subject['name'] }}</span>
                        @if($subject['min'] !== null)<span class="text-muted fw-normal">min {{ $fmt($subject['min']) }}%</span>@endif
                    </label>
                    <input id="sm_{{ $subject['id'] }}" type="number" step="0.01" class="form-control @error('subject_marks.' . $subject['id']) is-invalid @enderror"
                           wire:model.lazy="subject_marks.{{ $subject['id'] }}" placeholder="{{ $markType === 'cgpa' ? '0 – 10' : '0 – 100' }}">
                    @error('subject_marks.' . $subject['id'])
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @elseif($state)
                        <div class="el-mark-result">
                            <i class="ti ti-{{ $row['ok'] ? 'circle-check' : 'circle-x' }}"></i>
                            {{ $fmt($row['percent']) }}%{{ $markType === 'cgpa' ? ' (converted)' : '' }}{{ $row['ok'] ? '' : ' — needs ' . $fmt($subject['min']) . '%' }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif

@if($check && $check['status'] !== 'not_configured')
    @php $complete = collect($check['subjects'])->every(fn ($s) => $s['percent'] !== null); @endphp
    @if($check['status'] === 'eligible')
        <div class="so-alert so-alert-ok mt-3"><i class="ti ti-circle-check"></i>
            <div><strong>Meets the course eligibility criteria.</strong>@if($check['aggregate'] !== null) Average {{ $fmt($check['aggregate']) }}%.@endif</div></div>
    @elseif($complete || !$check['criterion_id'])
        <div class="so-alert so-alert-bad mt-3"><i class="ti ti-alert-octagon"></i>
            <div><strong>Not eligible for this course.</strong> {{ implode(' ', $check['reasons']) }}
                <div class="small mt-1">Details can be saved, but the application cannot be submitted unless an admin allows it with a reason (Review tab).</div></div></div>
    @endif
@endif
