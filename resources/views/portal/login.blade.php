<div class="campus-band login-band" style="flex-grow: 1; display: flex;">
<main class="main wide">
    <style>
        .login-band { --band-img: url('{{ asset('portal/graduation.jpg') }}'); }
        .login-band::before { background-position: center 45% !important; }
        .split { display: grid; grid-template-columns: minmax(0, 1fr) 460px; gap: 64px; align-items: center; flex-grow: 1; padding: 32px 0; }
        .perk { border-radius: 14px; padding: 13px 16px; display: flex; gap: 14px; align-items: center; max-width: 440px; }
        .perk .ico { width: 34px; height: 34px; border-radius: 10px; background: rgba(143, 227, 184, .16); color: #8FE3B8; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .in-ico { position: relative; display: block; }
        .in-ico > svg { position: absolute; left: 15px; top: 16px; color: var(--muted); pointer-events: none; }
        .in-ico .input { padding-left: 44px; }
        @media (max-width: 1000px) { .split { grid-template-columns: minmax(0, 1fr); gap: 32px; } .split .hide-md { display: none; } }
    </style>
    <div class="split">
        <div class="rise" style="display: flex; flex-direction: column; gap: 20px; color: #fff;">
            <span class="badge" style="align-self: flex-start; height: 32px; padding: 0 14px; background: rgba(255, 255, 255, .12); border: 1px solid rgba(255, 255, 255, .28); color: #fff;">Student portal</span>
            <h1 class="title" style="font-size: clamp(34px, 4.4vw, 52px);">Welcome back</h1>
            <p class="lead hide-md">Continue your application, upload documents, pay fees and follow your verification.</p>
            <ul class="hide-md" style="margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 10px;">
                @foreach([
                    ['<path d="M5 10.5 L8.5 14 L15 6.5"></path>', 'Pick up exactly where you left off'],
                    ['<circle cx="10" cy="10" r="7.5"></circle><path d="M10 6 V10 L12.5 11.5"></path>', 'Live status of document and fee checks'],
                    ['<path d="M5 2.5 H15 V17.5 L12.5 16 L10 17.5 L7.5 16 L5 17.5 Z"></path><path d="M7.5 7 H12.5 M7.5 10 H12.5"></path>', 'Download every fee receipt'],
                ] as [$svg, $text])
                    <li class="perk glass-dark"><span class="ico"><svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $svg !!}</svg></span><span>{{ $text }}</span></li>
                @endforeach
            </ul>
        </div>

        <form class="card rise" style="animation-delay: .12s; border-radius: 26px; padding: 36px 32px; gap: 18px;" wire:submit.prevent="login" aria-labelledby="signin-title">
            <div style="display: flex; flex-direction: column; align-items: center; gap: 10px; text-align: center;">
                <img src="{{ asset('brand/logo-navy.png') }}" alt="Sha-Shib Campus" width="240" height="38" style="display: block; width: 240px; max-width: 100%; height: auto; margin-bottom: 6px;">
                <h2 id="signin-title" style="font-size: 23px; line-height: 30px;">Sign in</h2>
                <p class="muted" style="margin: 0; font-size: 14px;">Use the email you registered with.</p>
            </div>
            @if($errorMessage)
                <div class="notice danger" role="alert" style="padding: 12px 16px; border-radius: 12px;">{{ $errorMessage }}</div>
            @endif
            <label class="field" for="f_email">
                <span class="label">Email</span>
                <span class="in-ico">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="4.5" width="15" height="11" rx="2"></rect><path d="M3 5.5 L10 11 L17 5.5"></path></svg>
                    <input id="f_email" type="email" class="input @error('email') invalid @enderror" wire:model.lazy="email" autocomplete="username" required>
                </span>
                @error('email')<span class="error">{{ $message }}</span>@enderror
            </label>
            <label class="field" for="f_password">
                <span class="label">Password</span>
                <span class="in-ico">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4.5" y="9" width="11" height="8" rx="2"></rect><path d="M7 9 V6.5 A3 3 0 0 1 13 6.5 V9"></path></svg>
                    <input id="f_password" type="password" class="input @error('password') invalid @enderror" wire:model.defer="password" autocomplete="current-password" required>
                </span>
                @error('password')<span class="error">{{ $message }}</span>@enderror
            </label>
            <label style="display: flex; align-items: center; gap: 10px; min-height: 40px; cursor: pointer; font-size: 14.5px; color: var(--ink-2);">
                <input type="checkbox" wire:model.defer="remember"> <span>Keep me signed in on this device</span>
            </label>
            <button type="submit" class="btn btn-primary" style="width: 100%; min-height: 54px; font-size: 16px;" wire:loading.attr="disabled" wire:target="login" data-one-request>
                <span wire:loading.remove wire:target="login">Sign in</span>
                <span wire:loading wire:target="login"><span class="spin"></span> Signing in…</span>
            </button>
            <p class="muted" style="margin: 0; font-size: 13.5px; text-align: center;">
                @if(\App\Support\Settings::bool('portal.registration_open'))
                    New here? <a href="{{ route('portal.register') }}" style="font-weight: 800;">Start your application</a>.<br>
                @endif
                Forgot your password? Contact the admissions office.
            </p>
        </form>
    </div>
</main>
</div>
