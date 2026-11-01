<button type="submit" class="btn btn-primary px-4" wire:loading.attr="disabled" wire:target="save">
    <span wire:loading.remove wire:target="save"><i class="ti ti-device-floppy me-1"></i> {{ $saveLabel ?? 'Save changes' }}</span>
    <span wire:loading wire:target="save"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
</button>
