<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · Sha-Shib Campus</title>
    <link rel="icon" type="image/png" href="{{ asset('brand/favicon.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --ink: #17163F; --muted: #585B7C; --line: #DDDFEC; --brand: #1D1C4C; --accent: #F26522; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #F3F4F9; -webkit-print-color-adjust: exact; print-color-adjust: exact; color: var(--ink); font: 14px/20px 'IBM Plex Sans', 'Segoe UI', system-ui, sans-serif; }
        .sheet { max-width: 800px; margin: 24px auto; background: #fff; border: 1px solid var(--line); border-radius: 8px; padding: 40px; }
        .toolbar { max-width: 800px; margin: 24px auto 0; display: flex; justify-content: flex-end; gap: 8px; }
        .toolbar button { height: 40px; padding: 0 16px; border-radius: 8px; border: 0; background: var(--brand); color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        .head { position: relative; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border-bottom: 3px solid var(--brand); padding-bottom: 16px; margin-bottom: 24px; }
        .head::after { content: ""; position: absolute; left: 0; bottom: -3px; width: 96px; height: 3px; background: var(--accent); }
        .inst { display: flex; align-items: center; gap: 14px; min-width: 0; }
        .inst-logo { width: 58px; height: 58px; object-fit: contain; flex-shrink: 0; }
        .brand-foot { margin-top: 36px; padding-top: 12px; border-top: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center; gap: 16px; font-size: 11px; color: var(--muted); }
        .brand-foot img { height: 24px; width: auto; }
        .head h1 { margin: 0; font-size: 22px; }
        .muted { color: var(--muted); }
        .mono { font-family: 'IBM Plex Mono', ui-monospace, monospace; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { width: 34%; color: var(--muted); font-weight: 500; }
        h2 { font-size: 15px; margin: 24px 0 8px; text-transform: uppercase; letter-spacing: .05em; color: var(--brand); }
        .sign { display: flex; justify-content: space-between; gap: 32px; margin-top: 56px; }
        .sign span { flex: 1; border-top: 1px solid #8E90AC; padding-top: 4px; text-align: center; font-size: 12px; color: var(--muted); }
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

    @hasSection('print_record_url')
    <script>
    // Count the print once the browser's print dialog closes (toolbar button or Ctrl+P), then tell
    // open CAMP tabs (e.g. the student's ER & ID page) to refresh their print count.
    (function () {
        const url = {!! json_encode(trim($__env->yieldContent('print_record_url')), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!};
        const key = {!! json_encode(trim($__env->yieldContent('print_record_key')), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!};
        window.addEventListener('afterprint', function () {
            fetch(url, {
                method: 'POST', keepalive: true, credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
            }).then(function (r) { return r.ok ? r.json() : null; }).then(function (data) {
                if (data && 'BroadcastChannel' in window) {
                    new BroadcastChannel('camp-prints').postMessage({ key: key, data: data });
                }
            }).catch(function () {});
        });
    })();
    </script>
    @endif
</body>
</html>
