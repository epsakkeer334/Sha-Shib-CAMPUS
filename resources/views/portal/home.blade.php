@php
    $admissionsOpen = \App\Support\Settings::bool('portal.registration_open');
    $brandName = config('camp.portal_name', config('app.name'));
    $icon = [
        'photo' => '<circle cx="10" cy="7" r="3.2"></circle><path d="M3.8 17 C5 13.8 7.3 12.4 10 12.4 S15 13.8 16.2 17"></path>',
        'doc' => '<path d="M5.5 2.5 H11.5 L15 6 V17.5 H5.5 Z"></path><path d="M11.5 2.5 V6 H15"></path><path d="M10.2 9 V14 M7.7 11.5 H12.7"></path>',
        'cap' => '<path d="M2.5 8 L10 4.5 L17.5 8 L10 11.5 Z"></path><path d="M5.5 9.5 V13 C6.9 14.4 8.4 15 10 15 S13.1 14.4 14.5 13 V9.5"></path>',
        'people' => '<circle cx="7.5" cy="7.5" r="2.7"></circle><path d="M2.5 16.5 C3.3 13.9 5.2 12.7 7.5 12.7 S11.7 13.9 12.5 16.5"></path><path d="M13 5.2 A2.6 2.6 0 0 1 13 10.2"></path><path d="M14.5 12.9 C16.2 13.3 17.3 14.5 17.8 16.5"></path>',
        'card' => '<rect x="2.5" y="5" width="15" height="10.5" rx="2"></rect><path d="M2.5 8.5 H17.5 M5.5 12.5 H8"></path>',
    ];
