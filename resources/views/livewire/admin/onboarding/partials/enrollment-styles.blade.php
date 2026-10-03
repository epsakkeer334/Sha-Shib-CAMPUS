{{-- Shared look for the ER & ID cards queue and the per-student ER & ID page (scoped to .er-ui) --}}
<style>
    .er-ui { --er-border: #E5E7EB; --er-soft: #F1F2F4; --er-ink: #111827; --er-muted: #6B7280; --er-accent: #F26522; }
    .er-ui .min-w-0 { min-width: 0; }
    .er-ui .er-ellipsis { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
    .er-ui .er-sub { font-size: 12px; color: var(--er-muted); display: flex; align-items: center; gap: 4px; min-width: 0; }
    .er-ui .er-mono { font-family: 'IBM Plex Mono', ui-monospace, monospace; }

    /* hero */
    .er-ui .er-hero { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; padding: 18px 22px; border-radius: 14px; border: 1px solid var(--er-border);
        background: radial-gradient(circle at 100% 0, rgba(242, 101, 34, .12), transparent 45%), linear-gradient(135deg, #FFFFFF 0%, #FFF8F3 100%); }
    .er-ui .er-hero h2 { font-size: 22px; color: var(--er-ink); }
    .er-ui .er-hero-cta { display: inline-flex; align-items: center; gap: 10px; padding: 10px 16px; border-radius: 999px; border: 0; background: var(--er-accent); color: #fff; font-size: 14px; box-shadow: 0 6px 16px rgba(242, 101, 34, .3); transition: transform .15s; }
    .er-ui .er-hero-cta:hover { transform: translateY(-1px); }
    .er-ui .er-pulse { width: 9px; height: 9px; border-radius: 50%; background: #fff; animation: erPulse 1.6s infinite; }
    @keyframes erPulse { 0% { box-shadow: 0 0 0 0 rgba(255, 255, 255, .8); } 100% { box-shadow: 0 0 0 9px rgba(255, 255, 255, 0); } }
    .er-ui .er-hero-done { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 999px; background: #DCFCE7; color: #15803D; font-weight: 600; font-size: 13.5px; }

    /* status cards */
    .er-ui .er-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
    .er-ui .er-card { --tone: #4338CA; --tone-soft: #EEF2FF; position: relative; overflow: hidden; display: flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: 14px 16px 16px; background: #fff; border: 1px solid var(--er-border); border-radius: 14px; text-align: left; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); transition: box-shadow .15s, transform .15s, border-color .15s; }
    .er-ui .er-card::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: var(--tone); opacity: 0; transition: opacity .15s; }
    .er-ui .er-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16, 24, 40, .08); }
    .er-ui .er-card.is-active { border-color: var(--tone); box-shadow: 0 8px 20px rgba(16, 24, 40, .08); background: linear-gradient(180deg, var(--tone-soft) 0%, #fff 70%); }
    .er-ui .er-card.is-active::after { opacity: 1; }
    .er-ui .er-card-top { display: flex; justify-content: space-between; align-items: center; width: 100%; }
    .er-ui .er-card-label { font-size: 13px; font-weight: 600; color: #374151; }
    .er-ui .er-card-icon { width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 19px; background: var(--tone-soft); color: var(--tone); }
    .er-ui .er-card-count { font-size: 26px; font-weight: 700; line-height: 1.15; color: var(--er-ink); }
    .er-ui .er-card-hint { font-size: 12px; color: var(--er-muted); max-width: 100%; }
    .er-ui .er-card-all { --tone: #4338CA; --tone-soft: #EEF2FF; }
    .er-ui .er-card-warn { --tone: #D97706; --tone-soft: #FEF3C7; }
    .er-ui .er-card-info { --tone: #0284C7; --tone-soft: #E0F2FE; }
    .er-ui .er-card-ok { --tone: #16A34A; --tone-soft: #DCFCE7; }

    /* panels */
    .er-ui .er-panel { background: #fff; border: 1px solid var(--er-border); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); overflow: hidden; }
    .er-ui .er-panel-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 12px 16px; border-bottom: 1px solid var(--er-soft); }
    .er-ui .er-panel-title { font-weight: 600; font-size: 15px; color: var(--er-ink); display: flex; align-items: center; gap: 8px; }
    .er-ui .er-panel-foot { padding: 12px 16px; border-top: 1px solid var(--er-soft); }
    .er-ui .er-panel-foot .pagination { margin: 0; }
    .er-ui .er-count { min-width: 26px; height: 22px; padding: 0 8px; border-radius: 999px; background: #F3F4F6; color: #374151; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; }

    /* chips & codes */
    .er-ui .er-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
    .er-ui .er-chip-ok { background: #DCFCE7; color: #15803D; }
    .er-ui .er-chip-warn { background: #FEF3C7; color: #B45309; }
    .er-ui .er-chip-info { background: #E0F2FE; color: #0369A1; }
    .er-ui .er-chip-bad { background: #FEE2E2; color: #DC2626; }
    .er-ui .er-chip-muted { background: #F3F4F6; color: var(--er-muted); }
    .er-ui .er-code { padding: 0 6px; border-radius: 5px; background: #EEF2FF; color: #4338CA; font-weight: 600; font-size: 11px; }
    .er-ui .er-inst-code { display: inline-block; margin-top: 3px; padding: 1px 8px; border-radius: 6px; background: #EEF2FF; color: #4338CA; font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 11.5px; font-weight: 500; }
</style>
