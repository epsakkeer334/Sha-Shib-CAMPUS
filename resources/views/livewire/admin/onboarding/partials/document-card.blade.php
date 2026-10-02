{{-- One KYC document with verify / reject. Params: $doc, $locked (gate approved → read-only) --}}
@php
    $statusColour = ['verified' => 'success', 'rejected' => 'danger'][$doc->verification_status] ?? 'info';
    $statusLabel = $doc->verification_status === 'pending' && $doc->previous_rejection ? 'Re-uploaded' : ucfirst($doc->verification_status);
    $url = route('admin.students.documents.show', $doc->id);
@endphp
<div class="col-md-6">
    <div class="doc-card h-100 {{ $doc->verification_status === 'pending' && !$locked ? 'doc-card-active' : '' }}">
        <a href="{{ $url }}" target="_blank" class="doc-preview d-flex align-items-center justify-content-center text-decoration-none" title="Open {{ $doc->original_name }}">
            @if($doc->is_image)
                <img src="{{ $url }}" alt="{{ $doc->type_label }}" loading="lazy">
            @else
                <span class="text-center text-muted small"><i class="ti ti-file-type-pdf fs-1 text-danger d-block"></i>{{ \Illuminate\Support\Str::limit($doc->original_name, 40) }}</span>
            @endif
        </a>
        <div class="p-3 d-flex flex-column gap-2">
            <div class="d-flex justify-content-between align-items-center gap-2">
                <span class="fw-semibold">{{ $doc->type_label }}</span>
                <span class="badge badge-soft-{{ $statusColour }}">{{ $statusLabel }}</span>
            </div>
            <span class="small text-muted">{{ $doc->size_label }} · uploaded {{ $doc->uploaded_at->format('d M, H:i') }}</span>

            @if($doc->verification_status !== 'pending')
                <span class="small text-muted">
                    by {{ optional($doc->verifier)->name ?? '—' }} · {{ optional($doc->verified_at)->format('d M, H:i') }}
                    @if($doc->remarks) — “{{ $doc->remarks }}” @endif
                </span>
            @elseif($doc->previous_rejection)
                <span class="small text-muted">Previously rejected: {{ $doc->previous_rejection }}</span>
            @endif

            @if($doc->verification_status === 'rejected' && !$locked)
                {{-- Re-review: the rejection was a mistake → approve, or put it back in review --}}
                <div class="rounded p-2 small" style="background: #FBF4F3; color: #6E1E19;">
                    Rejected by mistake? Review the file again, then approve it or move it back to pending.
                </div>
                <div>
                    <label class="form-label small fw-medium mb-1" for="remarks{{ $doc->id }}">Re-review note</label>
                    <textarea id="remarks{{ $doc->id }}" rows="2" class="form-control form-control-sm" wire:model.defer="remarks.{{ $doc->id }}"
                              placeholder="Optional, e.g. checked the original — marks are visible"></textarea>
                </div>
                <div class="d-grid gap-2" style="grid-template-columns: 1fr 1.3fr;">
                    <button type="button" class="btn btn-outline-secondary" wire:click="reopen({{ $doc->id }})" wire:loading.attr="disabled">Move back to pending</button>
                    <button type="button" class="btn btn-success" wire:click="approveRejected({{ $doc->id }})" wire:loading.attr="disabled">
                        <i class="ti ti-rotate-clockwise me-1"></i>Approve after re-review
                    </button>
                </div>
            @endif

            @if($doc->verification_status === 'pending' && !$locked)
                <div>
                    <label class="form-label small fw-medium mb-1" for="remarks{{ $doc->id }}">Remarks</label>
                    <textarea id="remarks{{ $doc->id }}" rows="2" class="form-control form-control-sm @error('remarks.' . $doc->id) is-invalid @enderror"
                              wire:model.defer="remarks.{{ $doc->id }}" placeholder="Required when rejecting"></textarea>
                    @error('remarks.' . $doc->id) <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="d-grid gap-2" style="grid-template-columns: 1fr 1fr;">
                    <button type="button" class="btn btn-outline-danger" wire:click="reject({{ $doc->id }})" wire:loading.attr="disabled">Reject</button>
                    <button type="button" class="btn btn-success" wire:click="verify({{ $doc->id }})" wire:loading.attr="disabled">Verify</button>
                </div>
            @endif
        </div>
    </div>
</div>
