{{-- Application sidebar: progress, the four steps, fees and help.
     Params: $current (details|academic|documents|payment), $student (nullable), $fees = true (show the fee summary) --}}
@php
    $stepDefs = [
        'details' => ['Your details', 'Personal, address, parent', $student ? route('portal.details') : null],
        'academic' => ['Academic', 'Class 10 & 12 marks', $student ? route('portal.academic') : null],
        'documents' => ['Documents', 'Photo, certificate, marksheets', $student ? route('portal.documents') : null],
        'payment' => ['Payment', 'UPI or at the office', $student && \App\Support\PortalProgress::paymentUnlocked($student) ? route('portal.payment') : null],
    ];
    $doneMap = [
        'details' => (bool) $student,
        'academic' => $student && $student->academicDetail()->exists(),
        'documents' => $student && $student->submitted_at && !$student->missingRequiredDocuments(),
        'payment' => $student && $student->dues()->exists() && $student->outstandingAmount() <= 0,
    ];
    $percent = (int) round(collect($doneMap)->filter()->count() / count($doneMap) * 100);
    $tickSvg = '<svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 10.5 L8.5 14 L15 6.5"></path></svg>';
    $helpInstitute = optional($student)->institute;
@endphp
<aside class="flow-side" aria-label="Application steps">
    <div class="card">
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <div class="row-between" style="align-items: baseline;"><b style="font-weight: 800;">Your progress</b><span class="mono" style="font-weight: 600; color: var(--brand);">{{ $percent }}%</span></div>
            <div class="progress" role="progressbar" aria-label="Application progress" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ $percent }}%;"></span></div>
        </div>
        <ol class="steps">
            @foreach($stepDefs as $key => [$name, $sub, $url])
                @php $state = $key === $current ? 'current' : ($doneMap[$key] ? 'done' : 'todo'); @endphp
                <li class="{{ $state }}" @if($state === 'current') aria-current="step" @endif>
                    @if($url && $state !== 'current')<a href="{{ $url }}">@else<div>@endif
                        <span class="num">{!! $state === 'done' ? $tickSvg : $loop->iteration !!}</span>
                        <span style="min-width: 0;">
                            <span class="name">{{ $name }}</span>
                            <span class="tag">{{ match (true) {
                                $state === 'current' => 'In progress',
                                $state === 'done' => 'Completed' . ($url ? ' · Edit' : ''),
                                $key === 'payment' && $student && !$url => 'After documents',
                                default => $sub,
                            } }}</span>
                        </span>
                    @if($url && $state !== 'current')</a>@else</div>@endif
                </li>
            @endforeach
        </ol>
        @if($helpInstitute && ($helpInstitute->phone || $helpInstitute->email))
            <div class="side-help">
                <span class="ico" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.5"></circle><path d="M7.8 7.6 A2.3 2.3 0 1 1 10.6 9.9 C10.1 10.1 10 10.5 10 11 M10 13.6 V13.7"></path></svg></span>
                <span><b>Need help?</b><br><span class="muted">Admissions desk{{ $helpInstitute->phone ? ' · ' . $helpInstitute->phone : '' }}{{ $helpInstitute->email ? ' · ' . $helpInstitute->email : '' }}</span></span>
            </div>
        @endif
    </div>
    @if($student && ($fees ?? true))
        <div class="fee-side">@include('portal.partials.fee-summary', ['student' => $student])</div>
    @endif
</aside>
