{{-- Segmented queue tabs bound to the component's $tab. Params: $tabs (key => label) --}}
<div class="queue-tabs d-inline-flex gap-1 p-1 rounded-3" role="tablist" aria-label="Queue">
    @foreach($tabs as $key => $label)
        <button type="button" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                class="btn btn-sm px-3 {{ $tab === $key ? 'queue-tab-active fw-semibold' : 'text-muted' }}"
                wire:click="$set('tab', '{{ $key }}')">{{ $label }}</button>
    @endforeach
</div>
