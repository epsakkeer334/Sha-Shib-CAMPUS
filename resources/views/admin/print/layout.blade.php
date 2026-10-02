<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --ink: #17201B; --muted: #56605A; --line: #DCDFD8; --brand: #0E6B55; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #F4F5F2; color: var(--ink); font: 14px/20px 'IBM Plex Sans', 'Segoe UI', system-ui, sans-serif; }
        .sheet { max-width: 800px; margin: 24px auto; background: #fff; border: 1px solid var(--line); border-radius: 8px; padding: 40px; }
        .toolbar { max-width: 800px; margin: 24px auto 0; display: flex; justify-content: flex-end; gap: 8px; }
        .toolbar button { height: 40px; padding: 0 16px; border-radius: 8px; border: 0; background: var(--brand); color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border-bottom: 2px solid var(--brand); padding-bottom: 16px; margin-bottom: 24px; }
        .head h1 { margin: 0; font-size: 22px; }
        .muted { color: var(--muted); }
        .mono { font-family: 'IBM Plex Mono', ui-monospace, monospace; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { width: 34%; color: var(--muted); font-weight: 500; }
        h2 { font-size: 15px; margin: 24px 0 8px; text-transform: uppercase; letter-spacing: .05em; color: var(--brand); }
        .sign { display: flex; justify-content: space-between; gap: 32px; margin-top: 56px; }
        .sign span { flex: 1; border-top: 1px solid #8D958F; padding-top: 4px; text-align: center; font-size: 12px; color: var(--muted); }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { border: 0; margin: 0; max-width: none; padding: 0; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Print / Save as PDF</button></div>
    @yield('content')
</body>
</html>
