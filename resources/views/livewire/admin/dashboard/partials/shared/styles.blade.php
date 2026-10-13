{{-- Dashboard design kit (all role dashboards): hero, KPI cards, panels, rows, chips --}}
<style>
    .db-ui { --db-border: #E5E7EB; --db-soft: #F1F2F4; --db-ink: #111827; --db-muted: #6B7280; --db-accent: #F26522; }
    .db-ui .min-w-0 { min-width: 0; }

    /* hero */
    .db-ui .db-hero { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; padding: 20px 24px; border-radius: 16px; border: 1px solid var(--db-border);
        background: radial-gradient(circle at 100% 0, rgba(242, 101, 34, .13), transparent 45%), linear-gradient(135deg, #FFFFFF 0%, #FFF8F3 100%); }
    .db-ui .db-hero-av { width: 56px; height: 56px; border-radius: 16px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 700; color: #fff; background: linear-gradient(135deg, #F26522, #F59E0B); box-shadow: 0 6px 16px rgba(242, 101, 34, .3); overflow: hidden; }
    .db-ui .db-hero-av img { width: 100%; height: 100%; object-fit: cover; }
    .db-ui .db-hero h2 { font-size: 22px; font-weight: 700; color: var(--db-ink); margin: 0; }
    .db-ui .db-hero-greet { font-size: 13px; color: var(--db-muted); }
    .db-ui .db-hero-line { font-size: 13.5px; color: #374151; margin-top: 3px; }
    .db-ui .db-hero-line a { font-weight: 600; }
    .db-ui .db-hero-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .db-ui .db-date { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 10px; background: #fff; border: 1px solid var(--db-border); font-size: 13px; color: #374151; }
    .db-ui .db-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 10px; font-size: 13px; font-weight: 600; border: 1px solid var(--db-border); background: #fff; color: #374151; text-decoration: none; }
    .db-ui .db-btn:hover { border-color: #FDBA8C; color: var(--db-accent); }
    .db-ui .db-btn-primary { background: var(--db-accent); border-color: var(--db-accent); color: #fff; box-shadow: 0 4px 12px rgba(242, 101, 34, .25); }
    .db-ui .db-btn-primary:hover { background: #E0551A; color: #fff; }

    /* KPI cards */
    .db-ui .db-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
    .db-ui .db-kpis.db-kpis-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .db-ui .db-kpi { --tone: #4338CA; --tone-soft: #EEF2FF; position: relative; display: flex; flex-direction: column; gap: 2px; padding: 16px 18px; background: #fff; border: 1px solid var(--db-border); border-top: 3px solid var(--tone); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); text-decoration: none; color: inherit; transition: box-shadow .15s, transform .15s; min-width: 0; }
    .db-ui a.db-kpi:hover { box-shadow: 0 8px 20px rgba(16, 24, 40, .08); transform: translateY(-2px); color: inherit; }
    .db-ui .db-kpi-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
    .db-ui .db-kpi-label { font-size: 12.5px; font-weight: 600; color: var(--db-muted); text-transform: uppercase; letter-spacing: .04em; }
    .db-ui .db-kpi-icon { width: 38px; height: 38px; border-radius: 11px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 19px; background: var(--tone-soft); color: var(--tone); }
    .db-ui .db-kpi-value { font-size: 26px; font-weight: 800; color: var(--db-ink); line-height: 1.15; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .db-ui .db-kpi-value small { font-size: 15px; font-weight: 600; color: var(--db-muted); }
    .db-ui .db-kpi-hint { font-size: 12.5px; color: var(--db-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .db-ui .db-kpi-go { position: absolute; right: 14px; bottom: 12px; font-size: 15px; color: #D1D5DB; transition: color .15s; }
    .db-ui a.db-kpi:hover .db-kpi-go { color: var(--tone); }

    /* panels */
    .db-ui .db-panel { background: #fff; border: 1px solid var(--db-border); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); overflow: hidden; height: 100%; display: flex; flex-direction: column; }
    .db-ui .db-panel-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 14px 18px; border-bottom: 1px solid var(--db-soft); }
    .db-ui .db-panel-head h5 { font-size: 15px; font-weight: 700; color: var(--db-ink); margin: 0; }
    .db-ui .db-panel-head small { display: block; font-size: 12px; color: var(--db-muted); }
    .db-ui .db-ph-icon { width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 17px; background: #FEF0E7; color: var(--db-accent); }
    .db-ui .db-panel-body { padding: 16px 18px; flex: 1 1 auto; }
    .db-ui .db-panel-body.p-0 { padding: 0; }
    .db-ui .db-link { font-size: 12.5px; font-weight: 600; color: var(--db-accent); text-decoration: none; white-space: nowrap; }
    .db-ui .db-link:hover { text-decoration: underline; }
    .db-ui .db-tag { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #F3F4F6; color: #374151; white-space: nowrap; }

    /* rows / lists */
    .db-ui .db-row { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--db-soft); }
    .db-ui .db-row:last-child { border-bottom: 0; }
    .db-ui .db-row-main { flex: 1 1 auto; min-width: 0; }
    .db-ui .db-row > .db-row-sub, .db-ui .db-row > .db-chip, .db-ui .db-row > .db-amount, .db-ui .db-row > b, .db-ui .db-row > .db-link { flex-shrink: 0; }
    /* compact tile grid (master data) */
    .db-ui .db-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .db-ui .db-grid-item { display: flex; align-items: center; gap: 10px; padding: 9px 10px; border: 1px solid var(--db-border); border-radius: 10px; text-decoration: none; color: inherit; transition: border-color .15s, background .15s; min-width: 0; }
    .db-ui .db-grid-item:hover { border-color: #FDBA8C; background: #FFFBF7; color: inherit; }
    .db-ui .db-grid-item b { margin-left: auto; font-size: 15px; color: var(--db-ink); }
    .db-ui .db-row-title { font-size: 13.5px; font-weight: 600; color: var(--db-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .db-ui .db-row-title a { color: inherit; }
    .db-ui .db-row-sub { font-size: 12px; color: var(--db-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .db-ui .db-av { --tone: #4338CA; --tone-soft: #EEF2FF; width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 12.5px; font-weight: 700; background: var(--tone-soft); color: var(--tone); }
    .db-ui .db-av-round { border-radius: 50%; }
    .db-ui .db-amount { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-weight: 700; font-size: 13.5px; color: var(--db-ink); white-space: nowrap; }
    .db-ui .db-empty { text-align: center; color: var(--db-muted); padding: 26px 8px; font-size: 13px; }
    .db-ui .db-empty i { display: block; font-size: 30px; color: #D1D5DB; margin-bottom: 6px; }
    .db-ui .db-empty.is-ok i { color: #22C55E; }

    /* chips & legend */
    .db-ui .db-chip { --tone: #475569; --tone-soft: #F1F5F9; display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 600; background: var(--tone-soft); color: var(--tone); white-space: nowrap; }
    .db-ui .db-legend { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px 14px; }
    .db-ui .db-legend-item { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 12.5px; color: #374151; }
    .db-ui .db-legend-item b { color: var(--db-ink); }
    .db-ui .db-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; background: var(--tone); }
    .db-ui .db-bar { display: flex; height: 8px; border-radius: 999px; overflow: hidden; background: #F3F4F6; gap: 2px; }
    .db-ui .db-bar span { height: 100%; background: var(--tone); }
    .db-ui .db-mini-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); border: 1px solid var(--db-border); border-radius: 12px; overflow: hidden; }
    .db-ui .db-mini-stats > div { padding: 10px 12px; border-right: 1px solid var(--db-soft); }
    .db-ui .db-mini-stats > div:last-child { border-right: 0; }
    .db-ui .db-mini-stats span { display: block; font-size: 11.5px; color: var(--db-muted); text-transform: uppercase; letter-spacing: .04em; font-weight: 600; }
    .db-ui .db-mini-stats b { font-size: 18px; color: var(--db-ink); }

    /* tabs */
    .db-ui .db-tabs { display: inline-flex; padding: 3px; border-radius: 10px; background: #F3F4F6; gap: 2px; }
    .db-ui .db-tabs button { border: 0; background: transparent; padding: 5px 10px; border-radius: 8px; font-size: 12px; font-weight: 600; color: var(--db-muted); }
    .db-ui .db-tabs button.active { background: #fff; color: var(--db-ink); box-shadow: 0 1px 2px rgba(16, 24, 40, .1); }

    /* tables */
    .db-ui .db-table thead th { background: #F9FAFB; font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--db-muted); padding: 10px 16px; white-space: nowrap; border-bottom: 1px solid var(--db-border); }
    .db-ui .db-table td { padding: 11px 16px; border-color: var(--db-soft); font-size: 13.5px; vertical-align: middle; }
    .db-ui .db-table tbody tr:last-child td { border-bottom: 0; }

    /* tones */
    .db-ui .tone-orange { --tone: #EA580C; --tone-soft: #FFF1E7; } .db-ui .tone-indigo { --tone: #4338CA; --tone-soft: #EEF2FF; }
    .db-ui .tone-green { --tone: #15803D; --tone-soft: #DCFCE7; } .db-ui .tone-sky { --tone: #0369A1; --tone-soft: #E0F2FE; }
    .db-ui .tone-violet { --tone: #6D28D9; --tone-soft: #EDE9FE; } .db-ui .tone-amber { --tone: #B45309; --tone-soft: #FEF3C7; }
    .db-ui .tone-red { --tone: #B91C1C; --tone-soft: #FEE2E2; } .db-ui .tone-teal { --tone: #0F766E; --tone-soft: #CCFBF1; }
    .db-ui .tone-slate { --tone: #475569; --tone-soft: #F1F5F9; } .db-ui .tone-pink { --tone: #BE185D; --tone-soft: #FCE7F3; }

    @media (max-width: 1199.98px) { .db-ui .db-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .db-ui .db-kpis.db-kpis-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 575.98px) {
        .db-ui .db-kpis, .db-ui .db-kpis.db-kpis-3 { grid-template-columns: 1fr; }
        .db-ui .db-hero { padding: 16px; }
        .db-ui .db-legend { grid-template-columns: 1fr; }
    }

    /* ---------- dashboard banner (dark, dashboards only) ---------- */
    .db-ui .db-banner { position: relative; overflow: hidden; border-radius: 18px; padding: 24px 26px; color: #fff;
        background: linear-gradient(120deg, #0B1220 0%, #172033 48%, #2A1A12 100%); box-shadow: 0 14px 34px rgba(15, 23, 42, .22); }
    .db-ui .db-banner::before { content: ''; position: absolute; inset: 0; opacity: .35; pointer-events: none;
        background-image: linear-gradient(rgba(255, 255, 255, .05) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, .05) 1px, transparent 1px);
        background-size: 28px 28px; mask-image: linear-gradient(90deg, transparent, #000 35%, #000 70%, transparent); -webkit-mask-image: linear-gradient(90deg, transparent, #000 35%, #000 70%, transparent); }
    .db-ui .db-banner-glow { position: absolute; right: -80px; top: -120px; width: 380px; height: 380px; border-radius: 50%; background: radial-gradient(circle, rgba(242, 101, 34, .55), transparent 65%); pointer-events: none; }
    .db-ui .db-banner-orb { position: absolute; border-radius: 50%; pointer-events: none; filter: blur(2px); }
    .db-ui .db-orb-1 { width: 120px; height: 120px; left: 38%; bottom: -60px; background: radial-gradient(circle, rgba(59, 130, 246, .35), transparent 70%); animation: dbFloat 9s ease-in-out infinite; }
    .db-ui .db-orb-2 { width: 70px; height: 70px; left: 62%; top: -25px; background: radial-gradient(circle, rgba(245, 158, 11, .4), transparent 70%); animation: dbFloat 7s ease-in-out infinite reverse; }
    @keyframes dbFloat { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(18px, -12px); } }
    .db-ui .db-banner-inner { position: relative; display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap; }
    .db-ui .db-banner-av { width: 64px; height: 64px; border-radius: 18px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 800; color: #fff;
        background: linear-gradient(135deg, #F26522, #F59E0B); box-shadow: 0 0 0 4px rgba(255, 255, 255, .08), 0 10px 24px rgba(242, 101, 34, .45); overflow: hidden; }
    .db-ui .db-banner-av img { width: 100%; height: 100%; object-fit: cover; }
    .db-ui .db-banner-greet { font-size: 13px; color: #CBD5E1; }
    .db-ui .db-banner-greet i { color: #FBBF24; }
    .db-ui .db-banner-greet strong { color: #fff; }
    .db-ui .db-banner-title { margin: 2px 0 0; font-size: 26px; font-weight: 800; color: #fff; letter-spacing: -.01em; }
    .db-ui .db-banner-line { margin-top: 4px; font-size: 13.5px; color: #CBD5E1; }
    .db-ui .db-banner-line .db-tag { background: rgba(255, 255, 255, .12); color: #fff; }
    .db-ui .db-banner-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; }
    .db-ui .db-bbtn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 10px; font-size: 13px; font-weight: 600; color: #fff; text-decoration: none;
        background: rgba(255, 255, 255, .08); border: 1px solid rgba(255, 255, 255, .18); backdrop-filter: blur(4px); transition: background .15s, transform .15s; }
    .db-ui .db-bbtn:hover { background: rgba(255, 255, 255, .16); color: #fff; transform: translateY(-1px); }
    .db-ui .db-bbtn.is-primary { background: #F26522; border-color: #F26522; box-shadow: 0 6px 16px rgba(242, 101, 34, .4); }
    .db-ui .db-bbtn.is-primary:hover { background: #FF7A3D; }
    .db-ui .db-banner-clock { min-width: 250px; padding: 14px 18px; border-radius: 14px; background: rgba(255, 255, 255, .07); border: 1px solid rgba(255, 255, 255, .14); backdrop-filter: blur(6px); }
    .db-ui .db-live { display: inline-flex; align-items: center; gap: 6px; padding: 2px 10px; border-radius: 999px; background: rgba(34, 197, 94, .16); color: #4ADE80; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
    .db-ui .db-live-dot { width: 7px; height: 7px; border-radius: 50%; background: #22C55E; animation: dbLive 1.6s infinite; }
    @keyframes dbLive { 0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, .7); } 70% { box-shadow: 0 0 0 7px rgba(34, 197, 94, 0); } 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); } }
    .db-ui .db-clock-time { margin-top: 6px; font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 26px; font-weight: 700; color: #fff; letter-spacing: .02em; }
    .db-ui .db-clock-date { font-size: 12.5px; color: #CBD5E1; }
    .db-ui .db-clock-updated { margin-top: 6px; font-size: 11.5px; color: #94A3B8; }
    .db-ui .db-clock-updated a { color: #FDBA74; }

    /* ---------- live effects ---------- */
    .db-ui .db-reveal { opacity: 0; transform: translateY(14px); animation: dbIn .55s cubic-bezier(.2, .7, .2, 1) forwards; }
    @keyframes dbIn { to { opacity: 1; transform: none; } }
    .db-ui .db-bar span { transition: width 1.1s cubic-bezier(.2, .7, .2, 1); }
    .db-ui .db-kpi::after { content: ''; position: absolute; inset: 0; border-radius: inherit; pointer-events: none;
        background: linear-gradient(110deg, transparent 30%, rgba(255, 255, 255, .55) 50%, transparent 70%); background-size: 250% 100%; background-position: 130% 0; }
    .db-ui a.db-kpi:hover::after { animation: dbShine .9s ease; }
    @keyframes dbShine { from { background-position: 130% 0; } to { background-position: -30% 0; } }
    .db-ui .db-kpi-icon { transition: transform .25s; }
    .db-ui a.db-kpi:hover .db-kpi-icon { transform: scale(1.08) rotate(-4deg); }
    .db-ui .db-panel { transition: box-shadow .2s; }
    .db-ui .db-panel:hover { box-shadow: 0 8px 22px rgba(16, 24, 40, .07); }
    @media (prefers-reduced-motion: reduce) {
        .db-ui .db-reveal, .db-ui .db-orb-1, .db-ui .db-orb-2, .db-ui .db-live-dot { animation: none !important; opacity: 1; transform: none; }
        .db-ui .db-bar span { transition: none; }
    }
    @media (max-width: 767.98px) { .db-ui .db-banner { padding: 18px; } .db-ui .db-banner-clock { width: 100%; } .db-ui .db-banner-title { font-size: 22px; } }
</style>

<script>
(function () {
    const root = document.currentScript.closest('.db-ui') || document;
    const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // live clock + "figures updated ... ago"
    const clock = root.querySelector('[data-clock]');
    const updated = root.querySelector('[data-updated]');
    const loadedAt = Date.now();
    const tick = () => {
        if (clock) { clock.textContent = new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }).toUpperCase(); }
        if (updated) {
            const min = Math.floor((Date.now() - loadedAt) / 60000);
            updated.textContent = min < 1 ? 'just now' : (min === 1 ? '1 min ago' : min + ' min ago');
        }
    };
    tick(); setInterval(tick, 1000);

    // staggered entrance of KPI cards and panels
    if (!reduce) {
        root.querySelectorAll('.db-kpi, .db-panel').forEach((el, i) => {
            el.classList.add('db-reveal');
            el.style.animationDelay = Math.min(i * 55, 900) + 'ms';
        });
    }

    // progress bars grow from zero
    root.querySelectorAll('.db-bar span').forEach(bar => {
        const target = bar.style.width; bar.style.width = '0';
        requestAnimationFrame(() => setTimeout(() => { bar.style.width = target; }, 250));
    });

    // KPI numbers count up (keeps prefixes such as the rupee sign and the "/total" part)
    root.querySelectorAll('.db-kpi-value').forEach(el => {
        const node = Array.from(el.childNodes).find(n => n.nodeType === 3 && /\d/.test(n.textContent));
        if (!node || reduce) { return; }
        const m = node.textContent.match(/^(\D*)([\d,]+(?:\.\d+)?)([\s\S]*)$/);
        if (!m) { return; }
        const target = parseFloat(m[2].replace(/,/g, ''));
        if (!isFinite(target) || target === 0) { return; }
        const grouped = m[2].includes(',');
        const fmt = v => grouped ? Math.round(v).toLocaleString('en-IN') : String(Math.round(v));
        const start = performance.now(), dur = 1100;
        const step = now => {
            const p = Math.min(1, (now - start) / dur), eased = 1 - Math.pow(1 - p, 3);
            node.textContent = m[1] + fmt(target * eased) + m[3];
            if (p < 1) { requestAnimationFrame(step); } else { node.textContent = m[1] + m[2] + m[3]; }
        };
        node.textContent = m[1] + '0' + m[3];
        requestAnimationFrame(step);
    });
})();
</script>
