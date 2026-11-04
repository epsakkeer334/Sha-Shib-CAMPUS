<div class="gl-page">
    <style>
        .gl-page {
            --gl-indigo: #F26522;
            --gl-indigo-2: #F26522;
            --gl-ink: #1D1C4C;
            --gl-muted: #6b7091;
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
            overflow: hidden;
            background: radial-gradient(1200px 700px at 10% 10%, #2D2B7F 0%, transparent 60%),
                        radial-gradient(900px 600px at 95% 90%, rgba(242, 101, 34, .30) 0%, transparent 60%),
                        linear-gradient(135deg, #14133A 0%, #1D1C4C 50%, #15143C 100%);
            font-family: inherit;
        }
        /* Soft floating colour blobs behind the glass */
        .gl-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(70px);
            opacity: .55;
            animation: gl-float 18s ease-in-out infinite;
            pointer-events: none;
        }
        .gl-blob.b1 { width: 420px; height: 420px; background: #4644B8; top: -120px; left: -80px; }
        .gl-blob.b2 { width: 360px; height: 360px; background: #F26522; bottom: -120px; right: -60px; animation-delay: -6s; opacity: .35; }
        .gl-blob.b3 { width: 300px; height: 300px; background: #F7941D; top: 45%; left: 42%; animation-delay: -12s; opacity: .25; }
        @keyframes gl-float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(40px, -30px) scale(1.08); }
            66% { transform: translate(-30px, 25px) scale(.95); }
        }
        .gl-grid {
            position: absolute;
            inset: 0;
            background-image: linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
            background-size: 44px 44px;
            mask-image: radial-gradient(ellipse at center, #000 30%, transparent 75%);
            -webkit-mask-image: radial-gradient(ellipse at center, #000 30%, transparent 75%);
            pointer-events: none;
        }

        .gl-shell {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1040px;
            display: grid;
            grid-template-columns: 1.05fr 1fr;
            border-radius: 28px;
            overflow: hidden;
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .14);
            box-shadow: 0 30px 80px rgba(5, 6, 30, .55), inset 0 1px 0 rgba(255,255,255,.12);
            backdrop-filter: blur(22px) saturate(140%);
            -webkit-backdrop-filter: blur(22px) saturate(140%);
        }

        /* Left: brand panel */
        .gl-brand {
            padding: 48px 44px;
            color: #e9e8ff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 32px;
        }
        .gl-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            letter-spacing: .04em;
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.18);
            color: #fff;
        }
        .gl-chip .dot { width: 7px; height: 7px; border-radius: 50%; background: #3ee6a0; box-shadow: 0 0 0 4px rgba(62,230,160,.18); }
        .gl-brand h1 {
            color: #fff;
            font-size: 34px;
            line-height: 1.2;
            font-weight: 700;
            margin: 20px 0 12px;
        }
        .gl-brand h1 span {
            background: linear-gradient(90deg, #FBB866, #F26522);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .gl-brand p.lead-sm { color: rgba(233,232,255,.75); font-size: 15px; margin: 0; max-width: 380px; }
        .gl-feats { list-style: none; padding: 0; margin: 0; display: grid; gap: 12px; }
        .gl-feats li {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 14px;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.1);
            font-size: 14px;
            color: #eceaff;
        }
        .gl-feats i {
            width: 34px; height: 34px;
            display: grid; place-items: center;
            border-radius: 10px;
            font-size: 18px;
            background: linear-gradient(135deg, rgba(247,148,29,.85), rgba(222,74,27,.7));
            color: #fff;
            flex: none;
        }
        .gl-brand small.copy { color: rgba(233,232,255,.55); font-size: 12px; }

        /* Right: frosted-glass form card */
        .gl-card {
            position: relative;
            margin: 14px;
            padding: 38px 36px 32px;
            border-radius: 22px;
            color: #fff;
            background: linear-gradient(160deg, rgba(255,255,255,.20) 0%, rgba(255,255,255,.07) 55%, rgba(255,255,255,.04) 100%);
            border: 1px solid rgba(255,255,255,.26);
            box-shadow: 0 24px 60px rgba(5, 6, 30, .45), inset 0 1px 0 rgba(255,255,255,.35), inset 0 -1px 0 rgba(255,255,255,.06);
            backdrop-filter: blur(26px) saturate(160%);
            -webkit-backdrop-filter: blur(26px) saturate(160%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow: hidden;
            isolation: isolate;
        }
        /* light sheen across the top edge of the glass */
        .gl-card::before {
            content: "";
            position: absolute;
            inset: -30% -30% auto -30%;
            height: 75%;
            background: radial-gradient(ellipse at 50% 60%, rgba(255,255,255,.16), transparent 70%);
            pointer-events: none;
            z-index: -1;
        }
        /* coloured glow behind the card so the frost has something to blur */
        .gl-card::after {
            content: "";
            position: absolute;
            width: 260px; height: 260px;
            right: -90px; bottom: -110px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(242,101,34,.45), transparent 70%);
            filter: blur(20px);
            pointer-events: none;
            z-index: -1;
        }
        .gl-logo-wrap {
            align-self: center;
            display: inline-flex;
            padding: 10px 18px;
            margin-bottom: 24px;
            border-radius: 16px;
            background: rgba(255,255,255,.92);
            box-shadow: 0 10px 24px rgba(5,6,30,.25);
        }
        .gl-logo { display: block; width: 250px; max-width: 100%; height: auto; }
        .gl-brand-logo { display: block; width: 360px; max-width: 100%; height: auto; }
        .gl-card h2 { color: #fff; font-size: 26px; font-weight: 700; margin: 0 0 6px; text-align: center; letter-spacing: -.01em; }
        .gl-card .sub { color: rgba(236,234,255,.72); font-size: 14px; text-align: center; margin: 0 0 26px; }

        .gl-field { margin-bottom: 16px; }
        .gl-field label { display: block; font-size: 12.5px; font-weight: 600; color: rgba(255,255,255,.85); margin-bottom: 7px; letter-spacing: .02em; }
        .gl-input { position: relative; }
        .gl-input > i.lead {
            position: absolute; left: 15px; top: 50%; transform: translateY(-50%);
            color: rgba(255,255,255,.6); font-size: 18px; pointer-events: none;
            transition: color .15s;
        }
        .gl-input input {
            width: 100%;
            height: 50px;
            padding: 0 46px 0 44px;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,.22);
            background: rgba(255,255,255,.08);
            color: #fff;
            font-size: 14.5px;
            transition: border-color .15s, box-shadow .15s, background .15s;
            outline: none;
        }
        .gl-input input::placeholder { color: rgba(236,234,255,.5); }
        .gl-input input:hover { background: rgba(255,255,255,.11); }
        .gl-input input:focus {
            background: rgba(255,255,255,.13);
            border-color: rgba(247,148,29,.9);
            box-shadow: 0 0 0 4px rgba(242,101,34,.25);
        }
        .gl-input:focus-within > i.lead { color: #FFD2B0; }
        /* keep browser autofill on the glass instead of a yellow box */
        .gl-input input:-webkit-autofill,
        .gl-input input:-webkit-autofill:hover,
        .gl-input input:-webkit-autofill:focus {
            -webkit-text-fill-color: #fff;
            caret-color: #fff;
            transition: background-color 9999s ease-in-out 0s;
            box-shadow: inset 0 0 0 100px rgba(29,28,76,.65);
        }
        .gl-input.has-error input { border-color: rgba(255,120,120,.9); box-shadow: 0 0 0 4px rgba(255,90,90,.15); }
        .gl-input .toggle-password {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            color: rgba(255,255,255,.65); font-size: 18px; cursor: pointer; padding: 6px; border-radius: 8px;
        }
        .gl-input .toggle-password:hover { color: #fff; background: rgba(255,255,255,.1); }
        .gl-err { color: #ffb3b3; font-size: 12px; margin-top: 6px; display: flex; align-items: center; gap: 4px; }

        .gl-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 12px; margin: 4px 0 22px; font-size: 13px; }
        .gl-row .form-check-input {
            background-color: rgba(255,255,255,.12);
            border-color: rgba(255,255,255,.35);
        }
        .gl-row .form-check-input:checked { background-color: var(--gl-indigo-2); border-color: var(--gl-indigo-2); }
        .gl-row .form-check-input:focus { box-shadow: 0 0 0 3px rgba(242,101,34,.3); }
        .gl-row .form-check-label { color: rgba(236,234,255,.8); }
        .gl-row .hint { color: rgba(236,234,255,.6); display: inline-flex; align-items: center; gap: 5px; }

        .gl-alert {
            display: flex; gap: 10px; align-items: flex-start;
            padding: 11px 14px; border-radius: 14px; margin-bottom: 18px;
            background: rgba(255, 82, 82, .16); border: 1px solid rgba(255,120,120,.4); color: #ffd6d6; font-size: 13px;
            backdrop-filter: blur(6px);
        }
        .gl-alert i { font-size: 18px; flex: none; color: #ff9b9b; }

        .gl-btn {
            position: relative;
            overflow: hidden;
            width: 100%;
            height: 50px;
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 14px;
            color: #fff;
            font-weight: 600;
            font-size: 15px;
            letter-spacing: .01em;
            background: linear-gradient(135deg, #F7941D 0%, #F26522 50%, #DE4A1B 100%);
            box-shadow: 0 14px 30px rgba(226,80,28,.42), inset 0 1px 0 rgba(255,255,255,.3);
            transition: transform .15s, box-shadow .15s, opacity .15s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        /* shine that sweeps across the button on hover */
        .gl-btn::after {
            content: "";
            position: absolute; top: 0; left: -60%;
            width: 40%; height: 100%;
            background: linear-gradient(100deg, transparent, rgba(255,255,255,.35), transparent);
            transform: skewX(-20deg);
            transition: left .6s ease;
        }
        .gl-btn:hover { transform: translateY(-1px); box-shadow: 0 18px 36px rgba(226,80,28,.52), inset 0 1px 0 rgba(255,255,255,.3); }
        .gl-btn:hover::after { left: 120%; }
        .gl-btn:disabled { opacity: .8; transform: none; }
        .gl-btn i { vertical-align: -2px; margin-left: 4px; }
        .gl-spin {
            display: inline-block; vertical-align: -3px; margin-right: 8px;
            width: 16px; height: 16px; border-radius: 50%;
            border: 2px solid rgba(255,255,255,.45); border-top-color: #fff;
            animation: gl-rot .7s linear infinite;
        }
        @keyframes gl-rot { to { transform: rotate(360deg); } }

        .gl-secure {
            display: flex; align-items: center; gap: 10px;
            margin: 22px 0 0; color: rgba(236,234,255,.55); font-size: 12px;
        }
        .gl-secure::before, .gl-secure::after { content: ""; flex: 1; height: 1px; background: rgba(255,255,255,.15); }
        .gl-secure span { display: inline-flex; align-items: center; gap: 6px; }

        .gl-foot { text-align: center; margin-top: 14px; font-size: 13px; color: rgba(236,234,255,.7); }
        .gl-foot a {
            color: #fff; font-weight: 600; text-decoration: none;
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 999px; margin-left: 4px;
            background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18);
            transition: background .15s;
        }
        .gl-foot a:hover { background: rgba(255,255,255,.18); }
        .gl-copy-m { display: none; }

        @media (max-width: 900px) {
            .gl-shell { grid-template-columns: minmax(0, 1fr); max-width: 460px; }
            .gl-brand { display: none; }
            .gl-card { margin: 10px; padding: 32px 22px; }
            .gl-copy-m { display: block; text-align: center; color: rgba(233,232,255,.6); font-size: 12px; margin-top: 18px; position: relative; z-index: 1; }
            .gl-page { flex-direction: column; }
        }
        @media (prefers-reduced-motion: reduce) {
            .gl-blob { animation: none; }
        }
    </style>

    <div class="gl-blob b1"></div>
    <div class="gl-blob b2"></div>
    <div class="gl-blob b3"></div>
    <div class="gl-grid"></div>

    <div class="gl-shell">
        {{-- Brand panel (desktop) --}}
        <div class="gl-brand">
            <div>
                <img src="{{ asset('brand/logo-white.png') }}" class="gl-brand-logo" alt="Sha-Shib Campus — Centralised Academic Management &amp; Performance Unified System">
                <h1>Central Academic<br><span>Management Portal</span></h1>
                <p class="lead-sm">One place for admissions, students, fees, scholarships and institute administration across the Sha-Shib Group.</p>
            </div>

            <ul class="gl-feats">
                <li><i class="ti ti-school"></i> Admissions &amp; student onboarding</li>
                <li><i class="ti ti-receipt-2"></i> Fees, payments &amp; scholarships</li>
                <li><i class="ti ti-shield-check"></i> Role-based, audited access</li>
            </ul>

            <small class="copy">&copy; {{ date('Y') }} Sha-Shib Group. All rights reserved.</small>
        </div>

        {{-- Sign-in card --}}
        <div class="gl-card">
            <span class="gl-logo-wrap">
                <img src="{{ asset('brand/logo-navy.png') }}" class="gl-logo" alt="Sha-Shib Campus">
            </span>

            <h2>Welcome back</h2>
            <p class="sub">Sign in to continue to your dashboard</p>

            <form wire:submit.prevent="login" novalidate>
                @if($errorMessage)
                    <div class="gl-alert" role="alert">
                        <i class="ti ti-alert-circle"></i>
                        <span>{{ $errorMessage }}</span>
                    </div>
                @endif

                <div class="gl-field">
                    <label for="login_email">Email address</label>
                    <div class="gl-input @error('email') has-error @enderror">
                        <i class="ti ti-mail lead"></i>
                        <input id="login_email" type="email" wire:model.defer="email" placeholder="you@shashib.com" autocomplete="username" autofocus>
                    </div>
                    @error('email') <span class="gl-err"><i class="ti ti-info-circle"></i> {{ $message }}</span> @enderror
                </div>

                <div class="gl-field">
                    <label for="login_password">Password</label>
                    <div class="gl-input @error('password') has-error @enderror">
                        <i class="ti ti-lock lead"></i>
                        <input id="login_password" type="password" wire:model.defer="password" class="pass-input" placeholder="Enter your password" autocomplete="current-password">
                        <span class="ti toggle-password ti-eye-off" title="Show / hide password"></span>
                    </div>
                    @error('password') <span class="gl-err"><i class="ti ti-info-circle"></i> {{ $message }}</span> @enderror
                </div>

                <div class="gl-row">
                    <div class="form-check mb-0">
                        <input class="form-check-input" wire:model.defer="remember" id="remember_me" type="checkbox">
                        <label for="remember_me" class="form-check-label">Remember me</label>
                    </div>
                    <span class="hint"><i class="ti ti-help-circle"></i> Forgot password? Contact your admin</span>
                </div>

                <button type="submit" class="gl-btn" wire:loading.attr="disabled" wire:target="login">
                    <span wire:loading.remove wire:target="login">Sign In <i class="ti ti-arrow-right"></i></span>
                    <span wire:loading wire:target="login"><span class="gl-spin"></span>Signing in…</span>
                </button>
            </form>

            <div class="gl-secure"><span><i class="ti ti-shield-lock"></i> Secure staff sign-in</span></div>

            <div class="gl-foot">
                Student or applicant? <a href="{{ route('portal.home') }}">Go to Admissions <i class="ti ti-arrow-up-right"></i></a>
            </div>
        </div>
    </div>

    <p class="gl-copy-m">&copy; {{ date('Y') }} Sha-Shib Group</p>
</div>
