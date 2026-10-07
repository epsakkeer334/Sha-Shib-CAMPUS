@php
    $check = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8.5 L6.5 12 L13 4.5"></path></svg>';
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
@endphp
<main class="main">
    <div style="display: flex; flex-direction: column; gap: 8px;">
        <span class="eyebrow">Application status</span>
        <h1 class="title">{{ $headline }}</h1>
        <p class="lead">{{ optional($student->course)->name }}@if($student->batch) · batch <span class="mono" style="font-weight: 600;">{{ $student->batch->code }}</span>@endif @if($student->submitted_at) · submitted {{ $student->submitted_at->format('d M Y') }}@endif</p>
    </div>

    {{-- Action needed --}}
    @if($student->status === 'draft')
        <section class="notice info">
            <div style="display: flex; flex-direction: column; gap: 4px;"><strong>Your application is not submitted yet</strong><span>Next: {{ $nextStep[1] }}.</span></div>
            <a href="{{ route($nextStep[0]) }}" class="btn btn-primary btn-sm">Continue</a>
        </section>
    @endif

    @if($student->status === 'rejected')
        <section class="notice danger">
            <div style="display: flex; flex-direction: column; gap: 4px; max-width: 560px;">
                <strong>Action needed: your application was returned for corrections</strong>
                @if(optional($docGate)->status === 'rejected' && $docGate->remarks)<span>“{{ $docGate->remarks }}”</span>@endif
            </div>
            <a href="{{ route('portal.details') }}" class="btn btn-danger btn-sm">Make corrections</a>
        </section>
    @endif

    @foreach($rejectedDocs as $doc)
        <section class="notice danger">
            <div style="display: flex; flex-direction: column; gap: 4px; max-width: 560px;">
                <strong>Action needed: re-upload your {{ $doc->type_label }}</strong>
                @if($doc->remarks)<span>“{{ $doc->remarks }}”</span>@endif
            </div>
            <a href="{{ route('portal.documents') }}" class="btn btn-danger btn-sm">Re-upload</a>
        </section>
    @endforeach

    @include('portal.partials.fee-summary', ['student' => $student])

    {{-- Progress --}}
    <section class="card" style="gap: 4px;">
        <div class="row-between" style="margin-bottom: 16px; align-items: baseline;">
            <h2>Progress</h2>
            @if($student->onboarding_deadline && !$student->er_number)
                <span style="font-size: 14px; color: var(--ink-2);">
                    Complete by <b>{{ $student->formatted_onboarding_deadline }}</b> ·
                    @if($days >= 0) {{ $days }} days left @else <span style="color: var(--danger);">{{ abs($days) }} days overdue</span> @endif
                </span>
            @endif
        </div>
        <ol class="timeline">
            <li>
                <span class="dot {{ $submitted ? 'done' : 'active' }}">{!! $submitted ? $check : '' !!}</span>
                <span style="display: flex; flex-direction: column; gap: 2px; padding-top: 5px;">
                    <span style="font-weight: 600;">Application submitted</span>
                    <span class="muted" style="font-size: 14px;">{{ $submitted ? 'Details, academics and documents received on ' . $student->submitted_at->format('d M') : 'Fill in your details, academics and documents, then submit.' }}</span>
                </span>
            </li>
            <li>
                <span class="dot {{ $docState }}">{!! $docState === 'done' ? $check : '' !!}</span>
                <span style="display: flex; flex-direction: column; gap: 2px; padding-top: 5px; {{ $docState === 'todo' ? 'color: var(--muted);' : '' }}">
                    <span style="font-weight: 600;">Document check
                        @if($docState === 'active')<span style="font-weight: 500; color: var(--info);">· in progress</span>@elseif($docState === 'problem')<span style="font-weight: 500; color: var(--danger);">· action needed</span>@endif
                    </span>
                    <span class="muted" style="font-size: 14px;">
                        Done by our admissions team. {{ $verifiedCount }} of {{ $required->count() }} checked{{ $rejectedDocs->count() ? ', ' . $rejectedDocs->count() . ' ' . ($rejectedDocs->count() === 1 ? 'needs' : 'need') . ' re-upload' : '' }}.
                    </span>
                </span>
            </li>
            <li>
                <span class="dot {{ $feeState }}">{!! $feeState === 'done' ? $check : '' !!}</span>
                <span style="display: flex; flex-direction: column; gap: 2px; padding-top: 5px; {{ $feeState === 'todo' ? 'color: var(--muted);' : '' }}">
                    <span style="font-weight: 600;">Fee check @if($feeState === 'active')<span style="font-weight: 500; color: var(--info);">· in progress</span>@endif</span>
                    <span class="muted" style="font-size: 14px;">
                        @if($feeState === 'done') All fees cleared.
                        @else
                            @if($pendingPayments->isNotEmpty()){{ money_inr($pendingPayments->sum('amount'), false) }} being confirmed by Accounts. @endif
                            @if($outstanding > 0){{ money_inr($outstanding, false) }} still to pay. @elseif($submitted && $student->dues->isEmpty()) Fees will be added by the Accounts office. @endif
                        @endif
                    </span>
                    @if($outstanding > 0 && \App\Support\PortalProgress::paymentUnlocked($student))<a href="{{ route('portal.payment') }}" style="font-size: 14px; font-weight: 600; margin-top: 4px;">Pay remaining fees</a>@endif
                </span>
            </li>
            <li>
                <span class="dot {{ $erState }}">{!! $erState === 'done' ? $check : '' !!}</span>
                <span style="display: flex; flex-direction: column; gap: 2px; padding-top: 5px; {{ $erState === 'todo' ? 'color: var(--muted);' : '' }}">
                    <span style="font-weight: 600; color: var(--ink-2);">ER number issued @if($student->er_number)<span class="mono" style="color: var(--ink);">· {{ $student->er_number }}</span>@endif</span>
                    <span style="font-size: 14px;">{{ $student->er_number ? 'Use this number for all college matters.' : 'You’ll get it here and by message once both checks are approved.' }}</span>
                </span>
            </li>
            <li>
                <span class="dot {{ $cardState }}">{!! $cardState === 'done' ? $check : '' !!}</span>
                <span style="display: flex; flex-direction: column; gap: 2px; padding-top: 5px; {{ $cardState === 'todo' ? 'color: var(--muted);' : '' }}">
                    <span style="font-weight: 600; color: var(--ink-2);">ID card ready</span>
                    <span style="font-size: 14px;">{{ $cardState === 'done' ? 'Issued on ' . $student->idCard->issue_date->format('d M Y') . '.' : 'Collect it from the office after it is signed.' }}</span>
                </span>
            </li>
        </ol>
    </section>

    {{-- Documents --}}
    <section class="card flush">
        <h2 style="padding: 22px 28px; border-bottom: 1px solid var(--line-2);">Your documents</h2>
        @foreach($required as $type => [$label])
            @php [$cls, $text] = $docBadge($latestDocs->get($type)); @endphp
            <div class="row-between" style="padding: 14px 28px; border-bottom: 1px solid var(--line-2);"><span>{{ $label }}</span><span class="badge {{ $cls }}">{{ $text }}</span></div>
        @endforeach
        <div style="padding: 14px 28px;"><a href="{{ route('portal.documents') }}" style="font-weight: 600;">Manage documents</a></div>
    </section>
</main>
