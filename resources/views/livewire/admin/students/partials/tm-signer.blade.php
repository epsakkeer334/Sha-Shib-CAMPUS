{{-- Which Training Manager physically signed. Params: $model (Livewire property), $managers (users) --}}
@if($managers->isNotEmpty())
    <div class="input-group input-group-sm tm-signer" style="max-width: 280px;">
        <span class="input-group-text"><i class="ti ti-signature"></i></span>
        <select class="form-select" wire:model.defer="{{ $model }}" aria-label="Training Manager who signed">
            <option value="">Signed by (Training Manager)…</option>
            @foreach($managers as $tm)<option value="{{ $tm->id }}">{{ $tm->name }}</option>@endforeach
        </select>
    </div>
@else
    <span class="small text-muted tm-signer" title="Add a Training Manager user for this institute to record the real signer">
        <i class="ti ti-info-circle"></i> No Training Manager account — recorded under your name
    </span>
@endif