@endphp
<main style="flex-grow: 1; display: flex; flex-direction: column;">
    <style>
        .hero { max-width: 1200px; margin: 0 auto; width: 100%; padding: 88px 24px 72px; display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 56px; align-items: center; }
        .hero h1 { font-size: clamp(36px, 5vw, 60px); line-height: 1.08; font-weight: 800; letter-spacing: -.03em; color: #fff; text-wrap: balance; }
        .hero h1 .hl { background: linear-gradient(90deg, #F7CF7E, #F2B544); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .pill-gold { align-self: flex-start; display: inline-flex; align-items: center; gap: 8px; height: 32px; padding: 0 14px; border-radius: 999px; background: rgba(242, 181, 68, .16); border: 1px solid rgba(242, 181, 68, .42); color: #F7CF7E; font-size: 13px; font-weight: 700; }
        .pill-gold .dot-live { width: 8px; height: 8px; border-radius: 50%; background: #F2B544; box-shadow: 0 0 0 0 rgba(242, 181, 68, .6); animation: live 2s ease-out infinite; }
        .pill-closed { background: rgba(255, 255, 255, .12); border-color: rgba(255, 255, 255, .3); color: #fff; }
        .pill-closed .dot-live { background: #F87171; animation: none; }
        @keyframes live { 0% { box-shadow: 0 0 0 0 rgba(242, 181, 68, .6); } 70% { box-shadow: 0 0 0 9px rgba(242, 181, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(242, 181, 68, 0); } }
        .ready li { display: flex; gap: 14px; align-items: center; }
        .ready .ico { width: 36px; height: 36px; border-radius: 10px; background: var(--brand-soft); color: var(--brand); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .how { max-width: 1200px; margin: 0 auto; width: 100%; padding: 8px 24px 88px; display: flex; flex-direction: column; gap: 28px; }
        .how ol { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(240px, 100%), 1fr)); gap: 18px; }
        .how .num { width: 44px; height: 44px; border-radius: 14px; background: var(--grad); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; box-shadow: 0 8px 18px rgba(59, 79, 224, .35); }
        @media (max-width: 960px) { .hero { grid-template-columns: minmax(0, 1fr); padding-top: 56px; gap: 36px; } }
        @media (max-width: 600px) {
            .hero { padding: 40px 16px 48px; } .how { padding: 0 16px 56px; }
            .how ol { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
            .how ol .card { padding: 16px !important; gap: 8px !important; }
            .how ol .card > span:last-child { font-size: 13px; line-height: 19px; }
            .how .num { width: 36px; height: 36px; border-radius: 11px; }
            .how ol .card > span:nth-child(2) { font-size: 15.5px !important; }
        }
    </style>

    <section class="hero">
        <div class="rise" style="display: flex; flex-direction: column; gap: 22px; color: #fff;">
            @if($admissionsOpen)
                <span class="pill-gold"><span class="dot-live"></span>Admissions open · {{ now()->year }}–{{ substr(now()->year + 1, -2) }} batch</span>
            @else
                <span class="pill-gold pill-closed"><span class="dot-live"></span>Admissions closed</span>
            @endif
            <h1>Begin your journey at <span class="hl">{{ $brandName }}</span></h1>
            <p style="margin: 0; font-size: 18px; line-height: 29px; color: rgba(255, 255, 255, .86); max-width: 540px;">
                Apply for admission online in four simple steps — register, upload your documents and pay your fees from anywhere.
            </p>
            @unless($admissionsOpen)
                <p class="notice info" style="margin: 0; max-width: 540px;">{{ setting('portal.closed_message') }}</p>
            @endunless
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                @if($admissionsOpen)
                    <a href="{{ route('portal.register') }}" class="btn btn-primary" style="min-height: 56px; padding: 0 28px; font-size: 16px;">Start your application
                        <svg class="arrow" width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 10 H16 M11 5 L16 10 L11 15"></path></svg></a>
                @endif
                <a href="{{ route('portal.login') }}" class="btn {{ $admissionsOpen ? 'btn-ghost' : 'btn-primary' }}" style="min-height: 56px; padding: 0 24px; font-size: 16px;">Continue an application</a>
            </div>
            <div style="display: flex; gap: 22px; flex-wrap: wrap; font-size: 14px; color: rgba(255, 255, 255, .8);">
                <span style="display: inline-flex; align-items: center; gap: 8px;"><svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="#8FE3B8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 2.5 L16 5 V9.5 C16 13.3 13.4 16.1 10 17.5 C6.6 16.1 4 13.3 4 9.5 V5 Z"></path><path d="M7.5 10 L9.3 11.8 L12.7 8.3"></path></svg>Private, secure documents</span>
                <span style="display: inline-flex; align-items: center; gap: 8px;"><svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="#8FE3B8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="7.5"></circle><path d="M10 6 V10 L12.5 11.5"></path></svg>Save and continue anytime</span>
            </div>
        </div>

        <aside class="card rise" style="animation-delay: .15s; border-radius: 24px; padding: 30px;">
            <div style="display: flex; flex-direction: column; gap: 4px;">
                <span style="font-size: 12px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: var(--brand);">Before you begin</span>
                <h2 style="font-size: 21px;">Keep these ready</h2>
            </div>
            <ul class="ready" style="margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 12px;">
                @foreach([
                    ['photo', 'A recent passport-size photo'],
                    ['doc', 'Medical fitness certificate'],
                    ['cap', 'Class 10 and Class 12 marksheets'],
                    ['people', 'Parent or guardian contact details'],
                    ['card', 'A UPI app (GPay, PhonePe …) for fees'],
                ] as [$i, $item])
                    <li><span class="ico"><svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon[$i] !!}</svg></span><span>{{ $item }}</span></li>
                @endforeach
            </ul>
            <p style="margin: 0; padding: 11px 14px; border-radius: 12px; background: var(--bg); font-size: 13.5px; line-height: 20px; color: var(--ink-2);">JPG, PNG or PDF, up to 5 MB each. Finish within {{ config('camp.onboarding_days') }} days of your joining date.</p>
        </aside>
    </section>

    <section class="how">
        <div style="display: flex; flex-direction: column; gap: 6px; color: #fff;">
            <span class="eyebrow">How admission works</span>
            <h2 style="font-size: clamp(26px, 3vw, 34px); line-height: 1.2; font-weight: 800; letter-spacing: -.02em;">Four steps from you, the rest from us</h2>
        </div>
        <ol class="stagger">
            @foreach([
                ['Register', 'Create your login and add personal, address and parent details.'],
                ['Academic details', 'Your Class 10 and Class 12 boards, stream and marks.'],
                ['Upload documents', 'Photo, medical certificate and marksheets — kept private.'],
                ['Pay fees', 'By GPay / UPI or at the Accounts office, with a receipt each time.'],
            ] as $i => [$head, $text])
                <li class="card lift" style="gap: 12px; padding: 26px;">
                    <span class="num">{{ $i + 1 }}</span>
                    <span style="font-size: 18px; font-weight: 800;">{{ $head }}</span>
                    <span style="color: var(--ink-2);">{{ $text }}</span>
                </li>
            @endforeach
        </ol>
        <div class="glass-dark rise" style="border-radius: 22px; padding: 24px 28px; display: flex; justify-content: space-between; align-items: center; gap: 18px; flex-wrap: wrap; color: #fff;">
            <div style="display: flex; gap: 16px; align-items: center;">
                <span style="width: 48px; height: 48px; border-radius: 14px; background: rgba(242, 181, 68, .18); color: #F7CF7E; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><svg width="22" height="22" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="4.5" width="15" height="11" rx="2"></rect><circle cx="7" cy="9.5" r="1.8"></circle><path d="M4.8 13.2 C5.3 12 6 11.5 7 11.5 S8.7 12 9.2 13.2 M11.5 8.5 H15 M11.5 11.5 H14"></path></svg></span>
                <span style="display: flex; flex-direction: column;"><span style="font-size: 17px; font-weight: 800;">Then we verify and issue your ER number</span><span style="color: rgba(255, 255, 255, .8);">Our team checks your documents and fees, then prepares your student ID card.</span></span>
            </div>
            @if($admissionsOpen)<a href="{{ route('portal.register') }}" class="btn btn-primary">Apply now</a>@endif
        </div>
    </section>
</main>
