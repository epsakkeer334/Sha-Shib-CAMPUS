@php
    $check = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8.5 L6.5 12 L13 4.5"></path></svg>';
    $days = $student->days_to_deadline;
    $submitted = (bool) $student->submitted_at && $student->status !== 'draft';

    $docState = !$submitted ? 'todo' : (optional($docGate)->status === 'approved' ? 'done' : (optional($docGate)->status === 'rejected' || $rejectedDocs->isNotEmpty() ? 'problem' : 'active'));
    $feeState = !$submitted ? 'todo' : (optional($feeGate)->status === 'approved' ? 'done' : 'active');
    $erState = $student->er_number ? 'done' : 'todo';
    $cardState = optional($student->idCard)->tm_signature_status === 'physically_signed' ? 'done' : ($student->er_number ? 'active' : 'todo');

    $headline = match (true) {
        $student->status === 'active' => 'Welcome aboard, ' . $student->first_name,
        (bool) $student->er_number => 'Your ER number is ready, ' . $student->first_name,
        $student->status === 'rejected' => $student->first_name . ', your application needs corrections',
        $submitted => 'Hi ' . $student->first_name . ', we’re checking your application',
        default => 'Hi ' . $student->first_name . ', let’s finish your application',
    };
    $docBadge = fn ($doc) => !$doc ? ['b-muted', 'Not uploaded'] : match ($doc->verification_status) {
        'verified' => ['b-ok', 'Approved'],
        'rejected' => ['b-danger', 'Re-upload needed'],
        default => $submitted ? ['b-info', 'Being checked'] : ['b-muted', 'Uploaded'],
    };
    $feeText = $feeState === 'done' ? 'All fees cleared.' : trim(
        ($pendingPayments->isNotEmpty() ? money_inr($pendingPayments->sum('amount'), false) . ' being confirmed by Accounts. ' : '')
        . ($outstanding > 0 ? money_inr($outstanding, false) . ' still to pay.' : ($submitted && $student->dues->isEmpty() ? 'Fees will be added by the Accounts office.' : ''))
    );
    $track = [
        [$submitted ? 'done' : 'active', 'Submitted', $submitted ? 'Details and documents received · ' . $student->submitted_at->format('d M') : 'Fill in your details and documents, then submit.'],
        [$docState, 'Document check', 'By the admissions team · ' . $verifiedCount . ' of ' . $required->count() . ' checked' . ($rejectedDocs->count() ? ' · ' . $rejectedDocs->count() . ' to re-upload' : '')],
        [$feeState, 'Fee check', 'By Accounts · ' . ($feeText ?: 'after you submit')],
        [$erState, 'ER number', $student->er_number ? 'Issued · ' . $student->er_number : 'Issued once both checks are approved'],
        [$cardState, 'ID card', $cardState === 'done' ? 'Issued on ' . $student->idCard->issue_date->format('d M Y') : 'Collect from the office after it is signed'],
    ];
