<div class="content ss-ui">
    <style>
        .ss-ui { --ss-ink: #111827; --ss-muted: #6B7280; --ss-line: #E5E7EB; --ss-soft: #F8FAFC; --ss-indigo: #4F46E5; }
        .ss-ui .min-w-0 { min-width: 0; }
        /* hero */
        .ss-hero { display: flex; flex-wrap: wrap; gap: 16px; align-items: center; justify-content: space-between; padding: 20px 24px; border-radius: 16px; color: #fff; margin-bottom: 18px;
            background: radial-gradient(circle at 90% -30%, rgba(129, 140, 248, .45), transparent 50%), linear-gradient(135deg, #0F172A 0%, #1E293B 55%, #312E81 100%); box-shadow: 0 10px 24px rgba(15, 23, 42, .22); }
        .ss-hero-main { display: flex; gap: 14px; align-items: center; min-width: 0; }
        .ss-hero-icon { width: 54px; height: 54px; border-radius: 16px; background: linear-gradient(135deg, #818CF8, #6366F1); display: inline-flex; align-items: center; justify-content: center; font-size: 28px; flex-shrink: 0; box-shadow: 0 6px 16px rgba(99, 102, 241, .45); }
        .ss-hero h2 { color: #fff; font-size: 24px; font-weight: 700; margin: 0 0 2px; }
        .ss-hero .breadcrumb-item, .ss-hero .breadcrumb-item a, .ss-hero .breadcrumb-item::before { color: rgba(255, 255, 255, .65) !important; font-size: 12.5px; }
        .ss-hero p { color: rgba(255, 255, 255, .75); font-size: 13px; margin: 0; }
        .ss-hero-side { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }
        .ss-hero-last { color: rgba(255, 255, 255, .6); font-size: 12px; }
        .ss-pills { display: flex; flex-wrap: wrap; gap: 8px; }
        .ss-pill { display: inline-flex; align-items: center; gap: 7px; padding: 7px 12px; border-radius: 999px; font-size: 12.5px; font-weight: 600; background: rgba(255, 255, 255, .08); border: 1px solid rgba(255, 255, 255, .16); color: #fff; transition: background .15s; }
        button.ss-pill:hover { background: rgba(255, 255, 255, .16); }
        .ss-pill .dot { width: 8px; height: 8px; border-radius: 50%; }
        .ss-pill .dot.on { background: #34D399; box-shadow: 0 0 0 3px rgba(52, 211, 153, .2); }
        .ss-pill .dot.off { background: #F87171; box-shadow: 0 0 0 3px rgba(248, 113, 113, .2); }
        /* layout */
        .ss-layout { display: grid; grid-template-columns: 270px minmax(0, 1fr); gap: 18px; align-items: start; }
        .ss-nav { position: sticky; top: 80px; background: #fff; border: 1px solid var(--ss-line); border-radius: 16px; padding: 8px; display: flex; flex-direction: column; gap: 2px; }
        .ss-nav button { display: flex; gap: 12px; align-items: center; text-align: left; width: 100%; border: 0; background: none; padding: 10px 12px; border-radius: 12px; color: #374151; transition: background .15s; }
        .ss-nav button:hover { background: var(--ss-soft); }
        .ss-nav button i { width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 18px; background: #F1F5F9; color: #475569; flex-shrink: 0; transition: all .15s; }
        .ss-nav button b { display: block; font-size: 14px; font-weight: 600; color: var(--ss-ink); }
        .ss-nav button small { display: block; font-size: 11.5px; color: var(--ss-muted); line-height: 1.3; }
        .ss-nav button { position: relative; }
        .ss-nav button.is-on { background: #EEF2FF; }
        .ss-nav button.is-on::before { content: ""; position: absolute; left: -8px; top: 12px; bottom: 12px; width: 4px; border-radius: 0 4px 4px 0; background: var(--ss-indigo); }
        .ss-nav button.is-on b { color: #3730A3; }
        .ss-nav button.is-on i { background: var(--ss-indigo); color: #fff; box-shadow: 0 4px 10px rgba(79, 70, 229, .3); }
        /* cards */
        .ss-card { background: #fff; border: 1px solid var(--ss-line); border-radius: 16px; overflow: hidden; }
        .ss-card + .ss-card { margin-top: 16px; }
        .ss-card-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 20px; border-bottom: 1px solid var(--ss-line); }
        .ss-card-head h5 { margin: 0; font-size: 16px; font-weight: 700; color: var(--ss-ink); }
        .ss-card-head p { margin: 2px 0 0; font-size: 12.5px; color: var(--ss-muted); }
        .ss-card-body { padding: 20px; }
        .ss-card-foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 20px; background: var(--ss-soft); border-top: 1px solid var(--ss-line); flex-wrap: wrap; }
        .ss-card-foot small { color: var(--ss-muted); }
        .ss-ui .form-label { font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .ss-ui .form-control, .ss-ui .form-select { border-radius: 10px; min-height: 40px; }
        .ss-hint { font-size: 12px; color: var(--ss-muted); margin-top: 4px; }
        .ss-section { font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #94A3B8; margin: 0 0 10px; }
        /* switch rows */
        .ss-switch-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 16px; border: 1px solid var(--ss-line); border-radius: 14px; background: #fff; }
        .ss-switch-row + .ss-switch-row { margin-top: 10px; }
        .ss-switch-row.is-main { background: linear-gradient(135deg, #F8FAFF, #EEF2FF); border-color: #C7D2FE; }
        .ss-switch-row .ico { width: 40px; height: 40px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 20px; background: #EEF2FF; color: var(--ss-indigo); flex-shrink: 0; }
        .ss-switch-row b { display: block; font-size: 14px; color: var(--ss-ink); }
        .ss-switch-row small { display: block; font-size: 12.5px; color: var(--ss-muted); }
        .ss-switch { position: relative; width: 46px; height: 26px; flex-shrink: 0; }
        .ss-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
        .ss-switch span { position: absolute; inset: 0; border-radius: 999px; background: #CBD5E1; transition: background .2s; cursor: pointer; }
        .ss-switch span::after { content: ""; position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0, 0, 0, .25); transition: transform .2s; }
        .ss-switch input:checked + span { background: #22C55E; }
        .ss-switch input:checked + span::after { transform: translateX(20px); }
        .ss-switch input:focus-visible + span { box-shadow: 0 0 0 3px rgba(79, 70, 229, .3); }
        .ss-switch input:disabled + span { opacity: .5; cursor: not-allowed; }
        .ss-switch.sm { width: 38px; height: 22px; }
        .ss-switch.sm span::after { width: 16px; height: 16px; }
        .ss-switch.sm input:checked + span::after { transform: translateX(16px); }
        /* swatches */
        .ss-swatches { display: grid; grid-template-columns: repeat(auto-fill, minmax(118px, 1fr)); gap: 10px; }
        .ss-swatch { position: relative; border: 1.5px solid var(--ss-line); border-radius: 12px; padding: 10px; background: #fff; cursor: pointer; display: flex; flex-direction: column; gap: 8px; transition: border-color .15s, box-shadow .15s; margin: 0; }
        .ss-swatch:hover { border-color: #A5B4FC; }
        .ss-swatch input { position: absolute; opacity: 0; pointer-events: none; }
        .ss-swatch .chip { height: 34px; border-radius: 8px; border: 1px solid rgba(0, 0, 0, .06); }
        .ss-swatch span.lbl { font-size: 12.5px; font-weight: 600; color: #374151; display: flex; justify-content: space-between; align-items: center; }
        .ss-swatch span.lbl i { color: var(--ss-indigo); visibility: hidden; }
        .ss-swatch.is-on { border-color: var(--ss-indigo); box-shadow: 0 0 0 3px rgba(79, 70, 229, .15); }
        .ss-swatch.is-on span.lbl i { visibility: visible; }
        .ss-swatch input[type=color] { position: static; opacity: 1; pointer-events: auto; width: 100%; height: 34px; border: 0; padding: 0; border-radius: 8px; background: none; cursor: pointer; }
        .ss-modes { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; max-width: 360px; }
        /* preview */
        .ss-preview { border: 1px solid var(--ss-line); border-radius: 14px; overflow: hidden; background: #F1F5F9; box-shadow: 0 10px 24px rgba(15, 23, 42, .08); }
        .ss-preview .pv-top { height: 38px; display: flex; align-items: center; gap: 6px; padding: 0 12px; border-bottom: 1px solid rgba(0, 0, 0, .06); }
        .ss-preview .pv-top i { width: 8px; height: 8px; border-radius: 50%; background: rgba(0, 0, 0, .18); }
        .ss-preview .pv-top em { margin-left: auto; width: 70px; height: 8px; border-radius: 4px; background: rgba(0, 0, 0, .14); }
        .ss-preview .pv-body { display: grid; grid-template-columns: 34% 1fr; min-height: 200px; }
        .ss-preview .pv-side { padding: 12px 10px; display: flex; flex-direction: column; gap: 7px; }
        .ss-preview .pv-side span { height: 9px; border-radius: 4px; background: rgba(128, 128, 128, .28); }
        .ss-preview .pv-side span.on { height: 20px; border-radius: 6px; }
        .ss-preview .pv-main { padding: 14px; display: flex; flex-direction: column; gap: 10px; }
        .ss-preview .pv-card { border-radius: 8px; padding: 10px; display: flex; flex-direction: column; gap: 6px; }
        .ss-preview .pv-card span { height: 7px; border-radius: 4px; background: rgba(128, 128, 128, .25); }
        .ss-preview .pv-btn { align-self: flex-start; padding: 5px 14px; border-radius: 6px; color: #fff; font-size: 11px; font-weight: 600; }
        /* events */
        .ss-stage { border: 1px solid var(--ss-line); border-radius: 14px; overflow: hidden; }
        .ss-stage + .ss-stage { margin-top: 12px; }
        .ss-stage-head { display: flex; align-items: center; gap: 8px; padding: 10px 14px; background: var(--ss-soft); font-weight: 700; font-size: 13px; color: var(--ss-ink); border-bottom: 1px solid var(--ss-line); }
        .ss-event { display: flex; align-items: center; gap: 12px; padding: 10px 14px; }
        .ss-event + .ss-event { border-top: 1px dashed var(--ss-line); }
        .ss-event .ev-ico { width: 32px; height: 32px; border-radius: 9px; display: inline-flex; align-items: center; justify-content: center; background: #F1F5F9; color: #475569; flex-shrink: 0; }
        .ss-event b { display: block; font-size: 13.5px; color: var(--ss-ink); font-weight: 600; }
        .ss-event small { color: var(--ss-muted); font-size: 12px; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ss-aud { display: inline-flex; gap: 4px; flex-shrink: 0; }
        .ss-aud span { padding: 1px 7px; border-radius: 999px; font-size: 10.5px; font-weight: 600; background: #F1F5F9; color: #475569; }
        .ss-aud span.st { background: #ECFDF5; color: #047857; }
        .ss-aud span.sf { background: #EEF2FF; color: #4338CA; }
        .ss-ui .is-dim { opacity: .5; pointer-events: none; }
        /* status */
        .ss-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .ss-badge.ok { background: #DCFCE7; color: #166534; }
        .ss-badge.off { background: #F1F5F9; color: #475569; }
        .ss-badge.warn { background: #FEF3C7; color: #92400E; }
        .ss-note { display: flex; gap: 10px; padding: 12px 14px; border-radius: 12px; font-size: 13px; }
        .ss-note i { font-size: 18px; flex-shrink: 0; margin-top: 1px; }
        .ss-note.info { background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE; }
        .ss-note.ok { background: #F0FDF4; color: #166534; border: 1px solid #BBF7D0; }
        .ss-note.bad { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; word-break: break-word; }
        .ss-presets { display: flex; flex-wrap: wrap; gap: 6px; }
        .ss-presets button { border: 1px solid var(--ss-line); background: #fff; border-radius: 999px; padding: 4px 12px; font-size: 12.5px; color: #374151; }
        .ss-presets button:hover { border-color: #A5B4FC; color: var(--ss-indigo); }
        .ss-pw { position: relative; }
        .ss-pw .saved { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 11.5px; color: #047857; background: #ECFDF5; padding: 2px 8px; border-radius: 999px; }
        /* system info */
        .ss-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(280px, 100%), 1fr)); gap: 14px; }
        .ss-info-box { border: 1px solid var(--ss-line); border-radius: 14px; overflow: hidden; }
        .ss-info-box h6 { margin: 0; padding: 10px 14px; background: var(--ss-soft); border-bottom: 1px solid var(--ss-line); font-size: 13px; font-weight: 700; color: var(--ss-ink); }
        .ss-info-row { display: flex; gap: 10px; align-items: flex-start; padding: 9px 14px; font-size: 13px; }
        .ss-info-row + .ss-info-row { border-top: 1px dashed var(--ss-line); }
        .ss-info-row > span:first-child { color: var(--ss-muted); width: 120px; flex-shrink: 0; }
        .ss-info-row b { font-weight: 600; color: var(--ss-ink); word-break: break-word; }
        .ss-info-row .s { width: 8px; height: 8px; border-radius: 50%; margin-top: 6px; flex-shrink: 0; }
        .ss-info-row .s.ok { background: #22C55E; } .ss-info-row .s.warn { background: #F59E0B; } .ss-info-row .s.bad { background: #EF4444; } .ss-info-row .s.info { background: #94A3B8; }
        .ss-info-row small { display: block; color: #B45309; font-size: 11.5px; }
        .ss-disk { height: 10px; border-radius: 999px; background: #E5E7EB; overflow: hidden; }
        .ss-disk span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #6366F1, #8B5CF6); }
        .ss-code { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 12px; background: #0F172A; color: #E2E8F0; border-radius: 12px; padding: 12px 14px; margin: 0; white-space: pre-wrap; }
        /* terminal window */
        .ss-term-win { border-radius: 14px; overflow: hidden; background: #0B0F19; border: 1px solid #1F2937; box-shadow: 0 18px 40px -12px rgba(2, 6, 23, .45), 0 4px 12px rgba(2, 6, 23, .12); }
        .ss-term-bar { display: flex; align-items: center; gap: 12px; padding: 10px 14px; background: linear-gradient(180deg, #1A2030, #151A27); border-bottom: 1px solid #232A3B; }
        .ss-term-bar .dots { display: inline-flex; gap: 7px; }
        .ss-term-bar .dots i { width: 12px; height: 12px; border-radius: 50%; display: block; }
        .ss-term-bar .dots .r { background: #FF5F57; } .ss-term-bar .dots .y { background: #FEBC2E; } .ss-term-bar .dots .g { background: #28C840; }
        .ss-term-bar .ttl { font-family: 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px; color: #8B95A7; }
        .ss-term-status { align-items: center; gap: 6px; font: 600 11px/1 'IBM Plex Mono', ui-monospace, monospace; padding: 4px 9px; border-radius: 999px; text-transform: lowercase; }
        .ss-term-status:not(.run) { display: inline-flex; } /* .run is shown by wire:loading.flex only */
        .ss-term-status.idle { color: #8B95A7; background: rgba(139, 149, 167, .12); }
        .ss-term-status.ok { color: #4ADE80; background: rgba(74, 222, 128, .12); }
        .ss-term-status.fail { color: #F87171; background: rgba(248, 113, 113, .14); }
        .ss-term-status.run { color: #FBBF24; background: rgba(251, 191, 36, .14); }
        .ss-term-status .pulse { width: 7px; height: 7px; border-radius: 50%; background: currentColor; animation: ssPulse 1s ease-in-out infinite; }
        .ss-term-copy { border: 0; background: transparent; color: #8B95A7; width: 28px; height: 28px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; }
        .ss-term-copy:hover { background: rgba(255, 255, 255, .08); color: #E2E8F0; }
        .ss-term-body { margin: 0; padding: 16px 18px 18px; min-height: 150px; max-height: 360px; overflow: auto; background: transparent; color: #CBD5E1; font: 12.5px/1.7 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, monospace; white-space: pre-wrap; word-break: break-word; }
        .ss-term-body::-webkit-scrollbar { width: 8px; } .ss-term-body::-webkit-scrollbar-thumb { background: #283044; border-radius: 8px; }
        .ss-term-body .t-prompt { color: #4ADE80; font-weight: 700; }
        .ss-term-body .t-cmd { color: #F1F5F9; }
        .ss-term-body .t-comment { color: #64748B; font-style: italic; }
        .ss-term-body .t-wait { color: #64748B; }
        .ss-term-body .t-wait .t-prompt { color: #475569; }
        .ss-term-body .t-ok { color: #4ADE80; font-weight: 600; }
        .ss-term-body .t-err { color: #F87171; }
        .ss-term-body .t-dim { color: #64748B; }
        .ss-term-body .t-badge { display: inline-block; padding: 0 6px; border-radius: 4px; font-size: 11px; font-weight: 700; line-height: 1.5; }
        .ss-term-body .t-INFO { background: #2563EB; color: #fff; } .ss-term-body .t-WARN { background: #D97706; color: #fff; } .ss-term-body .t-ERROR { background: #DC2626; color: #fff; }
        .ss-term-body .t-cursor { display: inline-block; width: 8px; height: 15px; margin-left: 3px; vertical-align: -3px; background: #94A3B8; animation: ssBlink 1.1s steps(1) infinite; }
        @keyframes ssBlink { 50% { opacity: 0; } }
        @keyframes ssPulse { 50% { opacity: .35; transform: scale(.8); } }
        /* cache */
        .ss-cache-state { display: flex; flex-wrap: wrap; gap: 8px; }
        .ss-cache-pill { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; padding: 5px 11px; border-radius: 999px; background: #F3F4F6; color: var(--ss-muted); border: 1px solid #E5E7EB; }
        .ss-cache-pill.on { background: #ECFDF5; color: #047857; border-color: #A7F3D0; }
        .ss-cache-pill b { color: inherit; font-weight: 600; }
        .ss-acts { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(230px, 100%), 1fr)); gap: 12px; }
        .ss-act { position: relative; display: flex; flex-direction: column; align-items: flex-start; gap: 6px; padding: 16px; border: 1px solid var(--ss-line); border-radius: 14px; background: #fff; transition: border-color .15s, box-shadow .15s, transform .15s; }
        .ss-act:hover { border-color: #C7D2FE; box-shadow: 0 8px 20px -10px rgba(79, 70, 229, .35); transform: translateY(-1px); }
        .ss-act.is-reco { border-color: #C7D2FE; background: linear-gradient(180deg, #F8FAFF, #fff 55%); }
        .ss-act-tag { position: absolute; top: 12px; right: 12px; font-size: 10.5px; font-weight: 700; color: #4338CA; background: #EEF2FF; padding: 3px 8px; border-radius: 999px; }
        .ss-act-ico { width: 40px; height: 40px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 20px; margin-bottom: 4px; }
        .ss-act-ico.warning { background: #FEF3C7; color: #B45309; } .ss-act-ico.danger { background: #FEE2E2; color: #DC2626; } .ss-act-ico.primary { background: #EEF2FF; color: var(--ss-indigo); }
        .ss-act b { font-size: 14.5px; color: var(--ss-ink); }
        .ss-act code { font-size: 11px; background: #F1F5F9; padding: 2px 7px; border-radius: 6px; color: #475569; }
        .ss-act small { color: var(--ss-muted); font-size: 12.5px; line-height: 1.45; margin-bottom: 8px; }
        /* health */
        .ss-health { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; padding: 14px 16px; border-radius: 14px; margin-bottom: 16px; border: 1px solid; }
        .ss-health.ok { background: #F0FDF4; border-color: #BBF7D0; } .ss-health.warn { background: #FFFBEB; border-color: #FDE68A; } .ss-health.bad { background: #FEF2F2; border-color: #FECACA; }
        .ss-health-ico { width: 42px; height: 42px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; background: #fff; }
        .ss-health.ok .ss-health-ico { color: #16A34A; } .ss-health.warn .ss-health-ico { color: #D97706; } .ss-health.bad .ss-health-ico { color: #DC2626; }
        .ss-health b { display: block; font-size: 15px; color: var(--ss-ink); }
        .ss-health small { color: var(--ss-muted); font-size: 12.5px; }
        .ss-health-counts { margin-left: auto; display: flex; gap: 6px; flex-wrap: wrap; }
        .ss-health-counts .c { font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 999px; background: #fff; }
        .ss-health-counts .c.ok { color: #15803D; } .ss-health-counts .c.warn { color: #B45309; } .ss-health-counts .c.bad { color: #B91C1C; }
        .ss-info-box h6 i { color: #64748B; margin-right: 4px; }
        /* card titles */
        .ss-title { display: flex; align-items: center; gap: 12px; min-width: 0; }
        .ss-head-ico { width: 40px; height: 40px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .ss-head-ico.indigo { background: #EEF2FF; color: #4F46E5; } .ss-head-ico.pink { background: #FCE7F3; color: #DB2777; } .ss-head-ico.blue { background: #DBEAFE; color: #2563EB; }
        .ss-head-ico.teal { background: #CCFBF1; color: #0D9488; } .ss-head-ico.amber { background: #FEF3C7; color: #D97706; } .ss-head-ico.green { background: #DCFCE7; color: #16A34A; }
        .ss-head-ico.slate { background: #F1F5F9; color: #475569; } .ss-head-ico.red { background: #FEE2E2; color: #DC2626; } .ss-head-ico.violet { background: #EDE9FE; color: #7C3AED; }
        /* motion */
        .ss-card { animation: ssIn .28s ease both; }
        .ss-card + .ss-card { animation-delay: .05s; }
        @keyframes ssIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) { .ss-card, .ss-act, .ss-term-body .t-cursor, .ss-term-status .pulse { animation: none !important; transition: none !important; } }
        @media (max-width: 991.98px) {
            .ss-layout { grid-template-columns: minmax(0, 1fr); }
            .ss-nav { position: static; flex-direction: row; overflow-x: auto; padding: 6px; }
            .ss-nav button { flex: 0 0 auto; width: auto; padding: 8px 10px; }
            .ss-nav button small { display: none; }
            .ss-nav button i { width: 30px; height: 30px; font-size: 16px; }
            .ss-nav button.is-on::before { left: 12px; right: 12px; top: auto; bottom: -6px; width: auto; height: 3px; border-radius: 3px 3px 0 0; }
            .ss-hero-side { align-items: flex-start; }
        }
        @media (max-width: 575.98px) {
            .ss-hero { padding: 16px; }
            .ss-card-body { padding: 16px; }
            .ss-event { flex-wrap: wrap; }
            .ss-aud { order: 3; width: 100%; padding-left: 44px; }
        }
    </style>

    {{-- Hero --}}
    <div class="ss-hero">
        <div class="ss-hero-main">
            <span class="ss-hero-icon"><i class="ti ti-adjustments-horizontal"></i></span>
            <div class="min-w-0">
                <h2>System Settings</h2>
                <nav><ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Administration</li><li class="breadcrumb-item active">System Settings</li>
                </ol></nav>
                <p>Look and feel, email, notifications and admissions for the whole system. Only the Super Admin sees this page.</p>
            </div>
        </div>
        <div class="ss-hero-side">
            <div class="ss-pills">
                <button type="button" class="ss-pill" wire:click="setTab('email')"><span class="dot {{ $emailLive ? 'on' : 'off' }}"></span> Email {{ $emailLive ? 'on' : 'off' }}</button>
                <button type="button" class="ss-pill" wire:click="setTab('notifications')"><span class="dot {{ setting('notify.in_app') ? 'on' : 'off' }}"></span> Notifications {{ setting('notify.in_app') ? 'on' : 'off' }}</button>
                <button type="button" class="ss-pill" wire:click="setTab('admissions')"><span class="dot {{ setting('portal.registration_open') ? 'on' : 'off' }}"></span> Admissions {{ setting('portal.registration_open') ? 'open' : 'closed' }}</button>
            </div>
            @if($lastChange)
                <small class="ss-hero-last"><i class="ti ti-history"></i> Last change {{ $lastChange['at']->diffForHumans() }}@if($lastChange['by']) by {{ $lastChange['by'] }}@endif</small>
            @endif
        </div>
    </div>

    <div class="ss-layout">
        {{-- Tabs --}}
        <nav class="ss-nav" aria-label="Settings sections">
            @foreach($tabs as $key => [$label, $icon, $desc])
                <button type="button" wire:click="setTab('{{ $key }}')" class="{{ $tab === $key ? 'is-on' : '' }}" @if($tab === $key) aria-current="page" @endif>
                    <i class="{{ $icon }}"></i>
                    <span class="min-w-0"><b>{{ $label }}</b><small>{{ $desc }}</small></span>
                </button>
            @endforeach
        </nav>

        <div class="min-w-0">
            {{-- ───────────────────────── General ───────────────────────── --}}
            @if($tab === 'general')
                <form wire:submit.prevent="save" class="ss-card">
                    <div class="ss-card-head"><div class="ss-title"><span class="ss-head-ico indigo"><i class="ti ti-building"></i></span><div class="min-w-0"><h5>General</h5><p>Shown in the browser title, emails and page footers.</p></div></div></div>
                    <div class="ss-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">System name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('s.general.site_name') is-invalid @enderror" wire:model.defer="s.general.site_name" maxlength="80">
                                @error('s.general.site_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="ss-hint">Browser tab title and the sender name of emails.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Organisation <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('s.general.organisation') is-invalid @enderror" wire:model.defer="s.general.organisation" maxlength="120">
                                @error('s.general.organisation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Support email</label>
                                <input type="email" class="form-control @error('s.general.support_email') is-invalid @enderror" wire:model.defer="s.general.support_email" placeholder="admissions@shashibcampus.com">
                                @error('s.general.support_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Support phone</label>
                                <input type="text" class="form-control @error('s.general.support_phone') is-invalid @enderror" wire:model.defer="s.general.support_phone" placeholder="+91 …">
                                @error('s.general.support_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea class="form-control @error('s.general.address') is-invalid @enderror" rows="2" wire:model.defer="s.general.address"></textarea>
                                @error('s.general.address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">Footer text</label>
                                <input type="text" class="form-control @error('s.general.footer_text') is-invalid @enderror" wire:model.defer="s.general.footer_text" placeholder="© {{ date('Y') }} Sha-Shib Group. All rights reserved.">
                                @error('s.general.footer_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="ss-hint">Leave empty to use the default copyright line.</div>
                            </div>
                        </div>
                    </div>
                    <div class="ss-card-foot"><small><i class="ti ti-history"></i> Changes are recorded in the audit trail.</small>@include('livewire.admin.settings.partials.save-button')</div>
                </form>
            @endif

            {{-- ───────────────────────── Appearance ───────────────────────── --}}
            @if($tab === 'appearance')
                @php
                    $pvColor = $s['theme']['color'] === 'custom' ? $s['theme']['custom_color'] : (\App\Support\Settings::COLORS[$s['theme']['color']][1] ?? '#F26522');
                    $pvSide = \App\Support\Settings::SIDEBARS[$s['theme']['sidebar']][1] ?? '#fff';
                    $pvTop = \App\Support\Settings::TOPBARS[$s['theme']['topbar']][1] ?? '#fff';
                    $dark = $s['theme']['mode'] === 'dark';
                    $isLight = fn ($c) => in_array(strtoupper($c), ['#FFFFFF', '#F0E4D7', '#B5C0D0', '#8CB9BD'], true);
                @endphp
                <form wire:submit.prevent="save" class="ss-card">
                    <div class="ss-card-head"><div class="ss-title"><span class="ss-head-ico pink"><i class="ti ti-palette"></i></span><div class="min-w-0"><h5>Appearance</h5><p>The admin panel theme for every staff member. The admissions portal keeps its own look.</p></div></div></div>
                    <div class="ss-card-body">
                        <div class="row g-4">
                            <div class="col-xl-8">
                                <p class="ss-section">Primary colour</p>
                                <div class="ss-swatches mb-4">
                                    @foreach(\App\Support\Settings::COLORS as $key => [$label, $hex])
                                        <label class="ss-swatch {{ $s['theme']['color'] === $key ? 'is-on' : '' }}">
                                            <input type="radio" wire:model="s.theme.color" value="{{ $key }}">
                                            <span class="chip" style="background: {{ $hex }};"></span>
                                            <span class="lbl">{{ $label }} <i class="ti ti-circle-check-filled"></i></span>
                                        </label>
                                    @endforeach
                                    <label class="ss-swatch {{ $s['theme']['color'] === 'custom' ? 'is-on' : '' }}">
                                        <input type="radio" wire:model="s.theme.color" value="custom">
                                        <input type="color" wire:model="s.theme.custom_color" wire:click="pickColor('custom')" aria-label="Custom colour">
                                        <span class="lbl">Custom <i class="ti ti-circle-check-filled"></i></span>
                                    </label>
                                </div>
                                @error('s.theme.custom_color')<div class="text-danger small mb-3">{{ $message }}</div>@enderror

                                <p class="ss-section">Sidebar</p>
                                <div class="ss-swatches mb-4">
                                    @foreach(\App\Support\Settings::SIDEBARS as $key => [$label, $bg])
                                        <label class="ss-swatch {{ $s['theme']['sidebar'] === $key ? 'is-on' : '' }}">
                                            <input type="radio" wire:model="s.theme.sidebar" value="{{ $key }}">
                                            <span class="chip" style="background: {{ $bg }};"></span>
                                            <span class="lbl">{{ $label }} <i class="ti ti-circle-check-filled"></i></span>
                                        </label>
                                    @endforeach
                                </div>

                                <p class="ss-section">Top bar</p>
                                <div class="ss-swatches mb-4">
                                    @foreach(\App\Support\Settings::TOPBARS as $key => [$label, $bg])
                                        <label class="ss-swatch {{ $s['theme']['topbar'] === $key ? 'is-on' : '' }}">
                                            <input type="radio" wire:model="s.theme.topbar" value="{{ $key }}">
                                            <span class="chip" style="background: {{ $bg }};"></span>
                                            <span class="lbl">{{ $label }} <i class="ti ti-circle-check-filled"></i></span>
                                        </label>
                                    @endforeach
                                </div>

                                <p class="ss-section">Mode</p>
                                <div class="ss-modes mb-4">
                                    @foreach(['light' => ['Light', 'ti ti-sun', '#F8FAFC'], 'dark' => ['Dark', 'ti ti-moon', '#1F2937']] as $key => [$label, $icon, $bg])
                                        <label class="ss-swatch {{ $s['theme']['mode'] === $key ? 'is-on' : '' }}">
                                            <input type="radio" wire:model="s.theme.mode" value="{{ $key }}">
                                            <span class="chip d-flex align-items-center justify-content-center" style="background: {{ $bg }}; color: {{ $key === 'dark' ? '#FBBF24' : '#F59E0B' }}; font-size: 18px;"><i class="{{ $icon }}"></i></span>
                                            <span class="lbl">{{ $label }} <i class="ti ti-circle-check-filled"></i></span>
                                        </label>
                                    @endforeach
                                </div>

                                <div class="ss-switch-row">
                                    <span class="d-flex gap-3 align-items-center min-w-0"><span class="ico"><i class="ti ti-user-cog"></i></span><span class="min-w-0"><b>Let users change their own theme</b><small>Shows the theme customiser (gear) to every user. Off: everyone sees this theme.</small></span></span>
                                    <label class="ss-switch"><input type="checkbox" wire:model.defer="s.theme.allow_user"><span></span></label>
                                </div>
                                <div class="ss-switch-row">
                                    <span class="d-flex gap-3 align-items-center min-w-0"><span class="ico"><i class="ti ti-loader-2"></i></span><span class="min-w-0"><b>Page loading animation</b><small>Spinner while an admin page loads.</small></span></span>
                                    <label class="ss-switch"><input type="checkbox" wire:model.defer="s.theme.loader"><span></span></label>
                                </div>
                            </div>

                            <div class="col-xl-4">
                                <p class="ss-section">Live preview</p>
                                <div class="ss-preview" style="background: {{ $dark ? '#0F172A' : '#F1F5F9' }};">
                                    <div class="pv-top" style="background: {{ $pvTop }};">
                                        <i></i><i></i><i></i><em style="{{ $isLight($pvTop) ? '' : 'background: rgba(255,255,255,.4);' }}"></em>
                                    </div>
                                    <div class="pv-body">
                                        <div class="pv-side" style="background: {{ $s['theme']['sidebar'] === 'light' && $dark ? '#1E293B' : $pvSide }};">
                                            <span class="on" style="background: {{ $pvColor }};"></span>
                                            <span></span><span></span><span style="width: 70%;"></span><span></span><span style="width: 60%;"></span>
                                        </div>
                                        <div class="pv-main">
                                            <div class="pv-card" style="background: {{ $dark ? '#1E293B' : '#fff' }};">
                                                <span style="width: 50%; background: {{ $pvColor }}; opacity: .9;"></span><span></span><span style="width: 80%;"></span>
                                                <span class="pv-btn" style="background: {{ $pvColor }}; height: auto;">Save</span>
                                            </div>
                                            <div class="pv-card" style="background: {{ $dark ? '#1E293B' : '#fff' }};"><span></span><span style="width: 65%;"></span></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="ss-hint mt-2"><i class="ti ti-info-circle"></i> After saving, the page reloads with the new theme.</div>
                            </div>
                        </div>
                    </div>
                    <div class="ss-card-foot"><small><i class="ti ti-users"></i> Applies to all admin users.</small>@include('livewire.admin.settings.partials.save-button', ['saveLabel' => 'Save & apply theme'])</div>
                </form>
            @endif

            {{-- ───────────────────────── Email ───────────────────────── --}}
            @if($tab === 'email')
                <form wire:submit.prevent="save" class="ss-card">
                    <div class="ss-card-head">
                        <div class="ss-title"><span class="ss-head-ico blue"><i class="ti ti-mail"></i></span><div class="min-w-0"><h5>Email</h5><p>Outgoing email for notifications (payments, approvals, ER numbers …).</p></div></div>
                        @if($emailLive)
                            <span class="ss-badge ok"><i class="ti ti-circle-check"></i> Sending</span>
                        @elseif(setting('mail.enabled'))
                            <span class="ss-badge warn"><i class="ti ti-alert-triangle"></i> Incomplete</span>
                        @else
                            <span class="ss-badge off"><i class="ti ti-circle-off"></i> Off</span>
                        @endif
                    </div>
                    <div class="ss-card-body">
                        <div class="ss-switch-row is-main mb-4">
                            <span class="d-flex gap-3 align-items-center min-w-0"><span class="ico"><i class="ti ti-send"></i></span><span class="min-w-0"><b>Send emails</b><small>Off: emails are kept in the Notification Log as “pending” and nothing is sent.</small></span></span>
                            <label class="ss-switch"><input type="checkbox" wire:model.defer="s.mail.enabled"><span></span></label>
                        </div>

                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <p class="ss-section mb-0">SMTP server</p>
                            <div class="ss-presets">
                                <small class="text-muted me-1 align-self-center">Quick fill:</small>
                                @foreach(\App\Http\Livewire\Admin\Settings\SystemSettingsComponent::SMTP_PRESETS as $key => [$label])
                                    <button type="button" wire:click="applyPreset('{{ $key }}')">{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">SMTP host</label>
                                <input type="text" class="form-control @error('s.mail.host') is-invalid @enderror" wire:model.defer="s.mail.host" placeholder="mail.smtp2go.com">
                                @error('s.mail.host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Port</label>
                                <input type="number" class="form-control @error('s.mail.port') is-invalid @enderror" wire:model.defer="s.mail.port">
                                @error('s.mail.port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Encryption</label>
                                <select class="form-select" wire:model.defer="s.mail.encryption">
                                    <option value="tls">TLS (587 / 2525)</option>
                                    <option value="ssl">SSL (465)</option>
                                    <option value="none">None</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" wire:model.defer="s.mail.username" autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <div class="ss-pw">
                                    <input type="password" class="form-control" wire:model.defer="mailPassword" autocomplete="new-password" placeholder="{{ $passwordSaved ? 'Leave empty to keep the saved password' : 'SMTP password or API key' }}">
                                    @if($passwordSaved && $mailPassword === '')<span class="saved"><i class="ti ti-lock"></i> saved</span>@endif
                                </div>
                                @if($passwordSaved)
                                    <div class="ss-hint">Stored encrypted. <a href="#" class="text-danger" data-ss-confirm="removePassword" data-icon="ti-lock-off" data-variant="danger" data-title="Remove SMTP password" data-text="The saved SMTP password will be deleted. Emails can't be sent until you enter it again." data-ok="Yes, Remove">Remove</a></div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">From address</label>
                                <input type="email" class="form-control @error('s.mail.from_address') is-invalid @enderror" wire:model.defer="s.mail.from_address" placeholder="no-reply@shashibcampus.com">
                                @error('s.mail.from_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="ss-hint">Use an address on a domain verified with your mail provider.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">From name</label>
                                <input type="text" class="form-control" wire:model.defer="s.mail.from_name" placeholder="Sha-Shib CAMPUS">
                            </div>
                        </div>

                        <div class="ss-note info mt-4">
                            <i class="ti ti-server-2"></i>
                            <span><b>GoDaddy VPS:</b> outgoing port 25 is usually blocked. Use port <b>2525</b> or <b>587</b> (TLS), or <b>465</b> (SSL). SMTP2GO works on 2525 and gives the best delivery.</span>
                        </div>
                    </div>
                    <div class="ss-card-foot"><small><i class="ti ti-shield-lock"></i> The password is encrypted with the app key.</small>@include('livewire.admin.settings.partials.save-button')</div>
                </form>

                <div class="ss-card">
                    <div class="ss-card-head"><div class="ss-title"><span class="ss-head-ico teal"><i class="ti ti-send"></i></span><div class="min-w-0"><h5>Send a test email</h5><p>Uses the details in the form above, saved or not.</p></div></div></div>
                    <div class="ss-card-body">
                        <form wire:submit.prevent="sendTest" class="d-flex flex-wrap gap-2">
                            <input type="email" class="form-control @error('testEmail') is-invalid @enderror" wire:model.defer="testEmail" placeholder="you@example.com" style="flex: 1 1 240px; max-width: 360px;">
                            <button type="submit" class="btn btn-dark" wire:loading.attr="disabled" wire:target="sendTest">
                                <span wire:loading.remove wire:target="sendTest"><i class="ti ti-send me-1"></i> Send test</span>
                                <span wire:loading wire:target="sendTest"><span class="spinner-border spinner-border-sm me-1"></span> Sending…</span>
                            </button>
                        </form>
                        @error('testEmail')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        @if($testResult)
                            <div class="ss-note {{ $testResult['ok'] ? 'ok' : 'bad' }} mt-3">
                                <i class="ti {{ $testResult['ok'] ? 'ti-circle-check' : 'ti-alert-circle' }}"></i>
                                <span>{{ $testResult['ok'] ? '' : 'Could not send: ' }}{{ $testResult['message'] }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- ───────────────────────── Notifications ───────────────────────── --}}
            @if($tab === 'notifications')
                <form wire:submit.prevent="save" class="ss-card">
                    <div class="ss-card-head"><div class="ss-title"><span class="ss-head-ico amber"><i class="ti ti-bell"></i></span><div class="min-w-0"><h5>Notifications</h5><p>In-app alerts in the bell (admin panel and student portal).</p></div></div></div>
                    <div class="ss-card-body">
                        <div class="ss-switch-row is-main">
                            <span class="d-flex gap-3 align-items-center min-w-0"><span class="ico"><i class="ti ti-bell-ringing"></i></span><span class="min-w-0"><b>In-app notifications</b><small>Master switch. Off: no new bell notifications for anyone.</small></span></span>
                            <label class="ss-switch"><input type="checkbox" wire:model="s.notify.in_app"><span></span></label>
                        </div>
                        <div class="row g-2 mt-1 {{ $s['notify']['in_app'] ? '' : 'is-dim' }}">
                            <div class="col-md-6">
                                <div class="ss-switch-row h-100">
                                    <span class="d-flex gap-3 align-items-center min-w-0"><span class="ico"><i class="ti ti-briefcase"></i></span><span class="min-w-0"><b>Staff</b><small>Admins, accounts, training managers …</small></span></span>
                                    <label class="ss-switch"><input type="checkbox" wire:model="s.notify.staff"><span></span></label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="ss-switch-row h-100">
                                    <span class="d-flex gap-3 align-items-center min-w-0"><span class="ico" style="background: #ECFDF5; color: #047857;"><i class="ti ti-school"></i></span><span class="min-w-0"><b>Students</b><small>Portal bell of applicants and students</small></span></span>
                                    <label class="ss-switch"><input type="checkbox" wire:model="s.notify.student"><span></span></label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-4 mb-2">
                            <p class="ss-section mb-0">Events</p>
                            <small class="text-muted">{{ collect($events)->filter()->count() }} of {{ count($events) }} on</small>
                        </div>
                        <div class="{{ $s['notify']['in_app'] ? '' : 'is-dim' }}">
                            @foreach($eventGroups as $stage => $list)
                                @php [$stageLabel, $stageIcon] = $stages[$stage] ?? [\Illuminate\Support\Str::headline($stage), 'ti ti-point']; @endphp
                                <div class="ss-stage">
                                    <div class="ss-stage-head"><i class="{{ $stageIcon }}"></i> {{ $stageLabel }}</div>
                                    @foreach($list as $event)
                                        <div class="ss-event">
                                            <span class="ev-ico"><i class="{{ $event['icon'] }}"></i></span>
                                            <span class="min-w-0 flex-grow-1"><b>{{ $event['title'] }}</b>@php $evText = preg_replace('/:([a-z_]+)/', '‹$1›', $event['message']); @endphp<small title="{{ $evText }}">{{ $evText }}</small></span>
                                            <span class="ss-aud">
                                                @if(!empty($event['staff']))<span class="sf">Staff</span>@endif
                                                @if(!empty($event['student']))<span class="st">Student</span>@endif
                                            </span>
                                            <label class="ss-switch sm"><input type="checkbox" wire:model.defer="events.{{ $event['key'] }}"><span></span></label>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>

                        <p class="ss-section mt-4">Other channels</p>
                        <div class="ss-switch-row">
                            <span class="d-flex gap-3 align-items-center min-w-0"><span class="ico"><i class="ti ti-mail"></i></span><span class="min-w-0"><b>Email</b><small>Controlled on the Email tab — currently {{ $emailLive ? 'sending' : 'off' }}.</small></span></span>
                            <button type="button" class="btn btn-sm btn-light border" wire:click="setTab('email')">Open <i class="ti ti-arrow-right"></i></button>
                        </div>
                        <div class="ss-switch-row">
                            <span class="d-flex gap-3 align-items-center min-w-0"><span class="ico" style="background: #F1F5F9; color: #94A3B8;"><i class="ti ti-message-2"></i></span><span class="min-w-0"><b>SMS</b><small>No SMS gateway is connected yet.</small></span></span>
                            <span class="ss-badge off">Not set up</span>
                        </div>
                    </div>
                    <div class="ss-card-foot"><small><i class="ti ti-info-circle"></i> Already delivered notifications are not affected.</small>@include('livewire.admin.settings.partials.save-button')</div>
                </form>
            @endif

            {{-- ───────────────────────── Admissions ───────────────────────── --}}
            @if($tab === 'admissions')
                <form wire:submit.prevent="save" class="ss-card">
                    <div class="ss-card-head">
                        <div class="ss-title"><span class="ss-head-ico green"><i class="ti ti-school"></i></span><div class="min-w-0"><h5>Admissions portal</h5><p>Online applications at <a href="{{ route('portal.home') }}" target="_blank">{{ preg_replace('#^https?://#', '', route('portal.home')) }} <i class="ti ti-external-link"></i></a></p></div></div>
                        <span class="ss-badge {{ setting('portal.registration_open') ? 'ok' : 'warn' }}">{{ setting('portal.registration_open') ? 'Open' : 'Closed' }}</span>
                    </div>
                    <div class="ss-card-body">
                        <div class="ss-switch-row is-main">
                            <span class="d-flex gap-3 align-items-center min-w-0"><span class="ico"><i class="ti ti-user-plus"></i></span><span class="min-w-0"><b>Accept new applications</b><small>Off: “Start your application” is hidden. Students who already applied can still log in, upload documents and pay.</small></span></span>
                            <label class="ss-switch"><input type="checkbox" wire:model="s.portal.registration_open"><span></span></label>
                        </div>
                        <div class="mt-3 {{ $s['portal']['registration_open'] ? 'opacity-50' : '' }}">
                            <label class="form-label">Message when closed</label>
                            <textarea class="form-control @error('s.portal.closed_message') is-invalid @enderror" rows="3" wire:model.defer="s.portal.closed_message" maxlength="500"></textarea>
                            @error('s.portal.closed_message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="ss-hint">Shown on the admissions home page while applications are closed.</div>
                        </div>
                    </div>
                    <div class="ss-card-foot"><small><i class="ti ti-world"></i> Takes effect on the portal straight away.</small>@include('livewire.admin.settings.partials.save-button')</div>
                </form>
            @endif

            {{-- ───────────────────────── System ───────────────────────── --}}
            @if($tab === 'system')
                <form wire:submit.prevent="save" class="ss-card">
                    <div class="ss-card-head"><div class="ss-title"><span class="ss-head-ico slate"><i class="ti ti-shield-lock"></i></span><div class="min-w-0"><h5>Session &amp; security</h5><p>How long a signed-in user can stay idle.</p></div></div></div>
                    <div class="ss-card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-sm-6 col-lg-4">
                                <label class="form-label">Session timeout (minutes)</label>
                                <input type="number" min="5" max="1440" class="form-control @error('s.security.session_lifetime') is-invalid @enderror" wire:model.defer="s.security.session_lifetime">
                                @error('s.security.session_lifetime')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6 col-lg-8"><div class="ss-hint mb-2">Between 5 minutes and 24 hours. Users idle longer than this must sign in again.</div></div>
                        </div>
                    </div>
                    <div class="ss-card-foot"><small><i class="ti ti-clock"></i> Applies from each user's next request.</small>@include('livewire.admin.settings.partials.save-button')</div>
                </form>

                <div class="ss-card">
                    <div class="ss-card-head">
                        <div class="ss-title"><span class="ss-head-ico red"><i class="ti ti-heartbeat"></i></span><div class="min-w-0"><h5>Server health</h5><p>Live checks of this server.</p></div></div>
                        <button type="button" class="btn btn-sm btn-light border" wire:click="$refresh"><i class="ti ti-refresh"></i> Re-check</button>
                    </div>
                    <div class="ss-card-body">
                        @php
                            $checks = collect(['env', 'server', 'app'])->flatMap(fn ($g) => $info[$g])->pluck(2)->countBy();
                            $problems = ($checks['warn'] ?? 0) + ($checks['bad'] ?? 0);
                        @endphp
                        <div class="ss-health {{ ($checks['bad'] ?? 0) ? 'bad' : ($problems ? 'warn' : 'ok') }}">
                            <span class="ss-health-ico"><i class="ti {{ ($checks['bad'] ?? 0) ? 'ti-alert-octagon' : ($problems ? 'ti-alert-triangle' : 'ti-shield-check') }}"></i></span>
                            <span class="min-w-0">
                                <b>{{ ($checks['bad'] ?? 0) ? 'Needs attention' : ($problems ? 'Mostly healthy' : 'All good') }}</b>
                                <small>{{ $problems ? $problems . ' ' . \Illuminate\Support\Str::plural('item', $problems) . ' to review below.' : 'Every check passed.' }} Checked {{ now()->format('d M Y, h:i A') }}.</small>
                            </span>
                            <span class="ss-health-counts">
                                <span class="c ok">{{ $checks['ok'] ?? 0 }} ok</span>
                                @if($checks['warn'] ?? 0)<span class="c warn">{{ $checks['warn'] }} warning</span>@endif
                                @if($checks['bad'] ?? 0)<span class="c bad">{{ $checks['bad'] }} problem</span>@endif
                            </span>
                        </div>
                        <div class="ss-info">
                            @foreach(['env' => ['Application', 'ti-app-window'], 'server' => ['Server', 'ti-server-2'], 'app' => ['Storage & services', 'ti-database']] as $group => [$title, $boxIcon])
                                <div class="ss-info-box">
                                    <h6><i class="ti {{ $boxIcon }}"></i> {{ $title }}</h6>
                                    @foreach($info[$group] as [$label, $value, $state, $tip])
                                        <div class="ss-info-row">
                                            <span>{{ $label }}</span>
                                            <span class="s {{ $state }}"></span>
                                            <span class="min-w-0"><b>{{ $value }}</b>@if($tip)<small>{{ $tip }}</small>@endif</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                            @if($info['disk'])
                                <div class="ss-info-box">
                                    <h6><i class="ti ti-device-sd-card"></i> Disk space</h6>
                                    <div class="p-3">
                                        <div class="d-flex justify-content-between mb-2 small"><span class="text-muted">Used {{ $info['disk']['used_pct'] }}%</span><b>{{ number_format($info['disk']['free'] / 1073741824, 1) }} GB free of {{ number_format($info['disk']['total'] / 1073741824, 1) }} GB</b></div>
                                        <div class="ss-disk"><span style="width: {{ $info['disk']['used_pct'] }}%; {{ $info['disk']['used_pct'] > 90 ? 'background: #EF4444;' : '' }}"></span></div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="ss-card">
                    <div class="ss-card-head">
                        <div class="ss-title"><span class="ss-head-ico violet"><i class="ti ti-bolt"></i></span><div class="min-w-0"><h5>Cache &amp; optimisation</h5><p>The same as running the artisan commands on the server — no SSH needed.</p></div></div>
                    </div>
                    <div class="ss-card-body">
                        <div class="ss-cache-state mb-3">
                            @foreach($info['cache'] as $row)
                                <span class="ss-cache-pill {{ $row[1] ? 'on' : '' }}"><i class="ti {{ $row[1] ? 'ti-circle-check-filled' : 'ti-circle-dashed' }}"></i> {{ $row[0] }} <b>{{ $row[2] ?? ($row[1] ? 'cached' : 'not cached') }}</b></span>
                            @endforeach
                        </div>

                        @php
                            $actions = [
                                ['clearCache', 'Clear data cache', 'cache:clear + view:clear', 'Settings, permissions and page views. Use when a page still shows old content.', 'ti-eraser', 'warning', 'btn-light border', 'Clear cache', 'Clearing…',
                                    'Saved settings, permissions and compiled page views will be cleared and rebuilt on the next visit.', 'Yes, Clear'],
                                ['optimizeClear', 'Clear all caches', 'optimize:clear', 'Config, routes, views, events and compiled files. Use after changing .env.', 'ti-trash', 'danger', 'btn-outline-danger', 'Clear all', 'Clearing…',
                                    'Runs php artisan optimize:clear — config, routes, views, events and compiled files are cleared. The site may be a little slower until you optimise again.', 'Yes, Clear all'],
                                ['optimizeApp', 'Clear & optimise', 'optimize:clear + optimize', 'Clears everything, then rebuilds the config and route caches so the live site runs faster. Use after every upload.', 'ti-rocket', 'primary', 'btn-primary', 'Optimise', 'Optimising…',
                                    'Runs php artisan optimize:clear, then php artisan optimize — all caches are cleared and the config and route caches are rebuilt.', 'Yes, Optimise'],
                            ];
                        @endphp
                        <div class="ss-acts">
                            @foreach($actions as [$method, $title, $cmd, $desc, $icon, $variant, $btn, $label, $busy, $confirmText, $ok])
                                <div class="ss-act {{ $method === 'optimizeApp' ? 'is-reco' : '' }}">
                                    @if($method === 'optimizeApp')<span class="ss-act-tag">Recommended after upload</span>@endif
                                    <span class="ss-act-ico {{ $variant }}"><i class="ti {{ $icon }}"></i></span>
                                    <b>{{ $title }}</b>
                                    <code>{{ $cmd }}</code>
                                    <small>{{ $desc }}</small>
                                    <button type="button" class="btn {{ $btn }} w-100 mt-auto" wire:loading.attr="disabled" wire:target="clearCache,optimizeClear,optimizeApp"
                                            data-ss-confirm="{{ $method }}" data-icon="{{ $icon }}" data-variant="{{ $variant }}" data-title="{{ $title }}" data-text="{{ $confirmText }}" data-ok="{{ $ok }}">
                                        <span wire:loading.remove wire:target="{{ $method }}"><i class="ti {{ $icon }} me-1"></i> {{ $label }}</span>
                                        <span wire:loading wire:target="{{ $method }}"><span class="spinner-border spinner-border-sm me-1"></span> {{ $busy }}</span>
                                    </button>
                                </div>
                            @endforeach
                        </div>

                        @if(! app()->environment('production'))
                            <div class="ss-note info mt-3"><i class="ti ti-info-circle"></i><span>This is not the live server. After using <b>Optimise</b> here, run <b>Clear all</b> when you are done, otherwise later <code>.env</code> changes are ignored.</span></div>
                        @endif

                        <div class="mt-3">
                            @include('livewire.admin.settings.partials.terminal', [
                                'title' => 'output',
                                'lines' => $artisanResult ? explode("\n", $artisanResult['output']) : null,
                                'status' => $artisanResult ? ($artisanResult['ok'] ? 'ok' : 'fail') : null,
                                'running' => ['clearCache' => 'cache:clear && php artisan view:clear', 'optimizeClear' => 'optimize:clear', 'optimizeApp' => 'optimize:clear && php artisan optimize'],
                            ])
                        </div>
                    </div>
                </div>

                <div class="ss-card">
                    <div class="ss-card-head"><div class="ss-title"><span class="ss-head-ico slate"><i class="ti ti-terminal-2"></i></span><div class="min-w-0"><h5>Deploy checklist</h5><p>Run these over SSH after uploading a new version to the VPS.</p></div></div></div>
                    <div class="ss-card-body">
                        @include('livewire.admin.settings.partials.terminal', [
                            'title' => 'deploy — ~/app',
                            'status' => 'static',
                            'lines' => [
                                '# 1. install PHP packages (production only)',
                                '$ composer install --no-dev --optimize-autoloader',
                                '# 2. add new tables / columns (additive, keeps data)',
                                '$ php artisan migrate --force',
                                '# 3. rebuild caches — or press Optimise above',
                                '$ php artisan optimize:clear && php artisan optimize',
                                '# 4. refresh roles & permissions',
                                '$ php artisan permission:cache-reset',
                            ],
                        ])
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Confirm Modal (same look as the Delete modal; filled from the clicked button's data-* attributes) -->
    <div class="modal fade" id="ssConfirmModal" tabindex="-1" aria-hidden="true" wire:ignore>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <i class="ti ti-alert-triangle text-danger fs-1 mb-3" data-ss-icon></i>
                    <h5 data-ss-title>Confirm</h5>
                    <p class="text-muted mb-4" data-ss-text>Are you sure?</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger px-4" data-ss-ok>Yes</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('camp-theme-saved', function () { setTimeout(function () { window.location.reload(); }, 700); });

        (function () {
            var el = document.getElementById('ssConfirmModal');
            var method = null;
            var component = el.closest('[wire\\:id]');

            // Delegated, so buttons re-rendered by Livewire (tab changes) keep working.
            component.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-ss-confirm]');
                if (!btn || btn.disabled) return;
                e.preventDefault();
                method = btn.dataset.ssConfirm;
                var variant = btn.dataset.variant || 'danger';
                el.querySelector('[data-ss-icon]').className = 'ti ' + (btn.dataset.icon || 'ti-alert-triangle') + ' text-' + variant + ' fs-1 mb-3';
                el.querySelector('[data-ss-title]').textContent = btn.dataset.title || 'Confirm';
                el.querySelector('[data-ss-text]').textContent = btn.dataset.text || 'Are you sure?';
                var ok = el.querySelector('[data-ss-ok]');
                ok.className = 'btn btn-' + variant + ' px-4';
                ok.textContent = btn.dataset.ok || 'Yes';
                bootstrap.Modal.getOrCreateInstance(el).show();
            });

            // Terminal "copy" button: copies the visible text of that terminal.
            component.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-ss-copy]');
                if (!btn) return;
                var text = btn.closest('.ss-term-win').querySelector('[data-ss-copy-src]').innerText.replace(/\s+$/, '');
                var done = function () {
                    var icon = btn.querySelector('i');
                    icon.className = 'ti ti-check';
                    setTimeout(function () { icon.className = 'ti ti-copy'; }, 1400);
                };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(done);
                } else {
                    var ta = document.createElement('textarea');
                    ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
                    document.body.appendChild(ta); ta.select();
                    try { document.execCommand('copy'); done(); } catch (err) {}
                    ta.remove();
                }
            });

            el.querySelector('[data-ss-ok]').addEventListener('click', function () {
                bootstrap.Modal.getOrCreateInstance(el).hide();
                if (method) {
                    window.livewire.find(component.getAttribute('wire:id')).call(method);
                    method = null;
                }
            });
        })();
    </script>
</div>
