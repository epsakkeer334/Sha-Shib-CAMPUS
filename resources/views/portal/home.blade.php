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
        .home-band { padding-bottom: 8px; }
        .how { max-width: 1200px; margin: 0 auto; width: 100%; padding: 80px 24px 24px; display: flex; flex-direction: column; gap: 28px; scroll-margin-top: 72px; }
        .sec-head { display: flex; flex-direction: column; gap: 6px; max-width: 640px; }
        .sec-head .eyebrow { color: var(--brand); }
        .sec-head h2 { font-size: clamp(26px, 3vw, 36px); line-height: 1.2; font-weight: 800; letter-spacing: -.02em; color: var(--ink); text-wrap: balance; }
        .sec-head p { margin: 0; color: var(--ink-2); font-size: 16px; line-height: 25px; }
        .how ol .card { position: relative; overflow: hidden; }
        .how ol .card::after { content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: var(--grad); transform: scaleX(0); transform-origin: left; transition: transform .35s var(--ease); }
        .how ol .card:hover::after { transform: scaleX(1); }
        .after { max-width: 1200px; margin: 0 auto; width: 100%; padding: 56px 24px; display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 56px; align-items: center; }
        .after-photo { position: relative; border-radius: 24px; overflow: hidden; aspect-ratio: 4 / 3; box-shadow: var(--shadow-lg); }
        .after-photo img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .8s var(--ease); }
        .after-photo:hover img { transform: scale(1.04); }
        .after-photo .tag { position: absolute; left: 18px; bottom: 18px; display: inline-flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 14px; background: rgba(255, 255, 255, .95); box-shadow: var(--shadow); font-weight: 700; font-size: 14px; }
        .after-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 14px; }
        .after-list li { display: grid; grid-template-columns: 44px minmax(0, 1fr); gap: 14px; align-items: start; padding: 16px; border-radius: 16px; background: #fff; border: 1px solid var(--glass-line); box-shadow: var(--shadow); transition: transform .2s ease, box-shadow .2s ease; }
        .after-list li:hover { transform: translateX(4px); box-shadow: var(--shadow-lg); }
        .after-list .ico { width: 44px; height: 44px; border-radius: 12px; background: var(--brand-soft); color: var(--brand); display: flex; align-items: center; justify-content: center; }
        .after-list b { display: block; font-size: 16px; }
        .after-list span { color: var(--ink-2); font-size: 14.5px; }
        .cta-wrap { max-width: 1200px; margin: 0 auto; width: 100%; padding: 24px 24px 88px; }
        .cta { --band-img: url('{{ asset('portal/graduation.jpg') }}'); border-radius: 28px; overflow: hidden; isolation: isolate; padding: 56px 48px; display: flex; justify-content: space-between; align-items: center; gap: 24px; flex-wrap: wrap; box-shadow: var(--shadow-lg); }
        .cta::before { background-position: center 30% !important; }
        .cta h2 { font-size: clamp(26px, 3.2vw, 38px); line-height: 1.15; font-weight: 800; letter-spacing: -.02em; }
        .cta p { margin: 8px 0 0; color: rgba(255, 255, 255, .84); font-size: 16px; max-width: 520px; }
        .how ol { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(240px, 100%), 1fr)); gap: 18px; }
        .how .num { width: 44px; height: 44px; border-radius: 14px; background: var(--grad); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; box-shadow: 0 8px 18px rgba(59, 79, 224, .35); }
        @media (max-width: 960px) { .hero { grid-template-columns: minmax(0, 1fr); padding-top: 56px; gap: 36px; } .after { grid-template-columns: minmax(0, 1fr); gap: 32px; } }
        @media (max-width: 600px) {
            .hero { padding: 40px 16px 48px; } .how { padding: 48px 16px 16px; } .after { padding: 40px 16px; } .cta-wrap { padding: 16px 16px 56px; } .cta { padding: 36px 24px; border-radius: 22px; }
            .how ol { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
            .how ol .card { padding: 16px !important; gap: 8px !important; }
            .how ol .card > span:last-child { font-size: 13px; line-height: 19px; }
            .how .num { width: 36px; height: 36px; border-radius: 11px; }
            .how ol .card > span:nth-child(2) { font-size: 15.5px !important; }
        }
    </style>

    <div class="campus-band home-band">
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
    </div>

    <section class="how" id="how">
        <div class="sec-head">
            <span class="eyebrow">How admission works</span>
            <h2>Four steps from you, the rest from us</h2>
            <p>Everything is saved as you go, so you can stop and continue on any device.</p>
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
    </section>

    <section class="after">
        <div class="after-photo rise">
            <img src="{{ asset('portal/students.jpg') }}" alt="Students working together in the college library" loading="lazy" width="1200" height="800">
            <span class="tag"><svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="#1F9D62" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="7.5"></circle><path d="M6.8 10.2 L9 12.4 L13.4 7.8"></path></svg>Verified by our admissions team</span>
        </div>
        <div style="display: flex; flex-direction: column; gap: 22px;">
            <div class="sec-head">
                <span class="eyebrow">After you apply</span>
                <h2>We verify and issue your ER number</h2>
                <p>Follow every check live from your application page — you also get a message at each step.</p>
            </div>
            <ul class="after-list stagger">
                @foreach([
                    ['doc', 'Document check', 'Our team reviews your photo, marksheets and medical certificate.'],
                    ['card', 'Fee check', 'Each payment is confirmed by the Accounts office, with a receipt.'],
                    ['people', 'ER number & ID card', 'Once both checks pass, you get your ER number and student ID card.'],
                ] as [$i, $head, $text])
                    <li><span class="ico"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon[$i] !!}</svg></span><span style="display: block;"><b>{{ $head }}</b><span>{{ $text }}</span></span></li>
                @endforeach
            </ul>
        </div>
    </section>

    <div class="cta-wrap">
        <section class="cta campus-band rise">
            <div>
                <h2>{{ $admissionsOpen ? 'Ready to begin your journey?' : 'Already started an application?' }}</h2>
                <p>{{ $admissionsOpen ? 'Register online in four simple steps — keep your documents ready and apply today.' : 'Sign in to continue, upload documents, pay fees and follow your verification.' }}</p>
            </div>
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                @if($admissionsOpen)<a href="{{ route('portal.register') }}" class="btn btn-primary" style="min-height: 54px; padding: 0 28px;">Start your application</a>@endif
                <a href="{{ route('portal.login') }}" class="btn btn-ghost" style="min-height: 54px;">Sign in</a>
            </div>
        </section>
    </div>
</main>
