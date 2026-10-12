{{-- Shared styles of the Monitoring pages (Audit Trail, Notification Log) --}}
<style>
    .mon-ui { --mon-border: #E5E7EB; --mon-soft: #F1F2F4; --mon-ink: #111827; --mon-muted: #6B7280; --mon-accent: #F26522; }
    .mon-ui .min-w-0 { min-width: 0; }
    .mon-ui .mon-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; padding-bottom: 14px; border-bottom: 1px dashed var(--mon-border); }
    .mon-ui .mon-head h2 { font-size: 22px; color: var(--mon-ink); }
    .mon-ui .mon-count { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 999px; background: #fff; border: 1px solid var(--mon-border); font-size: 13px; font-weight: 600; color: #374151; }
    .mon-ui .mon-count i { color: var(--mon-accent); }

    .mon-ui .mon-panel { background: #fff; border: 1px solid var(--mon-border); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); overflow: hidden; }
    .mon-ui .mon-filters { padding: 14px 16px 10px; border-bottom: 1px solid var(--mon-soft); background: #FCFCFD; display: flex; flex-direction: column; gap: 10px; }
    .mon-ui .mon-search { position: relative; }
    .mon-ui .mon-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9CA3AF; font-size: 17px; }
    .mon-ui .mon-search input { padding-left: 38px; border-radius: 10px; height: 40px; }
    .mon-ui .mon-filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 8px; }
    .mon-ui .mon-filter-grid .form-select, .mon-ui .mon-filter-grid .form-control { border-radius: 8px; }
    .mon-ui .mon-dates { display: flex; align-items: center; gap: 6px; grid-column: span 2; min-width: 0; }
    .mon-ui .mon-dates input { min-width: 0; }
    .mon-ui .mon-quick { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    .mon-ui .mon-pill { border: 1px solid var(--mon-border); background: #fff; border-radius: 999px; padding: 3px 12px; font-size: 12.5px; color: #374151; }
    .mon-ui .mon-pill:hover { border-color: #FDBA8C; color: #C2410C; }
    .mon-ui .mon-pill.is-active { background: var(--mon-accent); border-color: var(--mon-accent); color: #fff; }

    .mon-ui .mon-table thead th { background: #F9FAFB; font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--mon-muted); padding: 10px 16px; white-space: nowrap; border-bottom: 1px solid var(--mon-border); }
    .mon-ui .mon-table td { padding: 10px 16px; border-color: var(--mon-soft); font-size: 13.5px; }
    .mon-ui .mon-day td { background: #F8FAFC; color: #475569; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: 7px 16px; }
    .mon-ui .mon-day i { color: var(--mon-accent); margin-right: 4px; }
    .mon-ui .mon-row { cursor: pointer; transition: background .12s; }
    .mon-ui .mon-row:hover td { background: #FFFBF7; }
    .mon-ui .mon-row:hover .ti-chevron-right { color: var(--mon-accent) !important; }
    .mon-ui .mon-sub { font-size: 12px; color: var(--mon-muted); }
    .mon-ui .mon-desc { color: #1F2937; max-width: 520px; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .mon-ui .mon-mono { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 12px; color: #374151; }
    .mon-ui .mon-avatar { width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; background: #EEF2FF; color: #4338CA; font-size: 12.5px; font-weight: 700; }
    .mon-ui .mon-avatar.is-system { background: #F1F5F9; color: #64748B; font-size: 16px; }
    .mon-ui .mon-chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; background: var(--tone-soft); color: var(--tone); }
    .mon-ui .mon-empty { text-align: center; color: var(--mon-muted); padding: 48px 12px !important; }
    .mon-ui .mon-empty > i { font-size: 36px; color: #D1D5DB; display: block; margin-bottom: 6px; }
    .mon-ui .mon-loading { display: none; position: absolute; inset: 0; z-index: 2; background: rgba(255, 255, 255, .6); align-items: center; justify-content: center; }
    .mon-ui .mon-foot { padding: 12px 16px; border-top: 1px solid var(--mon-soft); }
    .mon-ui .mon-foot .pagination { margin: 0; }

    /* tones */
    .mon-ui .mon-tone-green { --tone: #15803D; --tone-soft: #DCFCE7; } .mon-ui .mon-tone-blue { --tone: #1D4ED8; --tone-soft: #DBEAFE; }
    .mon-ui .mon-tone-red { --tone: #B91C1C; --tone-soft: #FEE2E2; } .mon-ui .mon-tone-teal { --tone: #0F766E; --tone-soft: #CCFBF1; }
    .mon-ui .mon-tone-amber { --tone: #B45309; --tone-soft: #FEF3C7; } .mon-ui .mon-tone-slate { --tone: #475569; --tone-soft: #F1F5F9; }
    .mon-ui .mon-tone-violet { --tone: #6D28D9; --tone-soft: #EDE9FE; }

    /* detail pop-up */
    .mon-ui .mon-modal { border-radius: 16px; overflow: hidden; }
    .mon-ui .mon-modal .modal-header { align-items: flex-start; padding: 18px 20px; background: linear-gradient(90deg, #FFF7F2 0%, #fff 70%); border-bottom: 1px solid var(--mon-soft); }
    .mon-ui .mon-modal .modal-title { font-size: 16px; font-weight: 700; color: var(--mon-ink); line-height: 1.4; }
    .mon-ui .mon-modal .modal-body { padding: 18px 20px 22px; }
    .mon-ui .mon-modal-icon { width: 42px; height: 42px; border-radius: 12px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 20px; background: var(--tone-soft); color: var(--tone); }
    .mon-ui .mon-facts { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); background: #fff; border: 1px solid var(--mon-border); border-radius: 12px; overflow: hidden; }
    .mon-ui .mon-facts > div { background: #fff; padding: 10px 14px; display: flex; flex-direction: column; gap: 1px; min-width: 0; box-shadow: 1px 1px 0 var(--mon-border); } /* cell lines; the outer edge is clipped by the rounded border */
    .mon-ui .mon-facts span { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--mon-muted); }
    .mon-ui .mon-facts strong { font-size: 13.5px; color: var(--mon-ink); font-weight: 600; overflow-wrap: anywhere; }
    .mon-ui .mon-facts small { font-size: 12px; color: var(--mon-muted); overflow-wrap: anywhere; }
    .mon-ui .mon-section { margin: 18px 0 8px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #475569; }
    .mon-ui .mon-callout { display: flex; gap: 10px; margin-top: 14px; padding: 10px 14px; border-radius: 10px; background: #FFFBEB; border: 1px solid #FDE68A; font-size: 13.5px; color: #78350F; }
    .mon-ui .mon-callout.is-error { background: #FEF2F2; border-color: #FECACA; color: #991B1B; }
    .mon-ui .mon-callout i { font-size: 18px; margin-top: 1px; }
    .mon-ui .mon-diff th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .04em; color: var(--mon-muted); background: #F9FAFB; }
    .mon-ui .mon-diff td { font-size: 13px; vertical-align: top; }
    .mon-ui .mon-diff tr.is-changed .mon-before { background: #FEF2F2; color: #991B1B; text-decoration: line-through; text-decoration-color: rgba(153, 27, 27, .35); }
    .mon-ui .mon-diff tr.is-changed .mon-after { background: #F0FDF4; color: #166534; font-weight: 600; }
    .mon-ui .mon-kv { display: grid; grid-template-columns: minmax(120px, auto) 1fr; gap: 6px 16px; margin: 0; font-size: 13px; }
    .mon-ui .mon-kv dt { color: var(--mon-muted); font-weight: 500; }
    .mon-ui .mon-kv dd { margin: 0; color: var(--mon-ink); }
    .mon-ui .mon-message { white-space: pre-wrap; font-size: 14px; line-height: 1.6; color: #1F2937; background: #F9FAFB; border: 1px solid var(--mon-border); border-radius: 12px; padding: 14px 16px; margin: 0; font-family: inherit; }
    .mon-ui .mon-subject { font-size: 15px; font-weight: 700; color: var(--mon-ink); margin-bottom: 8px; }

    /* --- audit pop-up --- */
    .mon-ui .am-hero { position: relative; display: flex; gap: 16px; align-items: flex-start; padding: 22px 52px 20px 22px; background: linear-gradient(135deg, var(--tone-soft) 0%, #fff 75%); border-bottom: 1px solid var(--mon-soft); }
    .mon-ui .am-close { position: absolute; top: 18px; right: 18px; }
    .mon-ui .am-hero-icon { width: 48px; height: 48px; border-radius: 14px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; background: var(--tone); color: #fff; box-shadow: 0 6px 14px rgba(16, 24, 40, .15); }
    .mon-ui .am-kicker { font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--tone, var(--mon-muted)); }
    .mon-ui .am-title { margin: 3px 0 4px; font-size: 17px; font-weight: 700; color: var(--mon-ink); line-height: 1.4; }
    .mon-ui .am-byline { font-size: 13px; color: var(--mon-muted); }
    .mon-ui .am-byline strong { color: #374151; }
    .mon-ui .am-info { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .mon-ui .am-info li { display: flex; gap: 12px; align-items: flex-start; padding: 12px 14px; border: 1px solid var(--mon-border); border-radius: 12px; background: #fff; min-width: 0; }
    .mon-ui .am-info-icon { width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; background: #F3F4F6; color: #4B5563; font-size: 17px; }
    .mon-ui .am-info div { display: flex; flex-direction: column; min-width: 0; }
    .mon-ui .am-info span { font-size: 11.5px; color: var(--mon-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
    .mon-ui .am-info strong { font-size: 14px; color: var(--mon-ink); overflow-wrap: anywhere; }
    .mon-ui .am-info small { font-size: 12px; color: var(--mon-muted); overflow-wrap: anywhere; }
    .mon-ui .am-reason { display: flex; gap: 12px; margin-top: 14px; padding: 12px 16px; border-radius: 12px; background: #FFFBEB; border-left: 4px solid #F59E0B; }
    .mon-ui .am-reason > i { font-size: 22px; color: #D97706; }
    .mon-ui .am-reason span { font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #B45309; }
    .mon-ui .am-reason p { margin: 2px 0 0; font-size: 14px; color: #78350F; }
    .mon-ui .am-section-head { display: flex; align-items: center; justify-content: space-between; margin: 20px 0 8px; }
    .mon-ui .am-count { margin-left: 4px; padding: 0 7px; border-radius: 999px; background: #F3F4F6; color: #374151; font-size: 11px; }
    .mon-ui .am-link { border: 0; background: none; padding: 0; color: var(--mon-accent); font-size: 13px; font-weight: 600; }
    .mon-ui .am-changes { border: 1px solid var(--mon-border); border-radius: 12px; overflow: hidden; }
    .mon-ui .am-change { display: grid; grid-template-columns: 180px minmax(0, 1fr); gap: 12px; align-items: center; padding: 10px 14px; border-bottom: 1px solid var(--mon-soft); }
    .mon-ui .am-change:last-child { border-bottom: 0; }
    .mon-ui .am-change.is-same { background: #FAFAFA; color: var(--mon-muted); }
    .mon-ui .am-field { font-size: 13px; font-weight: 600; color: #374151; }
    .mon-ui .am-values { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 13.5px; min-width: 0; }
    .mon-ui .am-old { padding: 2px 8px; border-radius: 6px; background: #FEF2F2; color: #991B1B; text-decoration: line-through; text-decoration-color: rgba(153, 27, 27, .4); overflow-wrap: anywhere; }
    .mon-ui .am-new { padding: 2px 8px; border-radius: 6px; background: #F0FDF4; color: #166534; font-weight: 600; overflow-wrap: anywhere; }
    .mon-ui .am-arrow { color: #9CA3AF; }
    .mon-ui .am-tag { font-size: 11px; font-weight: 600; color: var(--mon-muted); text-transform: uppercase; letter-spacing: .04em; }
    .mon-ui .am-tech { margin-top: 18px; border: 1px dashed var(--mon-border); border-radius: 12px; padding: 0 14px; }
    .mon-ui .am-tech summary { padding: 10px 0; font-size: 13px; font-weight: 600; color: var(--mon-muted); cursor: pointer; list-style: none; }
    .mon-ui .am-tech summary::-webkit-details-marker { display: none; }
    .mon-ui .am-tech summary::after { content: '\25BE'; float: right; transition: transform .2s; }
    .mon-ui .am-tech[open] summary::after { transform: rotate(180deg); }
    .mon-ui .am-tech[open] { padding-bottom: 12px; }
    .mon-ui .mon-modal .modal-footer { padding: 12px 20px; border-top: 1px solid var(--mon-soft); background: #FCFCFD; }

    /* --- notification pop-up --- */
    .mon-ui .nm-head { align-items: center !important; }
    .mon-ui .nm-status { display: flex; gap: 12px; align-items: center; padding: 12px 16px; border-radius: 12px; background: var(--tone-soft); color: var(--tone); margin-bottom: 16px; }
    .mon-ui .nm-status > i { font-size: 24px; }
    .mon-ui .nm-status strong { display: block; font-size: 14px; }
    .mon-ui .nm-status span { font-size: 13px; opacity: .9; }
    .mon-ui .nm-mail { border: 1px solid var(--mon-border); border-radius: 14px; overflow: hidden; box-shadow: 0 2px 8px rgba(16, 24, 40, .05); }
    .mon-ui .nm-mail-head { display: grid; grid-template-columns: 72px minmax(0, 1fr); gap: 6px 12px; margin: 0; padding: 14px 18px; background: #F9FAFB; border-bottom: 1px solid var(--mon-border); font-size: 13.5px; }
    .mon-ui .nm-mail-head dt { color: var(--mon-muted); font-weight: 500; }
    .mon-ui .nm-mail-head dd { margin: 0; color: var(--mon-ink); overflow-wrap: anywhere; }
    .mon-ui .nm-who { margin-left: 6px; padding: 1px 8px; border-radius: 999px; background: #EEF2FF; color: #4338CA; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
    .mon-ui .nm-mail-body { padding: 22px 24px; white-space: pre-wrap; font-size: 14.5px; line-height: 1.7; color: #1F2937; background: #fff; min-height: 120px; }
    .mon-ui .nm-sms { padding: 18px; border-radius: 16px; background: #F3F4F6; }
    .mon-ui .nm-sms-to { font-size: 13px; color: var(--mon-muted); margin-bottom: 10px; }
    .mon-ui .nm-bubble { max-width: 85%; padding: 12px 16px; border-radius: 18px 18px 18px 4px; background: #fff; box-shadow: 0 1px 2px rgba(16, 24, 40, .08); white-space: pre-wrap; font-size: 14.5px; line-height: 1.55; color: #1F2937; }
    .mon-ui .nm-sms-time { margin-top: 6px; font-size: 11.5px; color: var(--mon-muted); }

    @media (max-width: 575.98px) {
        .mon-ui .am-info { grid-template-columns: 1fr; }
        .mon-ui .am-change { grid-template-columns: 1fr; gap: 4px; }
        .mon-ui .nm-mail-head { grid-template-columns: 1fr; }
    }
    @media (max-width: 575.98px) {
        .mon-ui .mon-dates { grid-column: auto; flex-wrap: wrap; }
        .mon-ui .mon-kv { grid-template-columns: 1fr; }
    }
</style>
