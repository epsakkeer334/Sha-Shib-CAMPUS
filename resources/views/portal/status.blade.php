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
        /* title left, ER number card right — side by side down to phone width */
        .st-head { display: grid; grid-template-columns: minmax(0, 1fr) clamp(250px, 30vw, 340px); align-items: end; gap: 16px 24px; }
        .st-head-side { display: flex; flex-direction: column; align-items: stretch; gap: 10px; min-width: 0; }
        .st-head-side .badge { white-space: normal; height: auto !important; min-height: 38px; padding: 6px 14px !important; text-align: center; line-height: 1.3; }
        .er-card { border-radius: 18px; padding: 16px 20px; display: flex; flex-direction: column; gap: 4px; color: #fff;
            background: rgba(255, 255, 255, .09); -webkit-backdrop-filter: blur(14px) saturate(140%); backdrop-filter: blur(14px) saturate(140%);
            border: 1px solid rgba(255, 255, 255, .20); box-shadow: 0 12px 30px rgba(14, 13, 40, .25); }
        .er-card .er-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .er-card.issued { background: linear-gradient(135deg, rgba(242, 181, 68, .22), rgba(255, 255, 255, .08)); border-color: rgba(242, 181, 68, .5); }
        @media (max-width: 1000px) { .st-cols { grid-template-columns: minmax(0, 1fr); } }
        @media (max-width: 640px) { .st-head { grid-template-columns: minmax(0, 1fr); } }
        @media (max-width: 560px) { .st-alert { grid-template-columns: auto minmax(0, 1fr); } .st-alert > .btn { grid-column: 1 / -1; } }
    </style>

    <header class="page-head rise st-head">
        <div style="display: flex; flex-direction: column; gap: 8px; min-width: 0;">
            <span class="eyebrow">Application status</span>
            <h1 class="title">{{ $headline }}</h1>
            <p class="lead">{{ optional($student->course)->name }}@if($student->batch) · batch <span class="mono" style="font-weight: 600;">{{ $student->batch->code }}</span>@endif @if($student->current_period_label) · <span style="font-weight: 600;">{{ $student->current_period_label }}</span>@endif @if($student->submitted_at) · submitted {{ $student->submitted_at->format('d M Y') }}@endif</p>
        </div>
        <div class="st-head-side">
        <section class="er-card {{ $student->er_number ? 'issued' : '' }}" aria-label="ER number">
            <span class="er-top"><span class="eyebrow">ER number</span>
                <span class="badge {{ $student->er_number ? 'b-ok' : '' }}" style="height: 22px; font-size: 11.5px; {{ $student->er_number ? '' : 'background: rgba(255, 255, 255, .12); color: rgba(255, 255, 255, .85);' }}">{{ $student->er_number ? 'Issued' : 'Pending' }}</span></span>
            @if($student->er_number)
                <span class="mono" style="font-size: 26px; line-height: 34px; font-weight: 500;">{{ $student->er_number }}</span>
                <span style="font-size: 13px; line-height: 19px; color: rgba(255, 255, 255, .82);">Use this number for all college matters.</span>
            @else
                <span class="mono" style="font-size: 20px; line-height: 30px; color: rgba(255, 255, 255, .6);">Not issued yet</span>
                <span style="font-size: 13px; line-height: 19px; color: rgba(255, 255, 255, .82);">Sent to you by message once both checks are approved.</span>
            @endif
        </section>
        @if($student->onboarding_deadline && !$student->er_number)
            <span class="badge" style="height: 38px; justify-content: center; padding: 0 16px; font-size: 13.5px; background: {{ $days >= 0 ? 'rgba(242, 181, 68, .16)' : 'rgba(248, 113, 113, .18)' }}; border: 1px solid {{ $days >= 0 ? 'rgba(242, 181, 68, .42)' : 'rgba(248, 113, 113, .45)' }}; color: {{ $days >= 0 ? '#F7CF7E' : '#FECACA' }};">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="7.5"></circle><path d="M10 6 V10 L12.5 11.5"></path></svg>
                Complete by {{ $student->formatted_onboarding_deadline }} · @if($days >= 0){{ $days }} days left @else{{ abs($days) }} days overdue @endif
            </span>
        @endif
        </div>
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

        {{-- Add-on programs chosen (step 2.10) --}}
        @php $addonChoices = $student->addonChoices()->with('program')->get()->filter(fn ($c) => $c->program); @endphp
        @if($addonChoices->isNotEmpty())
            <section class="card" style="gap: 12px; padding-top: 20px; padding-bottom: 20px;">
                <div class="row-between">
                    <h2>Your add-on programs</h2>
                    @if(\App\Support\PortalProgress::canEdit($student))<a href="{{ route('portal.details') }}#addons" style="font-size: 14px; font-weight: 700;">Change</a>@endif
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                    @foreach($addonChoices as $choice)
                        <span class="badge b-info" style="height: 32px; padding: 0 14px; font-size: 13.5px;">{{ $choice->program->name }} <span style="opacity: .75; font-weight: 600;">· {{ $choice->program->feeLabel() }}</span></span>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="st-cols">
            {{-- Documents --}}
            <section class="card" style="gap: 8px;">
                <div class="row-between" style="margin-bottom: 6px;"><h2>Your documents</h2><a href="{{ route('portal.documents') }}" style="font-size: 14px; font-weight: 700;">Manage documents</a></div>
                @foreach($required as $type => [$label])
                    @php [$cls, $text] = $docBadge($latestDocs->get($type)); @endphp
                    <div class="lrow"><span>{{ $label }}</span><span class="badge {{ $cls }}">{{ $text }}</span></div>
                @endforeach
            </section>

            @include('portal.partials.fee-summary', ['student' => $student])
        </div>
    </div>
</main>
