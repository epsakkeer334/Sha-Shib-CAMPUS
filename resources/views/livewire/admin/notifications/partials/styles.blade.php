{{-- Shared notification look (bell dropdown + notifications page) --}}
<style>
    .nt-item { display: flex; gap: 10px; padding: 12px 14px; border-bottom: 1px solid #F1F2F4; background: #fff; transition: background .12s; }
    .nt-item:hover { background: #FAFAFB; }
    .nt-item.is-unread { background: #FFF8F3; }
    .nt-item.is-unread:hover { background: #FFF1E7; }
    .nt-item .min-w-0 { min-width: 0; }
    .nt-icon { width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
    .nt-compact .nt-icon { width: 32px; height: 32px; font-size: 16px; }
    .nt-title { font-weight: 600; font-size: 13.5px; color: #111827; line-height: 1.3; }
    .nt-item:not(.is-unread) .nt-title { font-weight: 500; color: #374151; }
    .nt-message { font-size: 12.5px; color: #4B5563; margin-top: 2px; line-height: 1.4; }
    .nt-compact .nt-message { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .nt-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 10px; margin-top: 5px; font-size: 11.5px; color: #9CA3AF; }
    .nt-meta i { font-size: 12px; }
    .nt-stage { padding: 1px 8px; border-radius: 999px; font-weight: 600; font-size: 10.5px; }
    .nt-student { color: #374151; font-weight: 500; }
    .nt-dot { width: 8px; height: 8px; border-radius: 50%; background: #F26522; flex-shrink: 0; margin-top: 5px; box-shadow: 0 0 0 3px rgba(242, 101, 34, .15); }
    .nt-tone-indigo { background: #EEF2FF; color: #4338CA; }
    .nt-tone-amber { background: #FEF3C7; color: #B45309; }
    .nt-tone-green { background: #DCFCE7; color: #15803D; }
    .nt-tone-red { background: #FEE2E2; color: #DC2626; }
    .nt-tone-sky { background: #E0F2FE; color: #0369A1; }
</style>
