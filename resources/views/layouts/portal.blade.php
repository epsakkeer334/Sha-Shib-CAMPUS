@php
    $portalUser = auth()->user();
    $portalStudent = $portalUser && $portalUser->hasRole('student') ? $portalUser->student : null;
    $brand = $portalStudent ? optional($portalStudent->institute)->name : config('camp.portal_name', config('app.name'));
    $brandInitial = mb_strtoupper(mb_substr($brand, 0, 1));
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#1D1C4C">
    <title>{{ $title ?? 'Admissions' }} · {{ $brand }}</title>
    <link rel="icon" type="image/png" href="{{ asset('brand/favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('brand/apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="preload" as="image" href="{{ asset('portal/campus-bg.jpg') }}">
    <style>
        /* ───────── Admissions portal design system (glass on campus photo) ───────── */
        :root {
            --bg: #F3F4F9; --surface: #FFFFFF; --ink: #17163F; --ink-2: #34365C; --muted: #585B7C;
            --line: #DDDFEC; --line-2: #E9EAF3; --field: #CACDE0;
            --brand: #2D2B7F; --brand-rgb: 45, 43, 127; --brand-2: #1D1C4C; --brand-ink: #1D1C4C; --brand-dark: #1D1C4C; --brand-soft: #E9E9F6; --brand-tint: #F4F4FB;
            --accent: #F26522; --accent-rgb: 226, 80, 28; --accent-ink: #B8440F;
            --gold: #F7941D; --gold-soft: #FBB866;
            --ok-bg: #E3F6EC; --ok: #0B6B43; --ok-line: #A9DEC2;
            --info-bg: #E9E9F6; --info: #2D2B7F; --warn-bg: #FFF2DB; --warn: #8A4B00;
            --danger-bg: #FDE7E5; --danger: #A01F14; --danger-line: #F3C0B9; --danger-tint: #FFF4F2;
            --glass: #FFFFFF; --glass-line: #E3E8F2;
            --shadow: 0 1px 2px rgba(16, 24, 40, .05), 0 12px 32px rgba(16, 24, 40, .08);
            --shadow-lg: 0 2px 4px rgba(16, 24, 40, .06), 0 24px 48px rgba(16, 24, 40, .14);
            --navy: #1D1C4C; --navy-2: #2B2A6B;
            --grad: linear-gradient(135deg, #F07A1F 0%, #DE4A1B 100%);
            --grad-navy: linear-gradient(135deg, #2D2B7F 0%, #1D1C4C 100%);
            --ease: cubic-bezier(.2, .7, .2, 1);
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 15px/23px 'Plus Jakarta Sans', 'Segoe UI', system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
        a { color: var(--brand); transition: color .15s ease; } a:hover { color: var(--brand-dark); }
        input, select, textarea, button { font: inherit; }
        h1, h2, h3 { margin: 0; }
        .serif { font-family: inherit; }
        .mono { font-family: 'JetBrains Mono', ui-monospace, monospace; }
        .muted { color: var(--muted); }
        :focus-visible { outline: 3px solid var(--gold); outline-offset: 2px; }

        /* light page; a campus-photo band sits behind each page title (and the home / sign-in heroes) */
        .page { position: relative; min-height: 100vh; display: flex; flex-direction: column; isolation: isolate; overflow-x: clip; }
        .campus-band { position: relative; isolation: isolate; color: #fff; background-color: var(--navy); }
        .campus-band::before, .page-head::before { content: ""; position: absolute; z-index: -1;
            background: radial-gradient(50% 60% at 8% 10%, rgba(83, 80, 200, .30), transparent 70%),
                        radial-gradient(45% 55% at 100% 100%, rgba(242, 101, 34, .20), transparent 70%),
                        linear-gradient(180deg, rgba(20, 19, 56, .82) 0%, rgba(26, 25, 70, .80) 60%, rgba(29, 28, 76, .93) 100%),
                        var(--navy) var(--band-img, url('{{ asset('portal/campus-bg.jpg') }}')) center 38% / cover no-repeat; }
        .campus-band::before { inset: 0; }
        /* the band reaches from the top of the page to a little below the title, so the first cards overlap it */
        .page-head { position: relative; }
        .page-head::before { top: -240px; bottom: -112px; left: calc(50% - 50vw); width: 100vw; }

        /* dark surfaces (header, footer, highlight banners) */
        .glass-dark { background: rgba(24, 23, 64, .92); -webkit-backdrop-filter: blur(16px) saturate(140%); backdrop-filter: blur(16px) saturate(140%); border: 1px solid rgba(255, 255, 255, .10); }

        /* header / footer */
        .p-header { position: sticky; top: 0; z-index: 30; border-width: 0 0 1px; transition: box-shadow .2s ease, background-color .2s ease; }
        .p-header.scrolled { background: rgba(24, 23, 64, .97); box-shadow: 0 10px 30px rgba(14, 13, 40, .22); }
        .p-header .wrap { max-width: 1200px; margin: 0 auto; padding: 0 24px; min-height: 72px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .logo { display: flex; align-items: center; gap: 12px; color: #fff; text-decoration: none; min-width: 0; }
        .logo:hover { color: #fff; }
        .logo-img { display: block; height: 42px; width: auto; flex-shrink: 0; }
        .logo-inst { padding-left: 14px; border-left: 1px solid rgba(255, 255, 255, .24); font-size: 13px; font-weight: 700; line-height: 1.3; color: rgba(255, 255, 255, .86); max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .foot-brand .logo-img { height: 46px; }
        .foot-org { display: block; margin-top: 10px; font-size: 13px; font-weight: 700; color: rgba(255, 255, 255, .78); }
        .logo-mark { width: 40px; height: 40px; border-radius: 12px; background: var(--grad); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 17px; flex-shrink: 0; box-shadow: 0 8px 20px rgba(var(--accent-rgb), .40); }
        .logo-text { display: flex; flex-direction: column; line-height: 1.15; min-width: 0; }
        .logo-text b { font-weight: 800; font-size: 16.5px; letter-spacing: -.01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .logo-text small { font-size: 11.5px; color: rgba(255, 255, 255, .7); letter-spacing: .04em; }
        .p-nav { display: flex; align-items: center; gap: 6px; }
        .p-nav-sep { width: 1px; height: 24px; background: rgba(255, 255, 255, .18); margin: 0 8px; }
        .p-menu-btn { display: none; width: 42px; height: 42px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, .22); background: rgba(255, 255, 255, .06); color: #fff; cursor: pointer; align-items: center; justify-content: center; }
        .p-menu-btn:hover { background: rgba(255, 255, 255, .14); }
        .p-menu-btn .bars, .p-menu-btn .bars::before, .p-menu-btn .bars::after { display: block; width: 18px; height: 2px; border-radius: 2px; background: currentColor; transition: transform .2s ease, opacity .2s ease; }
        .p-menu-btn .bars { position: relative; }
        .p-menu-btn .bars::before, .p-menu-btn .bars::after { content: ""; position: absolute; left: 0; }
        .p-menu-btn .bars::before { top: -6px; } .p-menu-btn .bars::after { top: 6px; }
        .p-header.open .p-menu-btn .bars { background: transparent; }
        .p-header.open .p-menu-btn .bars::before { transform: translateY(6px) rotate(45deg); }
        .p-header.open .p-menu-btn .bars::after { transform: translateY(-6px) rotate(-45deg); }
        .p-mnav { display: none; border-top: 1px solid rgba(255, 255, 255, .10); padding: 8px 16px 16px; }
        .p-mnav a { display: flex; align-items: center; justify-content: space-between; min-height: 48px; padding: 0 12px; border-radius: 12px; color: #fff; text-decoration: none; font-weight: 600; }
        .p-mnav a:hover, .p-mnav a[aria-current="page"] { background: rgba(255, 255, 255, .08); color: #fff; }
        .p-mnav a.cta { margin-top: 8px; justify-content: center; background: var(--grad); font-weight: 700; }
        .p-header.open .p-mnav { display: block; animation: rise .25s var(--ease); }
        .p-nav-link { color: rgba(255, 255, 255, .85); text-decoration: none; font-weight: 600; font-size: 14.5px; padding: 8px 10px; border-radius: 10px; transition: color .15s ease, background-color .15s ease; }
        .p-nav-link:hover, .p-nav-link[aria-current="page"] { color: #fff; background: rgba(255, 255, 255, .10); }
        .p-bell { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 12px; color: #fff; transition: background-color .15s ease; }
        .p-bell:hover { background: rgba(255, 255, 255, .12); color: #fff; }
        .p-bell-badge[hidden] { display: none; }
        .p-bell-badge { position: absolute; top: 3px; right: 3px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: var(--gold); color: #2A1600; font-size: 11px; font-weight: 800; line-height: 18px; text-align: center; border: 2px solid #0E1A3D; }
        .avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #F7CF7E, #E8893A); color: #2A1600; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px; flex-shrink: 0; }
        .p-user { display: flex; flex-direction: column; align-items: flex-end; line-height: 1.2; color: #fff; }
        .p-user b { font-weight: 700; font-size: 14.5px; }
        .p-user a { font-size: 13px; color: var(--gold-soft); font-weight: 700; text-decoration: none; }
        .p-user a:hover { color: #fff; }
        .p-footer { background: var(--navy); color: rgba(255, 255, 255, .72); font-size: 14px; line-height: 22px; }
        .p-footer .wrap { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        .p-footer .foot { display: grid; grid-template-columns: minmax(0, 1.5fr) repeat(3, minmax(0, 1fr)); gap: 32px 40px; padding-top: 56px; padding-bottom: 40px; }
        .foot h3 { color: #fff; font-size: 13px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; margin-bottom: 14px; }
        .foot ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 9px; }
        .foot a { color: rgba(255, 255, 255, .78); text-decoration: none; transition: color .15s ease; }
        .foot a:hover { color: var(--gold-soft); }
        .foot-brand p { margin: 14px 0 18px; max-width: 360px; }
        .foot-trust { display: flex; flex-wrap: wrap; gap: 8px; }
        .foot-trust span { display: inline-flex; align-items: center; gap: 6px; height: 30px; padding: 0 12px; border-radius: 999px; background: rgba(255, 255, 255, .07); border: 1px solid rgba(255, 255, 255, .12); font-size: 12.5px; font-weight: 600; color: rgba(255, 255, 255, .86); }
        .foot-contact li { display: grid; grid-template-columns: 20px minmax(0, 1fr); gap: 10px; align-items: start; }
        .foot-contact svg { margin-top: 2px; color: var(--gold-soft); }
        .foot-bottom { border-top: 1px solid rgba(255, 255, 255, .10); }
        .foot-bottom .wrap { display: flex; justify-content: space-between; gap: 8px 24px; flex-wrap: wrap; padding-top: 18px; padding-bottom: 22px; font-size: 13px; color: rgba(255, 255, 255, .58); }

        /* layout */
        .main { flex-grow: 1; width: 100%; max-width: 960px; margin: 0 auto; padding: 40px 24px 72px; display: flex; flex-direction: column; gap: 24px; }
        .main.wide { max-width: 1200px; }
        .page-head { display: flex; flex-direction: column; gap: 8px; color: #fff; }
        .eyebrow { font-size: 12.5px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: var(--gold-soft); }
        .title { font-size: clamp(28px, 3.4vw, 40px); line-height: 1.15; font-weight: 800; letter-spacing: -.02em; color: #fff; text-wrap: balance; }
        .lead { color: rgba(255, 255, 255, .84); margin: 0; font-size: 16px; line-height: 25px; max-width: 640px; }
        .on-dark { color: var(--ink-2); }
        .on-dark a { color: var(--brand); font-weight: 700; text-decoration: none; } .on-dark a:hover { color: var(--brand-dark); text-decoration: underline; }
        .card { color: var(--ink); background: var(--glass); border: 1px solid var(--glass-line); box-shadow: var(--shadow); border-radius: 20px; padding: 26px 28px; display: flex; flex-direction: column; gap: 20px; }
        .card.flush { padding: 0; gap: 0; overflow: hidden; }
        .card h2 { font-size: 17.5px; line-height: 24px; font-weight: 800; letter-spacing: -.01em; }
        .card-head { display: flex; align-items: center; gap: 14px; }
        .card-head .ico { width: 42px; height: 42px; border-radius: 12px; background: var(--brand-soft); color: var(--brand); display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .card-head .sub { font-size: 13.5px; color: var(--muted); }
        .card-head .end { margin-left: auto; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(240px, 100%), 1fr)); gap: 16px 18px; align-items: start; }
        .grid.narrow { grid-template-columns: repeat(auto-fit, minmax(min(180px, 100%), 1fr)); }
        .row-between { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
        .actionbar { position: sticky; bottom: 16px; z-index: 5; background: rgba(255, 255, 255, .96); -webkit-backdrop-filter: blur(12px); backdrop-filter: blur(12px); border: 1px solid var(--glass-line); box-shadow: 0 -2px 6px rgba(16, 24, 40, .03), 0 16px 40px rgba(16, 24, 40, .16); border-radius: 18px; padding: 12px 14px; display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
        .actionbar .group { display: flex; gap: 10px; flex-wrap: wrap; margin-left: auto; }

        /* step flow: sidebar + content */
        .flow { display: grid; grid-template-columns: 300px minmax(0, 1fr); gap: 24px; align-items: start; }
        .flow-main { display: flex; flex-direction: column; gap: 22px; min-width: 0; }
        .flow-side { position: sticky; top: 96px; display: flex; flex-direction: column; gap: 16px; }
        .flow-side .card { padding: 20px; gap: 16px; }
        .progress { height: 8px; border-radius: 999px; background: #E1E6F2; overflow: hidden; }
        .progress > span { display: block; height: 100%; border-radius: 999px; background: var(--grad); transition: width .6s var(--ease); }
        .steps { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
        .steps li > a, .steps li > div { display: grid; grid-template-columns: 34px minmax(0, 1fr); gap: 12px; align-items: center; padding: 9px 10px; border-radius: 14px; text-decoration: none; color: var(--ink); border: 1px solid transparent; transition: background-color .15s ease, border-color .15s ease, transform .15s ease; }
        .steps li > a:hover { background: rgba(var(--brand-rgb), .06); transform: translateX(2px); }
        .steps .num { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px; border: 1.5px solid #C5CCDF; color: var(--muted); }
        .steps .name { font-weight: 700; font-size: 14.5px; display: block; }
        .steps .tag { font-size: 12.5px; color: var(--muted); display: block; }
        .steps .done .num { background: var(--ok-bg); border-color: transparent; color: var(--ok); }
        .steps .done .tag { color: var(--ok); font-weight: 600; }
        .steps .current > div, .steps .current > a { background: #EFEFF9; border-color: #C9D1FF; }
        .steps .current .num { background: var(--grad); border-color: transparent; color: #fff; box-shadow: 0 6px 14px rgba(var(--accent-rgb), .35); }
        .steps .current .name { color: var(--brand-ink); font-weight: 800; }
        .steps .current .tag { color: var(--brand-ink); font-weight: 600; }
        .steps .todo .name { color: var(--ink-2); }
        .side-help { display: flex; gap: 12px; align-items: flex-start; font-size: 13px; line-height: 19px; border-top: 1px solid var(--line); padding-top: 14px; }
        .side-help .ico { width: 34px; height: 34px; border-radius: 10px; background: var(--warn-bg); color: var(--warn); display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }

        /* fields */
        .field { display: flex; flex-direction: column; gap: 7px; min-width: 0; }
        .field > .label { font-size: 13.5px; font-weight: 700; color: #1B2745; }
        .field > .label .req { color: #B42318; }
        .input { height: 50px; border: 1px solid var(--field); border-radius: 13px; padding: 0 15px; width: 100%; color: var(--ink); font-size: 15px;
            background-color: #fff; box-shadow: 0 1px 2px rgba(16, 24, 40, .04);
            transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease; }
        select.input { appearance: none; -webkit-appearance: none; padding-right: 40px; cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 20 20' fill='none' stroke='%2355627F' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; }
        textarea.input { height: auto; padding: 12px 15px; resize: vertical; }
        .input:hover { border-color: #98A3C2; }
        .input:focus { outline: none; border-color: var(--brand); background-color: #fff; box-shadow: 0 0 0 4px rgba(var(--brand-rgb), .16); }
        .input::placeholder { color: #7A849E; }
        .input[disabled] { background-color: #F2F4F9; color: var(--muted); border-color: var(--line); cursor: not-allowed; }
        .input.invalid { border: 1.5px solid #B42318; background-color: #FFF8F7; }
        .hint { font-size: 12.5px; color: var(--muted); line-height: 18px; }
        .error { font-size: 12.5px; color: var(--danger); font-weight: 600; line-height: 18px; }
        input[type="checkbox"] { width: 20px; height: 20px; accent-color: var(--brand); margin: 0; }

        /* buttons */
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 50px; padding: 0 24px; border-radius: 13px; border: 0; font-weight: 700; font-size: 15px; text-decoration: none; cursor: pointer; white-space: nowrap;
            transition: transform .15s ease, box-shadow .2s ease, background-color .15s ease, border-color .15s ease, filter .15s ease; }
        .btn:hover { transform: translateY(-1px); }
        .btn:active { transform: translateY(0); }
        .btn-primary { background: var(--grad); color: #fff; box-shadow: 0 10px 24px rgba(var(--accent-rgb), .32); }
        .btn-primary:hover { color: #fff; filter: brightness(1.06); box-shadow: 0 14px 32px rgba(var(--accent-rgb), .42); }
        .btn-secondary { background: #fff; color: var(--ink); border: 1px solid #C5CCDF; }
        .btn-secondary:hover { color: var(--ink); background: #fff; border-color: #98A3C2; }
        .btn-ghost { background: rgba(255, 255, 255, .10); color: #fff; border: 1px solid rgba(255, 255, 255, .35); }
        .btn-ghost:hover { color: #fff; background: rgba(255, 255, 255, .18); }
        .btn-danger { background: #B42318; color: #fff; box-shadow: 0 10px 24px rgba(180, 35, 24, .28); }
        .btn-danger:hover { color: #fff; filter: brightness(1.08); }
        .btn-sm { min-height: 40px; padding: 0 16px; font-size: 14px; border-radius: 11px; }
        .btn[disabled] { opacity: .55; cursor: not-allowed; transform: none; }
        .btn .arrow { transition: transform .2s ease; }
        .btn:hover .arrow { transform: translateX(3px); }
        .link-btn { background: none; border: 0; color: var(--brand); font-weight: 700; cursor: pointer; padding: 0; }
        .link-btn:hover { color: var(--brand-dark); text-decoration: underline; }
        .spin { width: 16px; height: 16px; border-radius: 50%; border: 2px solid rgba(255, 255, 255, .45); border-top-color: #fff; display: inline-block; animation: spin .7s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* segmented & choice cards */
        .seg { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 4px; padding: 4px; border-radius: 14px; background: #F1F3F9; border: 1px solid #E1E6F0; }
        .seg button { height: 40px; border: 0; border-radius: 10px; background: transparent; color: #3D4966; font-weight: 600; cursor: pointer; transition: background-color .15s ease, box-shadow .15s ease, color .15s ease; }
        .seg button:hover { color: var(--ink); }
        .seg button[aria-pressed="true"] { background: #fff; color: var(--ink); font-weight: 800; box-shadow: 0 2px 6px rgba(11, 23, 51, .12); }
        .choices { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(150px, 100%), 1fr)); gap: 10px; }
        .choices.wide { grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr)); }
        .choice { position: relative; display: flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: 13px 15px; border: 1px solid #C5CCDF; border-radius: 14px; background: #fff; color: var(--ink); text-align: left; cursor: pointer;
            transition: border-color .15s ease, box-shadow .2s ease, background-color .15s ease, transform .15s ease; }
        .choice:hover { border-color: #98A3C2; background: #FAFBFF; transform: translateY(-1px); }
        .choice[aria-pressed="true"] { border: 2px solid var(--brand); padding: 12px 14px; background: #EFEFF9; box-shadow: 0 0 0 4px rgba(var(--brand-rgb), .12); }
        .choice[aria-pressed="true"]::after { content: ""; position: absolute; top: 10px; right: 10px; width: 20px; height: 20px; border-radius: 50%;
            background: var(--brand) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none' stroke='white' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5.5 10.5 L8.5 13.5 L14.5 6.5'/%3E%3C/svg%3E") center / 14px no-repeat; animation: pop .2s var(--ease); }
        .choice[aria-pressed="true"] .choice-title { color: var(--brand-ink); }
        .choice .choice-title { font-weight: 800; padding-right: 24px; }
        .choice .choice-sub { font-size: 12.5px; color: var(--muted); line-height: 18px; }
        .choice[disabled] { opacity: .5; cursor: not-allowed; transform: none; }

        /* badges & notices */
        .badge { display: inline-flex; align-items: center; gap: 6px; height: 26px; padding: 0 10px; border-radius: 999px; font-size: 12.5px; font-weight: 700; white-space: nowrap; }
        .b-ok { background: var(--ok-bg); color: var(--ok); } .b-info { background: var(--info-bg); color: var(--info); }
        .b-warn { background: var(--warn-bg); color: var(--warn); } .b-danger { background: var(--danger-bg); color: var(--danger); }
        .b-muted { background: #EDF0F7; color: #44506B; }
        .notice { border-radius: 18px; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; box-shadow: 0 6px 18px rgba(16, 24, 40, .06); font-size: 14.5px; }
        .notice.danger { background: #FFF4F2; border: 1px solid var(--danger-line); color: #5E130B; }
        .notice.danger strong { color: #7A160D; }
        .notice.info { background: #F2F4FF; border: 1px solid #C9D1FF; color: #1D2B6B; }
        .notice.ok { background: #ECF9F2; border: 1px solid var(--ok-line); color: #0B4F33; }
        .card .notice { box-shadow: none; }

        /* scholarships (Module 2C) */
        .fee-schol { display: inline-flex; gap: 4px; align-items: center; width: fit-content; margin-top: 4px; padding: 2px 10px; border-radius: 999px; background: #F3EEFF; color: #5B21B6; font-size: 12.5px; font-weight: 600; }
        .fee-schol-mini { color: #6D28D9; }

        /* course eligibility (12th) — compact */
        .elig-card { border-radius: 14px; background: #F5F7FF; border: 1px solid #D6DCF7; padding: 8px 12px; }
        .elig-head { display: flex; align-items: center; gap: 8px 10px; flex-wrap: wrap; cursor: pointer; list-style: none; min-height: 34px; }
        .elig-head::-webkit-details-marker { display: none; }
        .elig-head .ico { width: 26px; height: 26px; border-radius: 8px; background: var(--grad); color: #fff; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .elig-head b { font-weight: 800; font-size: 14px; }
        .elig-streams { display: inline-flex; flex-wrap: wrap; gap: 4px; }
        .elig-toggle { margin-left: auto; font-size: 13px; font-weight: 700; color: var(--brand); display: inline-flex; align-items: center; gap: 6px; }
        .elig-toggle::after { content: ""; width: 7px; height: 7px; border-right: 2px solid currentColor; border-bottom: 2px solid currentColor; transform: rotate(45deg) translateY(-2px); transition: transform .2s ease; }
        .elig-card[open] .elig-toggle::after { transform: rotate(-135deg) translateY(-2px); }
        .elig-card .when-open, .elig-card[open] .when-closed { display: none; }
        .elig-card[open] .when-open { display: inline; }
        .elig-list { display: flex; flex-direction: column; gap: 6px; margin: 8px 0 4px; animation: rise .3s var(--ease); }
        .elig-item { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 10px; align-items: center; background: #fff; border: 1px solid var(--line); border-radius: 10px; padding: 7px 10px; }
        .elig-code { display: inline-flex; align-items: center; height: 22px; padding: 0 8px; border-radius: 6px; background: var(--ink); color: #fff; font-weight: 800; font-size: 11px; letter-spacing: .04em; }
        .elig-subjects { display: flex; flex-wrap: wrap; gap: 4px 6px; align-items: center; }
        .elig-pill { display: inline-flex; gap: 4px; align-items: center; padding: 1px 8px; border-radius: 999px; background: var(--ok-bg); color: var(--ok); font-size: 12px; font-weight: 600; }
        .elig-pill b { font-weight: 800; }
        .elig-pill-agg { background: var(--info-bg); color: var(--info); }
        .elig-board { font-size: 12px; color: var(--muted); }
        .elig-marks { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(190px, 100%), 1fr)); gap: 12px; }
        .elig-mark { display: flex; flex-direction: column; gap: 7px; padding: 12px; border: 1px solid var(--line); border-radius: 14px; background: #FAFBFE; transition: border-color .2s ease, background-color .2s ease; }
        .elig-mark.is-ok { border-color: var(--ok-line); background: #F0FAF5; }
        .elig-mark.is-bad { border-color: var(--danger-line); background: #FFF6F4; }
        .elig-mark-name { font-size: 14px; font-weight: 700; display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
        .elig-mark-name .muted { font-weight: 600; font-size: 12px; }
        .elig-mark-result { font-size: 12.5px; font-weight: 700; }
        .elig-mark.is-ok .elig-mark-result { color: var(--ok); }
        .elig-mark.is-bad .elig-mark-result { color: var(--danger); }

        /* documents */
        .docrow { display: grid; grid-template-columns: 46px minmax(0, 1fr) auto; gap: 16px; align-items: center; padding: 16px 24px; border-bottom: 1px solid var(--line-2); transition: background-color .15s ease; }
        .docrow:hover { background: rgba(var(--brand-rgb), .04); }
        .docrow:last-child { border-bottom: 0; }
        .doc-icon { width: 46px; height: 46px; border-radius: 12px; background: var(--brand-soft); color: var(--brand); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; overflow: hidden; text-decoration: none; }
        .doc-icon img { width: 100%; height: 100%; object-fit: cover; }
        .dropzone { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 12px 16px; border: 1.5px dashed #A9B3D3; border-radius: 14px; background: #FAFBFF; text-align: center; cursor: pointer; transition: border-color .15s ease, background-color .15s ease, transform .15s ease; }
        .dropzone:hover { border-color: var(--brand); background: #EFEFF9; transform: translateY(-1px); }
        .dropzone svg { color: var(--brand); }
        .visually-hidden { position: absolute !important; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

        /* fees */
        .feerow { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 16px; align-items: center; padding: 15px 24px; border-bottom: 1px solid var(--line-2); transition: background-color .15s ease; }
        .feerow:hover { background: rgba(var(--brand-rgb), .04); }
        .feerow:last-child { border-bottom: 0; }
        .fee-all summary { cursor: pointer; font-size: 13px; font-weight: 700; color: var(--brand); list-style: none; display: inline-flex; align-items: center; gap: 6px; }
        .fee-all summary::-webkit-details-marker { display: none; }
        .fee-all summary::after { content: ""; width: 7px; height: 7px; border-right: 2px solid currentColor; border-bottom: 2px solid currentColor; transform: rotate(45deg) translateY(-2px); transition: transform .2s ease; }
        .fee-all[open] summary::after { transform: rotate(-135deg) translateY(-2px); }
        .feebar { height: 10px; border-radius: 999px; background: #E1E6F2; overflow: hidden; }
        .feebar > span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #1F9D62, #3CC08A); transition: width .6s var(--ease); }

        /* timeline / track */
        .track { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 14px; }
        .track li { display: flex; flex-direction: column; gap: 10px; }
        .track .bar { height: 6px; border-radius: 999px; background: #E1E6F2; overflow: hidden; }
        .track .bar > span { display: block; height: 100%; border-radius: 999px; }
        .track .done .bar > span { width: 100%; background: #1F9D62; }
        .track .active .bar > span { width: 50%; background: var(--grad); }
        .track .problem .bar > span { width: 50%; background: #B42318; }
        .track .t-head { display: flex; gap: 10px; align-items: center; font-weight: 800; }
        .track .todo .t-head { color: var(--ink-2); font-weight: 700; }
        .track .t-text { font-size: 13px; line-height: 19px; color: var(--muted); }
        .dot { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 13px; font-weight: 800; }
        .dot.done { background: #1F9D62; color: #fff; }
        .dot.active { border: 3px solid var(--brand); background: #EFEFF9; animation: pulse 1.8s ease-out infinite; }
        .dot.todo { border: 2px solid #C5CCDF; color: var(--muted); }
        .dot.problem { border: 3px solid #B42318; background: var(--danger-bg); animation: pulseRed 1.8s ease-out infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(var(--brand-rgb), .45); } 70% { box-shadow: 0 0 0 10px rgba(var(--brand-rgb), 0); } 100% { box-shadow: 0 0 0 0 rgba(var(--brand-rgb), 0); } }
        @keyframes pulseRed { 0% { box-shadow: 0 0 0 0 rgba(180, 35, 24, .4); } 70% { box-shadow: 0 0 0 10px rgba(180, 35, 24, 0); } 100% { box-shadow: 0 0 0 0 rgba(180, 35, 24, 0); } }
        .lrow { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 8px; border-bottom: 1px solid var(--line-2); border-radius: 8px; transition: background-color .15s ease; }
        .lrow:last-child { border-bottom: 0; }
        .lrow:hover { background: rgba(var(--brand-rgb), .05); }

        /* motion */
        .rise { animation: rise .7s var(--ease) both; }
        .flow-main > *, .stagger > * { animation: rise .6s var(--ease) both; }
        .flow-main > :nth-child(2), .stagger > :nth-child(2) { animation-delay: .06s; }
        .flow-main > :nth-child(3), .stagger > :nth-child(3) { animation-delay: .12s; }
        .flow-main > :nth-child(4), .stagger > :nth-child(4) { animation-delay: .18s; }
        .flow-main > :nth-child(n+5), .stagger > :nth-child(n+5) { animation-delay: .24s; }
        .flow-side { animation: rise .6s var(--ease) .05s both; }
        .lift { transition: transform .2s ease, box-shadow .2s ease; }
        .lift:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); border-color: #CBD3F5; }
        @keyframes rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
        @keyframes pop { from { transform: scale(.4); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        /* top progress bar (saving / next page loading) */
        #p-progress { position: fixed; top: 0; left: 0; z-index: 70; height: 3px; width: 0; opacity: 0; pointer-events: none;
            background: linear-gradient(90deg, #F7941D, #F26522, #2D2B7F); box-shadow: 0 0 10px rgba(242, 101, 34, .6); }
        #p-progress.on { opacity: 1; width: 82%; transition: width 6s cubic-bezier(.1, .7, .2, 1), opacity .15s ease; }
        #p-progress.done { opacity: 0; width: 100%; transition: width .2s ease, opacity .3s ease .15s; }

        /* toast */
        #portal-toasts { position: fixed; top: 84px; right: 16px; z-index: 60; display: flex; flex-direction: column; gap: 8px; max-width: min(380px, calc(100vw - 32px)); }
        .p-toast { background: rgba(11, 23, 51, .92); -webkit-backdrop-filter: blur(14px); backdrop-filter: blur(14px); color: #fff; padding: 13px 16px; border-radius: 14px; border: 1px solid rgba(255, 255, 255, .14); box-shadow: 0 14px 36px rgba(0, 0, 0, .3); font-size: 14px; font-weight: 600; animation: toastIn .35s var(--ease); }
        .p-toast.success { background: rgba(11, 107, 67, .94); } .p-toast.danger { background: rgba(160, 31, 20, .94); } .p-toast.warning { background: rgba(138, 75, 0, .94); }
        @keyframes toastIn { from { opacity: 0; transform: translateX(24px); } to { opacity: 1; transform: none; } }

        @media (max-width: 1000px) {
            .hide-md { display: none !important; }
            .p-footer .foot { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
            .flow { grid-template-columns: minmax(0, 1fr); }
            .flow-side { position: static; }
            .steps { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 6px; }
            .steps li > a, .steps li > div { grid-template-columns: minmax(0, 1fr); justify-items: center; text-align: center; gap: 6px; padding: 8px 4px; }
            .steps .tag, .side-help { display: none; }
            .steps .name { font-size: 12.5px; line-height: 16px; }
            .track { grid-template-columns: minmax(0, 1fr); }
        }
        @media (max-width: 760px) {
            .hide-sm { display: none !important; }
            .main { padding: 28px 16px 56px; }
            .p-header .wrap { padding: 0 16px; min-height: 64px; }
            .p-menu-btn { display: inline-flex; }
            .page-head::before { top: -200px; bottom: -96px; }
            .p-footer .foot { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 28px 20px; padding-top: 40px; padding-bottom: 28px; }
            .foot-brand { grid-column: 1 / -1; }
            .p-footer .wrap { padding-left: 16px; padding-right: 16px; }
            .card { padding: 20px; border-radius: 20px; }
            .docrow, .feerow { padding: 14px 18px; }
            .actionbar { bottom: 10px; padding: 10px; flex-wrap: nowrap; }
            .actionbar > .btn { flex-shrink: 0; }
            .actionbar .group { flex: 1 1 auto; flex-wrap: wrap; min-width: 0; gap: 8px; }
            .actionbar .group .btn { flex: 1 1 auto; min-width: 0; padding: 0 14px; min-height: 46px; }
            .flow-side .fee-side { display: none; }
            .logo-text small { display: none; }
            .logo-text b { font-size: 15px; max-width: 46vw; }
            .logo-img { height: 32px; }
            .logo-inst { display: none; }
            [data-signout-sm] { white-space: nowrap; padding: 8px; }
            .elig-item { grid-template-columns: minmax(0, 1fr); }
            .elig-code { justify-self: start; }
        }
        @media (max-width: 420px) { .p-footer .foot { grid-template-columns: minmax(0, 1fr); } }
        @media (max-width: 600px) { .feerow { grid-template-columns: minmax(0, 1fr) auto; } .docrow { grid-template-columns: 42px minmax(0, 1fr); } .docrow > :last-child { grid-column: 1 / -1; justify-content: flex-start !important; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation: none !important; transition: none !important; scroll-behavior: auto !important; } }
    </style>
    @livewireStyles
</head>
<body>
<div class="page">
    @php
        $openApply = \App\Support\Settings::bool('portal.registration_open');
        $footInst = $portalStudent ? $portalStudent->institute : null;
        $contact = [
            'address' => $footInst ? trim($footInst->address . ' ' . $footInst->city) : setting('general.address'),
            'phone' => $footInst ? $footInst->phone : setting('general.support_phone'),
            'email' => $footInst ? $footInst->email : setting('general.support_email'),
        ];
        $navLinks = $portalStudent
            ? [[route('portal.status'), 'My application', 'portal.status'], [route('portal.notifications'), 'Notifications', 'portal.notifications'], ['#contact', 'Contact', null]]
            : [[route('portal.home'), 'Home', 'portal.home'], [route('portal.home') . '#how', 'How it works', null], ['#contact', 'Contact', null]];
    @endphp
    <header class="p-header glass-dark">
        <div class="wrap">
            <a href="{{ $portalStudent ? route('portal.status') : route('portal.home') }}" class="logo" aria-label="{{ $brand }} — admissions home">
                <img src="{{ asset('brand/logo-white.png') }}" class="logo-img" alt="Sha-Shib Campus" width="261" height="42">
                @if($portalStudent && $brand)<span class="logo-inst">{{ $brand }}</span>@endif
            </a>
            @if($portalStudent)
                <nav class="p-nav" aria-label="Account">
                    <a href="{{ route('portal.status') }}" class="p-nav-link hide-sm" @if(request()->routeIs('portal.status')) aria-current="page" @endif>My application</a>
                    <a href="#contact" class="p-nav-link hide-md">Help</a>
                    @php $portalUnread = $portalUser->unreadNotifications()->count(); @endphp
                    <a href="{{ route('portal.notifications') }}" class="p-bell" title="Notifications" aria-label="Notifications{{ $portalUnread ? " ({$portalUnread} unread)" : '' }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6"/><path d="M9 17v1a3 3 0 0 0 6 0v-1"/></svg>
                        <span class="p-bell-badge" id="portalBellBadge" @unless($portalUnread) hidden @endunless>{{ $portalUnread > 99 ? '99+' : $portalUnread }}</span>
                    </a>
                    <span class="p-nav-sep hide-sm"></span>
                    <span class="p-user hide-sm"><b>{{ $portalStudent->full_name }}</b><a href="{{ route('portal.logout') }}" data-signout>Sign out</a></span>
                    <span class="avatar hide-sm" aria-hidden="true">{{ $portalStudent->initials }}</span>
                    <button type="button" class="p-menu-btn" aria-expanded="false" aria-controls="p-mnav" aria-label="Menu"><span class="bars"></span></button>
                </nav>
            @else
                <nav class="p-nav" aria-label="Main">
                    @foreach($navLinks as [$href, $label, $routeName])
                        <a href="{{ $href }}" class="p-nav-link hide-sm" @if($routeName && request()->routeIs($routeName)) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                    <span class="p-nav-sep hide-sm"></span>
                    @unless(request()->routeIs('portal.login'))
                        <a href="{{ route('portal.login') }}" class="btn btn-ghost btn-sm hide-sm">Sign in</a>
                    @endunless
                    @if($openApply && !request()->routeIs('portal.register'))
                        <a href="{{ route('portal.register') }}" class="btn btn-primary btn-sm">Apply now</a>
                    @endif
                    <button type="button" class="p-menu-btn" aria-expanded="false" aria-controls="p-mnav" aria-label="Menu"><span class="bars"></span></button>
                </nav>
            @endif
        </div>
        <nav class="p-mnav" id="p-mnav" aria-label="Menu">
            @if($portalStudent)
                <div style="display: flex; align-items: center; gap: 12px; padding: 10px 12px 12px; color: #fff;">
                    <span class="avatar" aria-hidden="true">{{ $portalStudent->initials }}</span>
                    <span style="display: flex; flex-direction: column; line-height: 1.25; min-width: 0;"><b style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $portalStudent->full_name }}</b><small style="color: rgba(255, 255, 255, .65);">{{ $portalStudent->email ?? $portalUser->email }}</small></span>
                </div>
            @endif
            @foreach($navLinks as [$href, $label, $routeName])
                <a href="{{ $href }}" @if($routeName && request()->routeIs($routeName)) aria-current="page" @endif>{{ $label }}<span aria-hidden="true">&rsaquo;</span></a>
            @endforeach
            @if($portalStudent)
                <a href="{{ route('portal.logout') }}" data-signout style="color: var(--gold-soft);">Sign out<span aria-hidden="true">&rsaquo;</span></a>
            @else
                @unless(request()->routeIs('portal.login'))<a href="{{ route('portal.login') }}">Sign in<span aria-hidden="true">&rsaquo;</span></a>@endunless
                @if($openApply && !request()->routeIs('portal.register'))<a href="{{ route('portal.register') }}" class="cta">Start your application</a>@endif
            @endif
        </nav>
    </header>

    {{ $slot }}

    <footer class="p-footer" id="contact">
        <div class="wrap foot">
            <div class="foot-brand">
                <span class="logo" style="pointer-events: none; flex-direction: column; align-items: flex-start; gap: 0;">
                    <img src="{{ asset('brand/logo-white.png') }}" class="logo-img" alt="Sha-Shib Campus" width="286" height="46" loading="lazy">
                    <span class="foot-org">{{ $portalStudent ? $brand : (setting('general.organisation') ?: 'Sha-Shib Group') }}</span>
                </span>
                <p>Online admissions in one place — apply, upload your documents, pay your fees and follow your verification from anywhere.</p>
                <div class="foot-trust">
                    <span><svg width="14" height="14" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 2.5 L16 5 V9.5 C16 13.3 13.4 16.1 10 17.5 C6.6 16.1 4 13.3 4 9.5 V5 Z"></path></svg>Private &amp; secure</span>
                    <span><svg width="14" height="14" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="7.5"></circle><path d="M10 6 V10 L12.5 11.5"></path></svg>Save &amp; continue anytime</span>
                </div>
            </div>
            <div>
                <h3>{{ $portalStudent ? 'My application' : 'Admissions' }}</h3>
                <ul>
                    @if($portalStudent)
                        <li><a href="{{ route('portal.status') }}">Application status</a></li>
                        <li><a href="{{ route('portal.details') }}">Your details</a></li>
                        <li><a href="{{ route('portal.academic') }}">Academic details</a></li>
                        <li><a href="{{ route('portal.documents') }}">Documents</a></li>
                        <li><a href="{{ route('portal.payment') }}">Fees &amp; payment</a></li>
                    @else
                        @if($openApply)<li><a href="{{ route('portal.register') }}">Apply now</a></li>@endif
                        <li><a href="{{ route('portal.login') }}">Continue an application</a></li>
                        <li><a href="{{ route('portal.home') }}#how">How admission works</a></li>
                        <li><a href="{{ route('portal.login') }}">Track your status</a></li>
                    @endif
                </ul>
            </div>
            <div>
                <h3>Keep ready</h3>
                <ul>
                    <li>Passport-size photo</li>
                    <li>Class 10 &amp; 12 marksheets</li>
                    <li>Medical fitness certificate</li>
                    <li>JPG, PNG or PDF · up to 5 MB</li>
                </ul>
            </div>
            <div>
                <h3>Contact</h3>
                <ul class="foot-contact">
                    @if($contact['address'])
                        <li><svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17.5 C10 17.5 4.5 12.4 4.5 8.3 A5.5 5.5 0 0 1 15.5 8.3 C15.5 12.4 10 17.5 10 17.5 Z"></path><circle cx="10" cy="8.3" r="2"></circle></svg><span>{{ $contact['address'] }}</span></li>
                    @endif
                    @if($contact['phone'])
                        <li><svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4.5 3 H7.5 L9 7 L7 8.3 C7.9 10.2 9.8 12.1 11.7 13 L13 11 L17 12.5 V15.5 C17 16.3 16.3 17 15.5 17 C9.2 16.6 3.4 10.8 3 4.5 C3 3.7 3.7 3 4.5 3 Z"></path></svg><a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a></li>
                    @endif
                    @if($contact['email'])
                        <li><svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="4.5" width="15" height="11" rx="2"></rect><path d="M3 5.5 L10 11 L17 5.5"></path></svg><a href="mailto:{{ $contact['email'] }}" style="word-break: break-all;">{{ $contact['email'] }}</a></li>
                    @endif
                    @unless(array_filter($contact))
                        <li style="display: block;">Questions about your application? Contact the admissions office of your institute.</li>
                    @endunless
                </ul>
            </div>
        </div>
        <div class="foot-bottom">
            <div class="wrap">
                <span>© {{ now()->year }} {{ setting('general.organisation') ?: $brand }}. All rights reserved.</span>
                <span>{{ setting('general.footer_text') ?: 'Admissions portal' }}</span>
            </div>
        </div>
    </footer>
</div>

<div id="p-progress" aria-hidden="true"></div>
<div id="portal-toasts" aria-live="polite"></div>
@livewireScripts
<script>
    // Notifications page marks items read → keep the header bell count in step
    window.addEventListener('portal-unread', function (e) {
        const badge = document.getElementById('portalBellBadge');
        if (!badge) { return; }
        const n = Number(e.detail.count) || 0;
        badge.textContent = n > 99 ? '99+' : n;
        badge.hidden = n === 0;
    });
</script>
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
    // Action buttons (Save, Submit …): keep focus in the field being edited until the click itself, so the
    // field's value and the action reach the server in ONE request (Livewire batches them). Without this the
    // field is sent on mouse-down and the action only after that round trip — slower, and easy to "lose".
    document.addEventListener('mousedown', function (e) {
        var btn = e.target.closest('.actionbar button, [data-one-request]');
        var field = document.activeElement;
        if (btn && field && field.matches('input:not([type=file]), select, textarea')) e.preventDefault();
    });
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.actionbar button, [data-one-request]');
        var field = document.activeElement;
        if (btn && field && field.matches('input:not([type=file]), select, textarea')) field.blur();
    }, true);

    // Slim progress bar at the top while saving and while the next page loads.
    (function () {
        var bar = document.getElementById('p-progress'), timer = null, busy = 0;
        function start(delay) {
            clearTimeout(timer);
            timer = setTimeout(function () { bar.className = 'on'; }, delay);
        }
        function done() {
            clearTimeout(timer);
            if (bar.className === 'on') { bar.className = 'done'; setTimeout(function () { if (!busy) bar.className = ''; }, 350); }
        }
        document.addEventListener('livewire:load', function () {
            // Only for the action buttons (Save and continue, Submit, Pay, Sign in) — not for typing or
            // choosing from a dropdown, which update quietly in the background.
            var isAction = function (message) {
                return (message.updateQueue || []).some(function (u) {
                    return u.type === 'callMethod' && /^(save|saveAndPay|submit|pay|login|confirmed)$/.test(u.method || (u.payload && u.payload.method) || '');
                });
            };
            Livewire.hook('message.sent', function (message) { if (isAction(message)) { message._bar = true; busy++; start(150); } });
            Livewire.hook('message.failed', function (message) { if (!message._bar) return; busy = Math.max(0, busy - 1); if (!busy) done(); });
            Livewire.hook('message.received', function (message) {
                if (!message._bar) return;
                busy = Math.max(0, busy - 1);
                // a redirect follows: keep the bar running until the next page appears
                var r = message.response && message.response.effects && message.response.effects.redirect;
                if (r) { start(0); return; }
                if (!busy) done();
            });
        });
        // safety: never leave the bar running (e.g. a redirect to a page that opens elsewhere)
        setInterval(function () { if (bar.className === 'on' && !busy && !document.hidden) { setTimeout(function () { if (!busy) done(); }, 8000); } }, 8000);

        window.addEventListener('pageshow', function () { busy = 0; done(); bar.className = ''; });
    })();

    // Missing / wrong fields after Save, Submit or Pay: move the page to the first one and put the
    // cursor in it (the message shows under the field itself).
    (function () {
        function firstError(root) {
            var err = root.querySelector('.error');
            if (!err) return null;
            var box = err.closest('.field') || err.closest('.elig-mark') || err.parentElement;
            return { box: box, input: box.querySelector('.input, select, textarea, input:not([type=hidden])') };
        }
        function jump(item) {
            var top = item.box.getBoundingClientRect().top + window.pageYOffset - 100;
            window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
            if (item.input) setTimeout(function () { item.input.focus({ preventScroll: true }); }, 400);
        }
        document.addEventListener('livewire:load', function () {
            var acted = false;
            Livewire.hook('message.sent', function (message) {
                acted = (message.updateQueue || []).some(function (u) { return u.type === 'callMethod' && /^(save|saveAndPay|submit|pay|login)$/.test(u.method || (u.payload && u.payload.method) || ''); });
            });
            Livewire.hook('message.processed', function (message, component) {
                if (!acted) return;
                acted = false;
                var item = firstError(component.el);
                if (item) { jump(item); return; }
                var alert = component.el.querySelector('.notice.danger[role="alert"]');
                if (alert) alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });
    })();

    // Header: deeper shadow once the page scrolls; menu button on phones
    (function () {
        var header = document.querySelector('.p-header'), btn = header && header.querySelector('.p-menu-btn');
        if (!header) return;
        var onScroll = function () { header.classList.toggle('scrolled', window.pageYOffset > 8); };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
        if (!btn) return;
        btn.addEventListener('click', function () {
            var open = header.classList.toggle('open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && header.classList.contains('open')) { header.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); btn.focus(); } });
        header.querySelectorAll('.p-mnav a').forEach(function (a) { a.addEventListener('click', function () { header.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); }); });
    })();

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