@endphp
<main class="main wide">
    <style>
        .st-cols { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr); gap: 22px; align-items: start; }
        .st-alert { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 16px; align-items: center; }
        .st-alert .ico { width: 44px; height: 44px; border-radius: 14px; display: flex; align-items: center; justify-content: center; }
        .notice.danger .ico { background: var(--danger-bg); color: #B42318; }
        .notice.info .ico { background: var(--brand-soft); color: var(--brand); }
        .er-card { border-radius: 22px; padding: 22px 24px; display: flex; flex-direction: column; gap: 6px; color: #fff; }
        @media (max-width: 1000px) { .st-cols { grid-template-columns: minmax(0, 1fr); } }
        @media (max-width: 560px) { .st-alert { grid-template-columns: auto minmax(0, 1fr); } .st-alert > .btn { grid-column: 1 / -1; } }
    </style>

    <header class="page-head rise" style="flex-direction: row; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap;">
        <div style="display: flex; flex-direction: column; gap: 8px; min-width: 0;">
            <span class="eyebrow">Application status</span>
            <h1 class="title">{{ $headline }}</h1>
            <p class="lead">{{ optional($student->course)->name }}@if($student->batch) · batch <span class="mono" style="font-weight: 600;">{{ $student->batch->code }}</span>@endif @if($student->current_period_label) · <span style="font-weight: 600;">{{ $student->current_period_label }}</span>@endif @if($student->submitted_at) · submitted {{ $student->submitted_at->format('d M Y') }}@endif</p>
        </div>
        @if($student->onboarding_deadline && !$student->er_number)
            <span class="badge" style="height: 38px; padding: 0 16px; font-size: 13.5px; background: {{ $days >= 0 ? 'rgba(242, 181, 68, .16)' : 'rgba(248, 113, 113, .18)' }}; border: 1px solid {{ $days >= 0 ? 'rgba(242, 181, 68, .42)' : 'rgba(248, 113, 113, .45)' }}; color: {{ $days >= 0 ? '#F7CF7E' : '#FECACA' }};">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="7.5"></circle><path d="M10 6 V10 L12.5 11.5"></path></svg>
                Complete by {{ $student->formatted_onboarding_deadline }} · @if($days >= 0){{ $days }} days left @else{{ abs($days) }} days overdue @endif
            </span>
        @endif
    </header>

    <div class="stagger" style="display: flex; flex-direction: column; gap: 22px;">
        {{-- Progress --}}
        <section class="card" aria-label="Progress">
            <ol class="track">
                @foreach($track as $i => [$state, $name, $text])
                    <li class="{{ $state }}">
                        <span class="bar"><span></span></span>
                        <span class="t-head"><span class="dot {{ $state }}">{!! $state === 'done' ? $check : ($state === 'todo' ? $i + 1 : '') !!}</span>{{ $name }}
                            @if($state === 'problem')<span class="badge b-danger" style="height: 22px; font-size: 11.5px;">Action needed</span>@endif
                        </span>
                        <span class="t-text">{{ $text }}</span>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Action needed --}}
        @if($student->status === 'draft')
            <section class="notice info st-alert">
                <span class="ico" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10 H16 M11 5 L16 10 L11 15"></path></svg></span>
                <span style="display: flex; flex-direction: column; gap: 2px;"><strong>Your application is not submitted yet</strong><span>Next: {{ $nextStep[1] }}.</span></span>
                <a href="{{ route($nextStep[0]) }}" class="btn btn-primary btn-sm">Continue</a>
            </section>
        @endif

        @if($student->status === 'rejected')
            <section class="notice danger st-alert" role="alert">
                <span class="ico" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.5"></circle><path d="M10 6.5 V10.5 M10 13.4 V13.5"></path></svg></span>
                <span style="display: flex; flex-direction: column; gap: 2px;">
                    <strong>Action needed: your application was returned for corrections</strong>
                    @if(optional($docGate)->status === 'rejected' && $docGate->remarks)<span>“{{ $docGate->remarks }}”</span>@endif
                </span>
                <a href="{{ route('portal.details') }}" class="btn btn-danger btn-sm">Make corrections</a>
            </section>
        @endif

        @foreach($rejectedDocs as $doc)
            <section class="notice danger st-alert" role="alert">
                <span class="ico" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.5"></circle><path d="M10 6.5 V10.5 M10 13.4 V13.5"></path></svg></span>
                <span style="display: flex; flex-direction: column; gap: 2px;">
                    <strong>Action needed: re-upload your {{ $doc->type_label }}</strong>
                    @if($doc->remarks)<span>“{{ $doc->remarks }}”</span>@endif
                </span>
                <a href="{{ route('portal.documents') }}" class="btn btn-danger btn-sm">Re-upload</a>
            </section>
        @endforeach

        <div class="st-cols">
            {{-- Documents --}}
            <section class="card" style="gap: 8px;">
                <div class="row-between" style="margin-bottom: 6px;"><h2>Your documents</h2><a href="{{ route('portal.documents') }}" style="font-size: 14px; font-weight: 700;">Manage documents</a></div>
                @foreach($required as $type => [$label])
                    @php [$cls, $text] = $docBadge($latestDocs->get($type)); @endphp
                    <div class="lrow"><span>{{ $label }}</span><span class="badge {{ $cls }}">{{ $text }}</span></div>
                @endforeach
            </section>

            <div style="display: flex; flex-direction: column; gap: 22px;">
                @include('portal.partials.fee-summary', ['student' => $student])

                <section class="er-card glass-dark">
                    <span class="eyebrow">ER number</span>
                    @if($student->er_number)
                        <span class="mono" style="font-size: 26px; line-height: 34px; font-weight: 500;">{{ $student->er_number }}</span>
                        <span style="font-size: 14px; color: rgba(255, 255, 255, .82);">Use this number for all college matters.</span>
                    @else
                        <span class="mono" style="font-size: 22px; line-height: 32px; color: rgba(255, 255, 255, .55);">Not issued yet</span>
                        <span style="font-size: 14px; color: rgba(255, 255, 255, .82);">It appears here and is sent to you by message once both checks are approved.</span>
                    @endif
                </section>
            </div>
        </div>
    </div>
</main>
