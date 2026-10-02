@php $tick = '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="#0E6B55" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink: 0; margin-top: 1px;"><path d="M4 10.5 L8 14 L16 5"></path></svg>'; @endphp
<main style="flex-grow: 1; display: flex; flex-direction: column;">
    <section style="background: #fff; border-bottom: 1px solid var(--line);">
        <div style="max-width: 1120px; margin: 0 auto; padding: 72px 24px; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(420px, 100%), 1fr)); gap: 48px; align-items: center;">
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <span class="badge b-ok" style="align-self: flex-start; height: 28px; font-weight: 600;">Admissions open · {{ now()->year }} batch</span>
                <h1 class="serif" style="font-size: 48px; line-height: 56px; font-weight: 600; text-wrap: balance;">Apply for admission online</h1>
                <p style="margin: 0; font-size: 18px; line-height: 28px; color: var(--ink-2); max-width: 560px;">
                    Fill in your details, upload your documents and pay your fees from home. Our admissions team verifies everything and issues your ER number.
                </p>
                <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <a href="{{ route('portal.register') }}" class="btn btn-primary">Start your application</a>
                    <a href="{{ route('portal.login') }}" class="btn btn-secondary">Continue an application</a>
                </div>
            </div>
            <aside style="background: var(--bg); border: 1px solid var(--line); border-radius: 16px; padding: 28px; display: flex; flex-direction: column; gap: 16px;">
                <h2 style="font-size: 18px; font-weight: 600;">Keep these ready</h2>
                <ul style="margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 12px;">
                    @foreach(['A recent passport-size photo', 'Medical fitness certificate', 'Class 10 and Class 12 marksheets', 'Parent or guardian contact details', 'A UPI app (GPay, PhonePe …) for fees'] as $item)
                        <li style="display: flex; gap: 12px; align-items: flex-start;">{!! $tick !!}<span>{{ $item }}</span></li>
                    @endforeach
                </ul>
                <p style="margin: 0; font-size: 13px;" class="muted">JPG, PNG or PDF, up to 5 MB each. You can save and come back any time.</p>
            </aside>
        </div>
    </section>
    <section style="max-width: 1120px; margin: 0 auto; padding: 64px 24px; width: 100%; display: flex; flex-direction: column; gap: 32px;">
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <h2 class="serif" style="font-size: 32px; line-height: 40px; font-weight: 600;">How it works</h2>
            <p class="lead">Complete your part within {{ config('camp.onboarding_days') }} days of your joining date.</p>
        </div>
        <ol style="list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(230px, 100%), 1fr)); gap: 16px;">
            @foreach([
                ['01 · You', 'Register', 'Create your login and fill in personal, address, parent and academic details.'],
                ['02 · You', 'Upload documents', 'Photo, medical certificate and marksheets. Files are kept private.'],
                ['03 · You', 'Pay fees', 'Pay by GPay / UPI, or at the Accounts office. Get a receipt for each payment.'],
            ] as [$tag, $head, $text])
                <li style="background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 24px; display: flex; flex-direction: column; gap: 10px;">
                    <span class="mono" style="font-size: 13px; color: var(--brand); font-weight: 500;">{{ $tag }}</span>
                    <span style="font-size: 18px; font-weight: 600;">{{ $head }}</span>
                    <span style="color: var(--ink-2);">{{ $text }}</span>
                </li>
            @endforeach
            <li style="background: var(--ink); color: #fff; border-radius: 14px; padding: 24px; display: flex; flex-direction: column; gap: 10px;">
                <span class="mono" style="font-size: 13px; color: #8FD3BD; font-weight: 500;">04 · Our team</span>
                <span style="font-size: 18px; font-weight: 600;">Verification &amp; ER number</span>
                <span style="color: #C9D1CB;">We verify documents and fees, issue your ER number and prepare your ID card.</span>
            </li>
        </ol>
    </section>
</main>
