{{-- Shared look of the onboarding queues (Module 2 design: queue tabs, selected row, document cards). --}}
<style>
    .queue-tabs { background: #ECEEEA; }
    .queue-tabs .btn { border: 0; min-height: 36px; }
    .queue-tab-active { background: #fff; color: #17201B; box-shadow: 0 1px 2px rgba(23, 32, 27, .12); }
    .onboarding-queue { max-height: 70vh; overflow-y: auto; }
    .onboarding-queue .list-group-item { border-left: 0; border-right: 0; }
    .onboarding-queue .active-queue { background: #E3F0EA; }
    .doc-card { background: #fff; border: 1px solid #DCDFD8; border-radius: 12px; overflow: hidden; }
    .doc-card-active { border: 2px solid #0E6B55; }
    .doc-card-missing { background: #FAFBF9; border: 1.5px dashed #C9CEC6; min-height: 200px; }
    .doc-preview { height: 150px; background: #ECEEEA; overflow: hidden; }
    .doc-preview img { width: 100%; height: 100%; object-fit: cover; }
    .amount { font-family: 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, monospace; white-space: nowrap; }
    .gate-card-dark { background: #17201B; color: #fff; border-radius: 12px; }
    .gate-card-dark .label { color: #A9B6AE; font-size: 12px; letter-spacing: .06em; text-transform: uppercase; }
    .step-dot { width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .step-done { background: #0E6B55; color: #fff; }
    .step-current { border: 2px solid #B4610E; background: #FBEFD9; }
    .step-todo { border: 2px solid #C9CEC6; }
    .id-card-preview { width: 340px; max-width: 100%; aspect-ratio: 85.6 / 54; border: 1px solid #C9CEC6; border-radius: 12px; overflow: hidden; box-shadow: 0 6px 18px rgba(23, 32, 27, .10); background: #fff; }
    .id-card-preview .band { background: #0E6B55; color: #fff; font-size: 11px; letter-spacing: .04em; }
</style>
