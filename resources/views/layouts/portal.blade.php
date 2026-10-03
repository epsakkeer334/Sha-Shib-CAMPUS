@php
    $portalUser = auth()->user();
    $portalStudent = $portalUser && $portalUser->hasRole('student') ? $portalUser->student : null;
    $brand = $portalStudent ? optional($portalStudent->institute)->name : config('camp.portal_name', config('app.name'));
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? 'Admissions' }} · {{ $brand }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Serif:wght@500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #F7F7F4; --surface: #FFFFFF; --ink: #17201B; --ink-2: #2E3833; --muted: #56605A;
            --line: #DCDFD8; --line-2: #ECEEEA; --field: #C9CEC6;
            --brand: #0E6B55; --brand-ink: #0B5544; --brand-dark: #0A4F3F; --brand-soft: #E3F0EA; --brand-tint: #F2F8F5;
            --info-bg: #E3ECF7; --info: #1B4F87; --warn-bg: #FBEFD9; --warn: #7A4006;
            --danger-bg: #F8E4E1; --danger: #8E2620; --danger-line: #E4B9B4; --danger-tint: #FBF4F3;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 15px/22px 'IBM Plex Sans', 'Segoe UI', system-ui, sans-serif; }
        a { color: var(--brand); } a:hover { color: var(--brand-dark); }
        input, select, textarea, button { font: inherit; }
        h1, h2 { margin: 0; }
        .serif { font-family: 'IBM Plex Serif', Georgia, serif; }
        .mono { font-family: 'IBM Plex Mono', ui-monospace, monospace; }
        .muted { color: var(--muted); }
        .page { min-height: 100vh; display: flex; flex-direction: column; }

        /* header / footer */
        .p-header { background: var(--surface); border-bottom: 1px solid var(--line); }
        .p-bell { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 10px; color: var(--ink-2); }
        .p-bell:hover { background: var(--brand-tint); color: var(--brand); }
        .p-bell-badge { position: absolute; top: 2px; right: 2px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: #C2410C; color: #fff; font-size: 11px; font-weight: 700; line-height: 18px; text-align: center; border: 2px solid var(--surface); }
        .p-header .wrap { max-width: 1120px; margin: 0 auto; padding: 0 24px; min-height: 72px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .logo { display: flex; align-items: center; gap: 10px; color: var(--ink); text-decoration: none; font-weight: 600; font-size: 16px; }
        .logo-mark { width: 36px; height: 36px; border-radius: 8px; background: var(--brand); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; }
        .avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--brand-soft); color: var(--brand-ink); display: inline-flex; align-items: center; justify-content: center; font-weight: 600; font-size: 13px; }
        .p-footer { background: var(--ink); color: #C9D1CB; }
        .p-footer .wrap { max-width: 1120px; margin: 0 auto; padding: 32px 24px; display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; font-size: 14px; }

        /* layout */
        .main { flex-grow: 1; width: 100%; max-width: 880px; margin: 0 auto; padding: 40px 24px 64px; display: flex; flex-direction: column; gap: 28px; }
        .main.wide { max-width: 1120px; }
        .eyebrow { font-size: 13px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--brand); }
        .title { font-family: 'IBM Plex Serif', Georgia, serif; font-size: 36px; line-height: 44px; font-weight: 600; }
        .lead { color: var(--ink-2); margin: 0; }
        .card { background: var(--surface); border: 1px solid var(--line); border-radius: 16px; padding: 28px; display: flex; flex-direction: column; gap: 20px; }
        .card.flush { padding: 0; gap: 0; }
        .card h2 { font-size: 18px; font-weight: 600; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(240px, 100%), 1fr)); gap: 16px 20px; align-items: start; }
        .grid.narrow { grid-template-columns: repeat(auto-fit, minmax(min(180px, 100%), 1fr)); }
        .row-between { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }

        /* fields */
        .field { display: flex; flex-direction: column; gap: 6px; }
        .field > .label { font-size: 14px; font-weight: 500; }
        .input { height: 48px; border: 1px solid var(--field); border-radius: 10px; padding: 0 14px; background: #fff; color: var(--ink); width: 100%; }
        textarea.input { height: auto; padding: 12px 14px; resize: vertical; }
        .input:focus { outline: 2px solid var(--brand); outline-offset: 1px; border-color: var(--brand); }
        .input[disabled] { background: #F4F5F2; color: var(--muted); border-color: var(--line); }
        .input.invalid { border: 1.5px solid #A3302A; }
        .hint { font-size: 13px; color: var(--muted); }
        .error { font-size: 13px; color: var(--danger); }

        /* buttons */
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; height: 52px; padding: 0 28px; border-radius: 10px; border: 0; font-weight: 600; font-size: 16px; text-decoration: none; cursor: pointer; }
        .btn-primary { background: var(--brand); color: #fff; } .btn-primary:hover { background: var(--brand-dark); color: #fff; }
        .btn-secondary { background: #fff; color: var(--ink); border: 1px solid var(--field); font-weight: 500; padding: 0 20px; }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-sm { height: 40px; padding: 0 14px; font-size: 14px; border-radius: 8px; }
        .btn[disabled] { opacity: .55; cursor: not-allowed; }
        .link-btn { background: none; border: 0; color: var(--brand); font-weight: 600; cursor: pointer; padding: 0; }

        /* steps */
        .steps { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
        .steps li { display: flex; flex-direction: column; gap: 8px; }
        .steps .bar { height: 4px; border-radius: 2px; background: var(--line); }
        .steps .done .bar, .steps .current .bar { background: var(--brand); }
        .steps .tag { font-size: 12px; color: var(--muted); }
        .steps .done .tag, .steps .current .tag { color: var(--brand); font-weight: 600; }
        .steps .current .name { font-weight: 600; }
        .steps .todo .name { color: var(--muted); }
        .steps a { color: inherit; text-decoration: none; }

        /* segmented & choice cards */
        .seg { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 4px; padding: 4px; border-radius: 12px; background: #ECEEEA; }
        .seg button { height: 40px; border: 0; border-radius: 9px; background: transparent; color: #4A534D; cursor: pointer; }
        .seg button[aria-pressed="true"] { background: #fff; color: var(--ink); font-weight: 600; box-shadow: 0 1px 2px rgba(23, 32, 27, .12); }
        .choices { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(140px, 100%), 1fr)); gap: 8px; }
        .choices.wide { grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr)); gap: 10px; }
        .choice { display: flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: 12px 14px; border: 1px solid var(--field); border-radius: 10px; background: #fff; color: var(--ink); text-align: left; cursor: pointer; }
        .choice[aria-pressed="true"] { border: 2px solid var(--brand); padding: 11px 13px; background: var(--brand-tint); }
        .choice[aria-pressed="true"] .choice-title { color: var(--brand-ink); }
        .choice .choice-title { font-weight: 600; }
        .choice .choice-sub { font-size: 13px; color: var(--muted); }
        .choice[disabled] { opacity: .5; cursor: not-allowed; }

        /* badges & notices */
        .badge { display: inline-flex; align-items: center; gap: 6px; height: 26px; padding: 0 10px; border-radius: 999px; font-size: 13px; font-weight: 500; white-space: nowrap; }
        .b-ok { background: var(--brand-soft); color: var(--brand-ink); } .b-info { background: var(--info-bg); color: var(--info); }
        .b-warn { background: var(--warn-bg); color: var(--warn); } .b-danger { background: var(--danger-bg); color: var(--danger); }
        .b-muted { background: #ECEEEA; color: #4A534D; }
        .notice { border-radius: 16px; padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; }
        .notice.danger { background: var(--danger-tint); border: 1px solid var(--danger-line); color: #4A1612; }
        .notice.danger strong { color: #6E1E19; }
        .notice.info { background: var(--info-bg); border: 1px solid #C3D3E8; color: #163E6B; }
        .notice.ok { background: var(--brand-soft); border: 1px solid #BFDCCF; color: var(--brand-ink); }

        /* documents */
        .docrow { display: grid; grid-template-columns: 44px minmax(0, 1fr) auto; gap: 16px; align-items: center; padding: 18px 28px; border-bottom: 1px solid var(--line-2); }
        .docrow:last-child { border-bottom: 0; }
        .doc-icon { width: 44px; height: 44px; border-radius: 8px; background: #ECEEEA; color: var(--muted); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 600; overflow: hidden; }
        .doc-icon img { width: 100%; height: 100%; object-fit: cover; }
        .dropzone { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 20px; border: 1.5px dashed #B9BFB6; border-radius: 12px; background: #FAFBF9; text-align: center; cursor: pointer; }
        .visually-hidden { position: absolute !important; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

        /* fees */
        .feerow { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 16px; align-items: center; padding: 18px 28px; border-bottom: 1px solid var(--line-2); }
        .feerow:last-child { border-bottom: 0; }

        /* timeline */
        .timeline { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
        .timeline li { display: grid; grid-template-columns: 32px minmax(0, 1fr); gap: 16px; padding-bottom: 22px; }
        .timeline li:last-child { padding-bottom: 0; }
        .dot { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .dot.done { background: var(--brand); color: #fff; } .dot.active { border: 3px solid #1F5A99; background: var(--info-bg); }
        .dot.todo { border: 2px solid var(--field); } .dot.problem { border: 3px solid var(--danger); background: var(--danger-bg); }

        /* toast */
        #portal-toasts { position: fixed; top: 16px; right: 16px; z-index: 50; display: flex; flex-direction: column; gap: 8px; max-width: min(380px, calc(100vw - 32px)); }
        .p-toast { background: var(--ink); color: #fff; padding: 12px 16px; border-radius: 10px; box-shadow: 0 8px 24px rgba(0, 0, 0, .18); font-size: 14px; }
        .p-toast.success { background: var(--brand-dark); } .p-toast.danger { background: var(--danger); } .p-toast.warning { background: #8A4B07; }

        @media (max-width: 760px) { .hide-sm { display: none !important; } .title { font-size: 30px; line-height: 38px; } .docrow, .feerow { padding: 16px 18px; } }
        @media (max-width: 600px) { .feerow { grid-template-columns: minmax(0, 1fr) auto; } .card { padding: 20px; } }
    </style>
    @livewireStyles
</head>
<body>
<div class="page">
    <header class="p-header">
        <div class="wrap">
            <a href="{{ route('portal.home') }}" class="logo"><span class="logo-mark">{{ mb_strtoupper(mb_substr($brand, 0, 1)) }}</span><span>{{ $brand }}</span></a>
            @if($portalStudent)
                <div style="display: flex; align-items: center; gap: 12px;">
                    <a href="{{ route('portal.status') }}" class="hide-sm" style="text-decoration: none; font-weight: 500;">My application</a>
                    @php $portalUnread = $portalUser->unreadNotifications()->count(); @endphp
                    <a href="{{ route('portal.notifications') }}" class="p-bell" title="Notifications" aria-label="Notifications{{ $portalUnread ? " ({$portalUnread} unread)" : '' }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6"/><path d="M9 17v1a3 3 0 0 0 6 0v-1"/></svg>
                        @if($portalUnread)<span class="p-bell-badge">{{ $portalUnread > 99 ? '99+' : $portalUnread }}</span>@endif
                    </a>
                    <span class="avatar" aria-hidden="true">{{ $portalStudent->initials }}</span>
                    <span class="hide-sm" style="font-weight: 500;">{{ $portalStudent->first_name }}</span>
                    <a href="{{ route('portal.logout') }}" style="font-size: 14px;" data-signout>Sign out</a>
                </div>
            @else
                <span style="font-size: 14px;" class="muted">Already applied? <a href="{{ route('portal.login') }}" style="font-weight: 600;">Sign in</a></span>
            @endif
        </div>
    </header>

    {{ $slot }}

    <footer class="p-footer">
        <div class="wrap">
            <span style="color: #fff; font-weight: 600;">{{ $brand }}</span>
            @if($portalStudent && $portalStudent->institute)
                <span>{{ trim($portalStudent->institute->address . ' ' . $portalStudent->institute->city) }} @if($portalStudent->institute->phone) · {{ $portalStudent->institute->phone }} @endif</span>
                @if($portalStudent->institute->email)<span>Admissions help: {{ $portalStudent->institute->email }}</span>@endif
            @else
                <span>Admissions portal</span>
            @endif
        </div>
    </footer>
</div>

<div id="portal-toasts" aria-live="polite"></div>
@livewireScripts
<script>
    // Toasts sent by components: dispatchBrowserEvent('show-toast', {type, message})
    window.addEventListener('show-toast', function (event) {
        var el = document.createElement('div');
        el.className = 'p-toast ' + (event.detail.type || '');
        el.setAttribute('role', 'status');
        el.textContent = event.detail.message;
        document.getElementById('portal-toasts').prepend(el);
        setTimeout(function () { el.remove(); }, 5000);
    });
    @if(session('toast'))
        document.addEventListener('DOMContentLoaded', function () {
            window.dispatchEvent(new CustomEvent('show-toast', { detail: @json(session('toast')) }));
        });
    @endif
    // Sign out: let the field being edited send its value (draft autosave) before leaving.
    document.addEventListener('click', function (e) {
        var link = e.target.closest('[data-signout]');
        if (!link || !document.activeElement || !document.activeElement.matches('input, select, textarea')) return;
        e.preventDefault();
        document.activeElement.blur();
        setTimeout(function () { window.location = link.href; }, 700);
    });
    // Copy buttons: <button data-copy="text">
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-copy]');
        if (btn && navigator.clipboard) {
            navigator.clipboard.writeText(btn.getAttribute('data-copy'));
            btn.textContent = 'Copied';
        }
    });
</script>
</body>
</html>
