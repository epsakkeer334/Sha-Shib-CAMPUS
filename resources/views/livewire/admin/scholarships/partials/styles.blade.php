{{-- Scholarships (Module 2C): shared look of the schemes and assign pages --}}
<style>
    .sc-ui { --sc-ink: #111827; --sc-muted: #6B7280; --sc-line: #E5E7EB; --sc-gold: #B45309; --sc-accent: #F26522; }
    .sc-ui .min-w-0 { min-width: 0; }
    /* hero */
    .sc-ui .sc-hero { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 18px; align-items: center; padding: 20px 24px; border-radius: 16px; color: #fff;
        background: radial-gradient(circle at 92% -20%, rgba(251, 191, 36, .35), transparent 45%), linear-gradient(135deg, #1E1B4B 0%, #312E81 55%, #4338CA 100%); box-shadow: 0 10px 24px rgba(49, 46, 129, .25); }
    .sc-ui .sc-hero h2 { color: #fff; font-size: 24px; font-weight: 700; }
    .sc-ui .sc-hero .breadcrumb-item, .sc-ui .sc-hero .breadcrumb-item a, .sc-ui .sc-hero .breadcrumb-item::before { color: rgba(255, 255, 255, .7) !important; font-size: 12.5px; }
    .sc-ui .sc-hero-main { display: flex; gap: 14px; align-items: center; min-width: 0; }
    .sc-ui .sc-hero-icon { width: 54px; height: 54px; border-radius: 16px; background: linear-gradient(135deg, #FBBF24, #F59E0B); color: #451A03; display: inline-flex; align-items: center; justify-content: center; font-size: 28px; flex-shrink: 0; box-shadow: 0 6px 16px rgba(245, 158, 11, .4); }
    .sc-ui .sc-hero-sub { color: rgba(255, 255, 255, .78); font-size: 13px; }
    .sc-ui .sc-hero-stats { display: flex; gap: 8px; }
    .sc-ui .sc-hero-stats > div { padding: 8px 14px; border-radius: 12px; background: rgba(255, 255, 255, .1); border: 1px solid rgba(255, 255, 255, .16); display: flex; flex-direction: column; min-width: 104px; }
    .sc-ui .sc-hero-stats b { font-size: 19px; font-family: 'IBM Plex Mono', ui-monospace, monospace; }
    .sc-ui .sc-hero-stats span { font-size: 11.5px; color: rgba(255, 255, 255, .7); }
    .sc-ui .sc-hero-actions { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
    .sc-ui .sc-cta { background: linear-gradient(135deg, #FBBF24, #F59E0B); border: 0; color: #451A03; font-weight: 700; }
    .sc-ui .sc-cta:hover { color: #451A03; filter: brightness(1.05); }
    /* filters */
    .sc-ui .sc-filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .sc-ui .sc-search { position: relative; flex: 1 1 220px; max-width: 320px; }
    .sc-ui .sc-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
    .sc-ui .sc-search input { padding-left: 34px; }
    .sc-ui .sc-seg { display: inline-flex; padding: 3px; border-radius: 10px; background: #F3F4F6; }
    .sc-ui .sc-seg button { border: 0; background: none; padding: 6px 14px; border-radius: 8px; font-size: 13px; color: #4B5563; }
    .sc-ui .sc-seg button.is-on { background: #fff; color: var(--sc-ink); font-weight: 600; box-shadow: 0 1px 2px rgba(0, 0, 0, .08); }
    /* cards */
    .sc-ui .sc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(330px, 100%), 1fr)); gap: 14px; }
    .sc-ui .sc-card { background: #fff; border: 1px solid var(--sc-line); border-radius: 16px; padding: 16px; display: flex; flex-direction: column; gap: 12px; transition: box-shadow .15s, transform .15s, border-color .15s; }
    .sc-ui .sc-card:hover { box-shadow: 0 10px 24px rgba(17, 24, 39, .08); transform: translateY(-2px); border-color: #C7D2FE; }
    .sc-ui .sc-card.is-off { opacity: .62; }
    .sc-ui .sc-card-top { display: flex; gap: 12px; align-items: flex-start; }
    .sc-ui .sc-value { width: 78px; height: 72px; border-radius: 14px; display: flex; flex-direction: column; align-items: center; justify-content: center; flex-shrink: 0; }
    .sc-ui .sc-value b { font-size: 22px; line-height: 1; font-family: 'IBM Plex Mono', ui-monospace, monospace; }
    .sc-ui .sc-value b small { font-size: 13px; }
    .sc-ui .sc-value span { font-size: 11px; margin-top: 3px; }
    .sc-ui .sc-value.is-pct { background: linear-gradient(135deg, #EEF2FF, #E0E7FF); color: #3730A3; }
    .sc-ui .sc-value.is-fixed { background: linear-gradient(135deg, #FFFBEB, #FEF3C7); color: #92400E; }
    .sc-ui .sc-value.is-fixed b { font-size: 17px; }
    .sc-ui .sc-code { padding: 1px 8px; border-radius: 6px; background: #F5F3FF; color: #6D28D9; font-weight: 700; font-size: 11.5px; font-family: 'IBM Plex Mono', ui-monospace, monospace; }
    .sc-ui .sc-off { padding: 1px 8px; border-radius: 999px; background: #FEE2E2; color: #991B1B; font-size: 11px; font-weight: 600; }
    .sc-ui .sc-name { font-size: 16px; font-weight: 700; color: var(--sc-ink); margin: 4px 0 2px; }
    .sc-ui .sc-facts { display: flex; flex-direction: column; gap: 4px; font-size: 13px; color: #374151; }
    .sc-ui .sc-facts > div { display: flex; align-items: center; gap: 6px; min-width: 0; }
    .sc-ui .sc-facts i { color: #9CA3AF; }
    .sc-ui .sc-chip { padding: 0 6px; border-radius: 5px; background: #EEF2FF; color: #4338CA; font-weight: 600; font-size: 11px; flex-shrink: 0; }
    .sc-ui .sc-periods { display: flex; flex-wrap: wrap; gap: 4px; }
    .sc-ui .sc-periods span { width: 26px; height: 26px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; background: #F3F4F6; color: #9CA3AF; }
    .sc-ui .sc-periods span.is-on { background: #4338CA; color: #fff; }
    .sc-ui .sc-desc { font-size: 12.5px; color: var(--sc-muted); margin: 0; }
    .sc-ui .sc-card-foot { display: flex; align-items: center; gap: 16px; padding-top: 12px; border-top: 1px dashed var(--sc-line); margin-top: auto; }
    .sc-ui .sc-metric { display: flex; flex-direction: column; }
    .sc-ui .sc-metric b { font-size: 15px; color: var(--sc-ink); font-family: 'IBM Plex Mono', ui-monospace, monospace; }
    .sc-ui .sc-metric span { font-size: 11.5px; color: var(--sc-muted); }
    .sc-ui .btn-icon { width: 30px; height: 30px; padding: 0; display: inline-flex; align-items: center; justify-content: center; }
    .sc-ui .sc-empty { grid-column: 1 / -1; text-align: center; padding: 48px 16px; background: #fff; border: 1px dashed #C7D2FE; border-radius: 16px; }
    .sc-ui .sc-empty > span { width: 64px; height: 64px; border-radius: 50%; background: #EEF2FF; color: #4338CA; display: inline-flex; align-items: center; justify-content: center; font-size: 30px; margin-bottom: 10px; }
    /* modal */
    .sc-ui .sc-step { display: flex; align-items: center; gap: 8px; font-weight: 700; font-size: 13.5px; color: var(--sc-ink); }
    .sc-ui .sc-step > span:first-child { width: 22px; height: 22px; border-radius: 50%; background: var(--sc-accent); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; }
    .sc-ui .sc-type { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; padding: 4px; background: #F3F4F6; border-radius: 12px; }
    .sc-ui .sc-type button { border: 0; background: none; border-radius: 9px; padding: 8px; font-size: 13.5px; color: #4B5563; }
    .sc-ui .sc-type button.is-on { background: #fff; color: var(--sc-ink); font-weight: 700; box-shadow: 0 1px 3px rgba(0, 0, 0, .1); }
    .sc-ui .sc-example { display: flex; gap: 8px; align-items: center; padding: 10px 12px; border-radius: 10px; background: #F0FDF4; border: 1px solid #BBF7D0; color: #166534; font-size: 13px; }
    .sc-ui .sc-period-pick { display: flex; flex-wrap: wrap; gap: 8px; }
    .sc-ui .sc-period-pick button { width: 74px; padding: 8px 4px; border: 1px solid var(--sc-line); background: #fff; border-radius: 12px; display: flex; flex-direction: column; align-items: center; transition: border-color .15s, background .15s; }
    .sc-ui .sc-period-pick button small { font-size: 10.5px; color: var(--sc-muted); }
    .sc-ui .sc-period-pick button b { font-size: 18px; color: #374151; }
    .sc-ui .sc-period-pick button.is-on { border: 2px solid #4338CA; background: #EEF2FF; padding: 7px 3px; }
    .sc-ui .sc-period-pick button.is-on b, .sc-ui .sc-period-pick button.is-on small { color: #3730A3; }
    .sc-ui .sc-period-pick .sc-all { justify-content: center; font-weight: 600; font-size: 13px; color: #4338CA; border-style: dashed; }
    .sc-ui .sc-period-pick.is-invalid button { border-color: #FCA5A5; }
    .sc-ui .sc-status { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; border: 1px solid var(--sc-line); border-radius: 10px; }
    /* assign page */
    .sc-ui .sc-panel { background: #fff; border: 1px solid var(--sc-line); border-radius: 16px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
    .sc-ui .sc-panel-head { display: flex; align-items: center; gap: 10px; padding: 14px 18px; border-bottom: 1px solid #F1F2F4; flex-wrap: wrap; }
    .sc-ui .sc-pick { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; padding: 16px 18px; }
    .sc-ui .sc-tabs { display: inline-flex; gap: 4px; padding: 4px; background: #F3F4F6; border-radius: 12px; }
    .sc-ui .sc-tabs button { border: 0; background: none; padding: 7px 14px; border-radius: 9px; font-size: 13px; color: #4B5563; font-weight: 500; }
    .sc-ui .sc-tabs button.is-on { background: #fff; color: var(--sc-ink); font-weight: 700; box-shadow: 0 1px 2px rgba(0, 0, 0, .08); }
    .sc-ui .sc-tabs .badge { font-size: 10.5px; }
    .sc-ui .sc-students { width: 100%; }
    .sc-ui .sc-students > thead th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .04em; color: var(--sc-muted); background: #F9FAFB; padding: 10px 12px; border-bottom: 1px solid var(--sc-line); white-space: nowrap; }
    .sc-ui .sc-students > tbody > tr > td { padding: 12px; border-bottom: 1px solid #F1F2F4; vertical-align: top; }
    .sc-ui .sc-students > tbody > tr.is-picked > td { background: #FAFAFF; }
    .sc-ui .sc-students > tbody > tr.is-muted > td { background: #FCFCFD; }
    .sc-ui .sc-av { width: 36px; height: 36px; border-radius: 10px; background: #EEF2FF; color: #4338CA; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .sc-ui .sc-sem { width: 100%; font-size: 12.5px; border-collapse: separate; border-spacing: 0; border: 1px solid #F1F2F4; border-radius: 10px; overflow: hidden; }
    .sc-ui .sc-sem th { font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; color: #9CA3AF; font-weight: 600; padding: 5px 8px; background: #FAFAFA; text-align: right; }
    .sc-ui .sc-sem th:first-child, .sc-ui .sc-sem td:first-child { text-align: left; }
    .sc-ui .sc-sem td { padding: 5px 8px; text-align: right; border-top: 1px solid #F5F5F5; font-family: 'IBM Plex Mono', ui-monospace, monospace; white-space: nowrap; }
    .sc-ui .sc-sem td:first-child { font-family: inherit; font-weight: 600; color: #374151; }
    .sc-ui .sc-sem .is-this { color: #4338CA; font-weight: 700; }
    .sc-ui .sc-sem .is-other { color: #B45309; }
    .sc-ui .sc-sem .is-pay { color: var(--sc-ink); font-weight: 700; }
    .sc-ui .sc-later { font-size: 12px; color: var(--sc-muted); margin-top: 6px; }
    .sc-ui .sc-tag { display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
    .sc-ui .sc-tag-ok { background: #DCFCE7; color: #166534; } .sc-ui .sc-tag-has { background: #EEF2FF; color: #3730A3; }
    .sc-ui .sc-tag-no { background: #F3F4F6; color: #6B7280; } .sc-ui .sc-tag-warn { background: #FEF3C7; color: #92400E; }
    .sc-ui .sc-tag-off { background: #FEE2E2; color: #991B1B; }
    .sc-ui .sc-bar { position: sticky; bottom: 12px; z-index: 5; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 12px; padding: 12px 18px; border-radius: 14px; color: #fff; background: linear-gradient(135deg, #1E1B4B, #312E81); box-shadow: 0 10px 24px rgba(30, 27, 75, .3); }
    .sc-ui .sc-bar b { font-family: 'IBM Plex Mono', ui-monospace, monospace; }
    .sc-ui .sc-summary { display: flex; gap: 10px; align-items: center; padding: 12px 14px; border-radius: 12px; background: linear-gradient(135deg, #EEF2FF, #F5F3FF); border: 1px solid #E0E7FF; }
    .sc-ui .sc-summary .sc-value { width: 64px; height: 58px; }
    @media (max-width: 1199px) { .sc-ui .sc-hero { grid-template-columns: 1fr; } .sc-ui .sc-hero-actions { justify-content: flex-start; } .sc-ui .sc-hero-stats { flex-wrap: wrap; } }
</style>
