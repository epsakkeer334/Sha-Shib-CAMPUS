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
    <meta name="theme-color" content="#0B1733">
    <title>{{ $title ?? 'Admissions' }} · {{ $brand }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="preload" as="image" href="{{ asset('portal/campus-bg.jpg') }}">
    <style>
        /* ───────── Admissions portal design system (glass on campus photo) ───────── */
        :root {
            --bg: #F2F5FC; --surface: #FFFFFF; --ink: #0B1733; --ink-2: #33415F; --muted: #55627F;
            --line: #DCE1EE; --line-2: #E8ECF5; --field: rgba(104, 120, 170, .38);
            --brand: #3B4FE0; --brand-2: #6A3FD9; --brand-ink: #2338A8; --brand-dark: #2A3BB8; --brand-soft: #E6ECFF; --brand-tint: #F2F4FF;
            --gold: #F2B544; --gold-soft: #F7CF7E;
            --ok-bg: #E3F6EC; --ok: #0B6B43; --ok-line: #A9DEC2;
            --info-bg: #E6ECFF; --info: #2338A8; --warn-bg: #FFF2DB; --warn: #8A4B00;
            --danger-bg: #FDE7E5; --danger: #A01F14; --danger-line: #F3C0B9; --danger-tint: #FFF4F2;
            --glass: rgba(255, 255, 255, .86); --glass-line: rgba(255, 255, 255, .72);
            --shadow: 0 24px 60px rgba(4, 10, 30, .30);
            --grad: linear-gradient(135deg, #3B4FE0 0%, #6A3FD9 100%);
            --ease: cubic-bezier(.2, .7, .2, 1);
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { margin: 0; background: #070F24; color: var(--ink); font: 15px/23px 'Plus Jakarta Sans', 'Segoe UI', system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
        a { color: var(--brand); transition: color .15s ease; } a:hover { color: var(--brand-dark); }
        input, select, textarea, button { font: inherit; }
        h1, h2, h3 { margin: 0; }
        .serif { font-family: inherit; }
        .mono { font-family: 'JetBrains Mono', ui-monospace, monospace; }
        .muted { color: var(--muted); }
        :focus-visible { outline: 3px solid var(--gold); outline-offset: 2px; }

        /* background: campus photo + navy overlay + soft brand glows, fixed behind everything */
        .page { position: relative; min-height: 100vh; display: flex; flex-direction: column; isolation: isolate; }
        .page::before { content: ""; position: fixed; inset: 0; z-index: -2; background: #0B1733 url('{{ asset('portal/campus-bg.jpg') }}') center 35% / cover no-repeat; transform: scale(1.06); animation: bgDrift 40s ease-in-out infinite alternate; }
        .page::after { content: ""; position: fixed; inset: 0; z-index: -1;
            background: radial-gradient(60% 50% at 10% 12%, rgba(116, 87, 255, .38), transparent 70%),
                        radial-gradient(40% 40% at 92% 6%, rgba(41, 191, 208, .20), transparent 70%),
                        radial-gradient(55% 45% at 50% 105%, rgba(242, 181, 68, .22), transparent 70%),
                        linear-gradient(180deg, rgba(6, 13, 34, .72) 0%, rgba(8, 17, 44, .80) 45%, rgba(6, 13, 34, .90) 100%); }
        @keyframes bgDrift { from { transform: scale(1.06) translate3d(0, 0, 0); } to { transform: scale(1.12) translate3d(-1.2%, -1%, 0); } }

        /* glass surfaces */
        .glass-dark { background: rgba(8, 16, 42, .58); -webkit-backdrop-filter: blur(18px) saturate(140%); backdrop-filter: blur(18px) saturate(140%); border: 1px solid rgba(255, 255, 255, .12); }

        /* header / footer */
        .p-header { position: sticky; top: 0; z-index: 30; border-width: 0 0 1px; }
        .p-header .wrap { max-width: 1200px; margin: 0 auto; padding: 0 24px; min-height: 72px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .logo { display: flex; align-items: center; gap: 12px; color: #fff; text-decoration: none; min-width: 0; }
        .logo:hover { color: #fff; }
        .logo-mark { width: 40px; height: 40px; border-radius: 12px; background: var(--grad); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 17px; flex-shrink: 0; box-shadow: 0 8px 20px rgba(59, 79, 224, .45); }
        .logo-text { display: flex; flex-direction: column; line-height: 1.15; min-width: 0; }
        .logo-text b { font-weight: 800; font-size: 16.5px; letter-spacing: -.01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .logo-text small { font-size: 11.5px; color: rgba(255, 255, 255, .7); letter-spacing: .04em; }
        .p-nav { display: flex; align-items: center; gap: 10px; }
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
        .p-footer { border-width: 1px 0 0; color: rgba(255, 255, 255, .78); }
        .p-footer .wrap { max-width: 1200px; margin: 0 auto; padding: 24px; display: flex; justify-content: space-between; align-items: center; gap: 12px 24px; flex-wrap: wrap; font-size: 13.5px; }
        .p-footer b { color: #fff; font-weight: 800; }

        /* layout */
        .main { flex-grow: 1; width: 100%; max-width: 960px; margin: 0 auto; padding: 40px 24px 72px; display: flex; flex-direction: column; gap: 24px; }
        .main.wide { max-width: 1200px; }
        .page-head { display: flex; flex-direction: column; gap: 8px; color: #fff; }
        .eyebrow { font-size: 12.5px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: var(--gold-soft); }
        .title { font-size: clamp(28px, 3.4vw, 40px); line-height: 1.15; font-weight: 800; letter-spacing: -.02em; color: #fff; text-wrap: balance; }
        .lead { color: rgba(255, 255, 255, .84); margin: 0; font-size: 16px; line-height: 25px; max-width: 640px; }
        .on-dark { color: rgba(255, 255, 255, .86); }
        .on-dark a { color: var(--gold-soft); font-weight: 700; } .on-dark a:hover { color: #fff; }
        .card { background: var(--glass); -webkit-backdrop-filter: blur(22px) saturate(150%); backdrop-filter: blur(22px) saturate(150%); border: 1px solid var(--glass-line); box-shadow: var(--shadow); border-radius: 22px; padding: 26px 28px; display: flex; flex-direction: column; gap: 20px; }
        .card.flush { padding: 0; gap: 0; overflow: hidden; }
        .card h2 { font-size: 17.5px; line-height: 24px; font-weight: 800; letter-spacing: -.01em; }
        .card-head { display: flex; align-items: center; gap: 14px; }
        .card-head .ico { width: 42px; height: 42px; border-radius: 12px; background: var(--brand-soft); color: var(--brand); display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .card-head .sub { font-size: 13.5px; color: var(--muted); }
        .card-head .end { margin-left: auto; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(240px, 100%), 1fr)); gap: 16px 18px; align-items: start; }
        .grid.narrow { grid-template-columns: repeat(auto-fit, minmax(min(180px, 100%), 1fr)); }
        .row-between { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
        .actionbar { position: sticky; bottom: 16px; z-index: 5; background: rgba(255, 255, 255, .80); -webkit-backdrop-filter: blur(20px) saturate(150%); backdrop-filter: blur(20px) saturate(150%); border: 1px solid var(--glass-line); box-shadow: 0 16px 40px rgba(4, 10, 30, .32); border-radius: 18px; padding: 12px 14px; display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
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
        .steps li > a:hover { background: rgba(59, 79, 224, .06); transform: translateX(2px); }
        .steps .num { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px; border: 1.5px solid #C5CCDF; color: var(--muted); }
        .steps .name { font-weight: 700; font-size: 14.5px; display: block; }
        .steps .tag { font-size: 12.5px; color: var(--muted); display: block; }
        .steps .done .num { background: var(--ok-bg); border-color: transparent; color: var(--ok); }
        .steps .done .tag { color: var(--ok); font-weight: 600; }
        .steps .current > div, .steps .current > a { background: #EEF1FF; border-color: #C9D1FF; }
        .steps .current .num { background: var(--grad); border-color: transparent; color: #fff; box-shadow: 0 6px 14px rgba(59, 79, 224, .35); }
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
            background-color: rgba(255, 255, 255, .62); box-shadow: inset 0 1px 0 rgba(255, 255, 255, .75);
            transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease; }
        select.input { appearance: none; -webkit-appearance: none; padding-right: 40px; cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 20 20' fill='none' stroke='%2355627F' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; }
        textarea.input { height: auto; padding: 12px 15px; resize: vertical; }
        .input:hover { border-color: #98A3C2; background-color: rgba(255, 255, 255, .74); }
        .input:focus { outline: none; border-color: var(--brand); background-color: rgba(255, 255, 255, .92); box-shadow: 0 0 0 4px rgba(59, 79, 224, .18); }
        .input::placeholder { color: #7A849E; }
        .input[disabled] { background-color: rgba(236, 239, 247, .7); color: var(--muted); border-color: var(--line); cursor: not-allowed; }
        .input.invalid { border: 1.5px solid #B42318; background-color: #FFF8F7; }
        .hint { font-size: 12.5px; color: var(--muted); line-height: 18px; }
        .error { font-size: 12.5px; color: var(--danger); font-weight: 600; line-height: 18px; }
        input[type="checkbox"] { width: 20px; height: 20px; accent-color: var(--brand); margin: 0; }

        /* buttons */
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 50px; padding: 0 24px; border-radius: 13px; border: 0; font-weight: 700; font-size: 15px; text-decoration: none; cursor: pointer; white-space: nowrap;
            transition: transform .15s ease, box-shadow .2s ease, background-color .15s ease, border-color .15s ease, filter .15s ease; }
        .btn:hover { transform: translateY(-1px); }
        .btn:active { transform: translateY(0); }
        .btn-primary { background: var(--grad); color: #fff; box-shadow: 0 10px 24px rgba(59, 79, 224, .35); }
        .btn-primary:hover { color: #fff; filter: brightness(1.07); box-shadow: 0 14px 32px rgba(59, 79, 224, .45); }
        .btn-secondary { background: rgba(255, 255, 255, .92); color: var(--ink); border: 1px solid #C5CCDF; }
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
        .seg { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 4px; padding: 4px; border-radius: 14px; background: rgba(255, 255, 255, .45); border: 1px solid rgba(104, 120, 170, .30); }
        .seg button { height: 40px; border: 0; border-radius: 10px; background: transparent; color: #3D4966; font-weight: 600; cursor: pointer; transition: background-color .15s ease, box-shadow .15s ease, color .15s ease; }
        .seg button:hover { color: var(--ink); }
        .seg button[aria-pressed="true"] { background: #fff; color: var(--ink); font-weight: 800; box-shadow: 0 2px 6px rgba(11, 23, 51, .12); }
        .choices { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(150px, 100%), 1fr)); gap: 10px; }
        .choices.wide { grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr)); }
        .choice { position: relative; display: flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: 13px 15px; border: 1px solid #C5CCDF; border-radius: 14px; background: rgba(255, 255, 255, .55); color: var(--ink); text-align: left; cursor: pointer;
            transition: border-color .15s ease, box-shadow .2s ease, background-color .15s ease, transform .15s ease; }
        .choice:hover { border-color: #98A3C2; background: rgba(255, 255, 255, .8); transform: translateY(-1px); }
        .choice[aria-pressed="true"] { border: 2px solid var(--brand); padding: 12px 14px; background: #EEF1FF; box-shadow: 0 0 0 4px rgba(59, 79, 224, .12); }
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
        .notice { border-radius: 18px; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; box-shadow: 0 16px 40px rgba(4, 10, 30, .22); font-size: 14.5px; }
        .notice.danger { background: rgba(255, 244, 242, .96); border: 1px solid var(--danger-line); color: #5E130B; }
        .notice.danger strong { color: #7A160D; }
        .notice.info { background: rgba(242, 244, 255, .95); border: 1px solid #C9D1FF; color: #1D2B6B; }
        .notice.ok { background: rgba(236, 249, 242, .96); border: 1px solid var(--ok-line); color: #0B4F33; }
        .card .notice { box-shadow: none; }

        /* scholarships (Module 2C) */
        .fee-schol { display: inline-flex; gap: 4px; align-items: center; width: fit-content; margin-top: 4px; padding: 2px 10px; border-radius: 999px; background: #F3EEFF; color: #5B21B6; font-size: 12.5px; font-weight: 600; }
        .fee-schol-mini { color: #6D28D9; }

        /* course eligibility (12th) — compact */
        .elig-card { border-radius: 14px; background: rgba(242, 244, 255, .8); border: 1px solid #D6DCF7; padding: 8px 12px; }
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
        .elig-item { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 10px; align-items: center; background: rgba(255, 255, 255, .78); border: 1px solid var(--line); border-radius: 10px; padding: 7px 10px; }
        .elig-code { display: inline-flex; align-items: center; height: 22px; padding: 0 8px; border-radius: 6px; background: var(--ink); color: #fff; font-weight: 800; font-size: 11px; letter-spacing: .04em; }
        .elig-subjects { display: flex; flex-wrap: wrap; gap: 4px 6px; align-items: center; }
        .elig-pill { display: inline-flex; gap: 4px; align-items: center; padding: 1px 8px; border-radius: 999px; background: var(--ok-bg); color: var(--ok); font-size: 12px; font-weight: 600; }
        .elig-pill b { font-weight: 800; }
        .elig-pill-agg { background: var(--info-bg); color: var(--info); }
        .elig-board { font-size: 12px; color: var(--muted); }
        .elig-marks { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(190px, 100%), 1fr)); gap: 12px; }
        .elig-mark { display: flex; flex-direction: column; gap: 7px; padding: 12px; border: 1px solid var(--line); border-radius: 14px; background: rgba(255, 255, 255, .6); transition: border-color .2s ease, background-color .2s ease; }
        .elig-mark.is-ok { border-color: var(--ok-line); background: rgba(236, 249, 242, .85); }
        .elig-mark.is-bad { border-color: var(--danger-line); background: rgba(255, 244, 242, .9); }
        .elig-mark-name { font-size: 14px; font-weight: 700; display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
        .elig-mark-name .muted { font-weight: 600; font-size: 12px; }
        .elig-mark-result { font-size: 12.5px; font-weight: 700; }
        .elig-mark.is-ok .elig-mark-result { color: var(--ok); }
        .elig-mark.is-bad .elig-mark-result { color: var(--danger); }

        /* documents */
        .docrow { display: grid; grid-template-columns: 46px minmax(0, 1fr) auto; gap: 16px; align-items: center; padding: 16px 24px; border-bottom: 1px solid var(--line-2); transition: background-color .15s ease; }
        .docrow:hover { background: rgba(59, 79, 224, .04); }
        .docrow:last-child { border-bottom: 0; }
        .doc-icon { width: 46px; height: 46px; border-radius: 12px; background: var(--brand-soft); color: var(--brand); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; overflow: hidden; text-decoration: none; }
        .doc-icon img { width: 100%; height: 100%; object-fit: cover; }
        .dropzone { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 12px 16px; border: 1.5px dashed #A9B3D3; border-radius: 14px; background: rgba(255, 255, 255, .5); text-align: center; cursor: pointer; transition: border-color .15s ease, background-color .15s ease, transform .15s ease; }
        .dropzone:hover { border-color: var(--brand); background: rgba(238, 241, 255, .85); transform: translateY(-1px); }
        .dropzone svg { color: var(--brand); }
        .visually-hidden { position: absolute !important; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

        /* fees */
        .feerow { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 16px; align-items: center; padding: 15px 24px; border-bottom: 1px solid var(--line-2); transition: background-color .15s ease; }
        .feerow:hover { background: rgba(59, 79, 224, .04); }
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
        .dot.active { border: 3px solid var(--brand); background: #EEF1FF; animation: pulse 1.8s ease-out infinite; }
        .dot.todo { border: 2px solid #C5CCDF; color: var(--muted); }
        .dot.problem { border: 3px solid #B42318; background: var(--danger-bg); animation: pulseRed 1.8s ease-out infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(59, 79, 224, .45); } 70% { box-shadow: 0 0 0 10px rgba(59, 79, 224, 0); } 100% { box-shadow: 0 0 0 0 rgba(59, 79, 224, 0); } }
        @keyframes pulseRed { 0% { box-shadow: 0 0 0 0 rgba(180, 35, 24, .4); } 70% { box-shadow: 0 0 0 10px rgba(180, 35, 24, 0); } 100% { box-shadow: 0 0 0 0 rgba(180, 35, 24, 0); } }
        .lrow { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 8px; border-bottom: 1px solid var(--line-2); border-radius: 8px; transition: background-color .15s ease; }
        .lrow:last-child { border-bottom: 0; }
        .lrow:hover { background: rgba(59, 79, 224, .05); }

        /* motion */
        .rise { animation: rise .7s var(--ease) both; }
        .flow-main > *, .stagger > * { animation: rise .6s var(--ease) both; }
        .flow-main > :nth-child(2), .stagger > :nth-child(2) { animation-delay: .06s; }
        .flow-main > :nth-child(3), .stagger > :nth-child(3) { animation-delay: .12s; }
        .flow-main > :nth-child(4), .stagger > :nth-child(4) { animation-delay: .18s; }
        .flow-main > :nth-child(n+5), .stagger > :nth-child(n+5) { animation-delay: .24s; }
        .flow-side { animation: rise .6s var(--ease) .05s both; }
        .lift { transition: transform .2s ease, box-shadow .2s ease; }
        .lift:hover { transform: translateY(-3px); box-shadow: 0 30px 64px rgba(4, 10, 30, .38); }
        @keyframes rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
        @keyframes pop { from { transform: scale(.4); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        /* toast */
        #portal-toasts { position: fixed; top: 84px; right: 16px; z-index: 60; display: flex; flex-direction: column; gap: 8px; max-width: min(380px, calc(100vw - 32px)); }
        .p-toast { background: rgba(11, 23, 51, .92); -webkit-backdrop-filter: blur(14px); backdrop-filter: blur(14px); color: #fff; padding: 13px 16px; border-radius: 14px; border: 1px solid rgba(255, 255, 255, .14); box-shadow: 0 14px 36px rgba(0, 0, 0, .3); font-size: 14px; font-weight: 600; animation: toastIn .35s var(--ease); }
        .p-toast.success { background: rgba(11, 107, 67, .94); } .p-toast.danger { background: rgba(160, 31, 20, .94); } .p-toast.warning { background: rgba(138, 75, 0, .94); }
        @keyframes toastIn { from { opacity: 0; transform: translateX(24px); } to { opacity: 1; transform: none; } }

        @media (max-width: 1000px) {
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
            .card { padding: 20px; border-radius: 20px; }
            .docrow, .feerow { padding: 14px 18px; }
            .actionbar { bottom: 10px; padding: 10px; flex-wrap: nowrap; }
            .actionbar > .btn { flex-shrink: 0; }
            .actionbar .group { flex: 1 1 auto; flex-wrap: wrap; min-width: 0; gap: 8px; }
            .actionbar .group .btn { flex: 1 1 auto; min-width: 0; padding: 0 14px; min-height: 46px; }
            .flow-side .fee-side { display: none; }
            .logo-text small { display: none; }
            .logo-text b { font-size: 15px; max-width: 46vw; }
            [data-signout-sm] { white-space: nowrap; padding: 8px; }
            .elig-item { grid-template-columns: minmax(0, 1fr); }
            .elig-code { justify-self: start; }
        }
        @media (max-width: 600px) { .feerow { grid-template-columns: minmax(0, 1fr) auto; } .docrow { grid-template-columns: 42px minmax(0, 1fr); } .docrow > :last-child { grid-column: 1 / -1; justify-content: flex-start !important; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation: none !important; transition: none !important; scroll-behavior: auto !important; } }
    </style>
    @livewireStyles
</head>
<body>
<div class="page">
    <header class="p-header glass-dark">
        <div class="wrap">
            <a href="{{ $portalStudent ? route('portal.status') : route('portal.home') }}" class="logo" aria-label="{{ $brand }} — admissions home">
                <span class="logo-mark" aria-hidden="true">{{ $brandInitial }}</span>
                <span class="logo-text"><b>{{ $brand }}</b><small>Student Onboarding</small></span>
            </a>
            @if($portalStudent)
                <nav class="p-nav" aria-label="Account">
                    <a href="{{ route('portal.status') }}" class="p-nav-link hide-sm" @if(request()->routeIs('portal.status')) aria-current="page" @endif>My application</a>
                    @php $portalUnread = $portalUser->unreadNotifications()->count(); @endphp
                    <a href="{{ route('portal.notifications') }}" class="p-bell" title="Notifications" aria-label="Notifications{{ $portalUnread ? " ({$portalUnread} unread)" : '' }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6"/><path d="M9 17v1a3 3 0 0 0 6 0v-1"/></svg>
                        <span class="p-bell-badge" id="portalBellBadge" @unless($portalUnread) hidden @endunless>{{ $portalUnread > 99 ? '99+' : $portalUnread }}</span>
                    </a>
                    <span class="p-user hide-sm"><b>{{ $portalStudent->full_name }}</b><a href="{{ route('portal.logout') }}" data-signout>Sign out</a></span>
                    <span class="avatar" aria-hidden="true">{{ $portalStudent->initials }}</span>
                    <a href="{{ route('portal.logout') }}" class="p-nav-link" style="display: none;" data-signout data-signout-sm>Sign out</a>
                </nav>
            @else
                <nav class="p-nav" aria-label="Account">
                    @unless(request()->routeIs('portal.login'))
                        <a href="{{ route('portal.login') }}" class="btn btn-ghost btn-sm">Sign in</a>
                    @endunless
                    @if(\App\Support\Settings::bool('portal.registration_open') && !request()->routeIs('portal.register'))
                        <a href="{{ route('portal.register') }}" class="btn btn-primary btn-sm">Apply now</a>
                    @endif
                </nav>
            @endif
        </div>
    </header>

    {{ $slot }}

    <footer class="p-footer glass-dark">
        <div class="wrap">
            <b>{{ $brand }}</b>
            @if($portalStudent && $portalStudent->institute)
                @php $footInst = $portalStudent->institute; @endphp
                <span>{{ collect([trim($footInst->address . ' ' . $footInst->city), $footInst->phone, $footInst->email])->filter()->implode(' · ') }}</span>
            @else
                <span>Admissions portal</span>
            @endif
            <span>© {{ now()->year }} {{ $brand }}</span>
        </div>
    </footer>
</div>
<style>@media (max-width: 760px) { [data-signout-sm] { display: inline-flex !important; } }</style>

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
