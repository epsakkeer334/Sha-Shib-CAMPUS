{{-- One KYC document: compact card with preview, status and verify / reject / re-review.
     Params: $doc, $locked (Gate 1 approved → read-only) --}}
@php
    [$statusLabel, $statusClass] = match (true) {
        $doc->verification_status === 'verified' => ['Verified', 'dv-badge-ok'],
        $doc->verification_status === 'rejected' => ['Rejected', 'dv-badge-bad'],
        (bool) $doc->previous_rejection => ['Re-uploaded', 'dv-badge-info'],
        default => ['Pending', 'dv-badge-warn'],
    };
    $url = route('admin.students.documents.show', $doc->id);
    $isPdf = !$doc->is_image;
    $actionable = !$locked && in_array($doc->verification_status, ['pending', 'rejected'], true);
@endphp
<div class="col-md-6 col-xxl-3">
    <div class="dv-doc {{ $doc->verification_status === 'pending' && !$locked ? 'dv-doc-pending' : '' }} {{ $doc->verification_status === 'rejected' ? 'dv-doc-rejected' : '' }}">
        <a href="{{ $url }}" target="_blank" class="dv-doc-preview" title="Open {{ $doc->original_name }}">
            @if($isPdf)
                <span class="dv-doc-pdf"><i class="ti ti-file-type-pdf"></i><span>PDF</span></span>
            @else
                <img src="{{ $url }}" alt="{{ $doc->type_label }}" loading="lazy">
            @endif
            <span class="dv-doc-open"><i class="ti ti-external-link"></i> Open</span>
        </a>

        <div class="dv-doc-body">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="min-w-0">
                    <div class="dv-doc-title text-truncate">{{ $doc->type_label }}</div>
                    <div class="dv-doc-meta text-truncate" title="{{ $doc->original_name }}">{{ $doc->size_label }} · {{ $doc->uploaded_at->format('d M, H:i') }}</div>
                </div>
                <span class="dv-badge {{ $statusClass }}">{{ $statusLabel }}</span>
            </div>

            @if($doc->verification_status !== 'pending')
                <div class="dv-doc-note">
                    <i class="ti ti-user-check"></i> {{ optional($doc->verifier)->name ?? '—' }} · {{ optional($doc->verified_at)->format('d M') }}
                    @if($doc->remarks)<div class="text-truncate" title="{{ $doc->remarks }}">“{{ $doc->remarks }}”</div>@endif
                </div>
            @elseif($doc->previous_rejection)
                <div class="dv-doc-note"><i class="ti ti-history"></i> Previously rejected: <span title="{{ $doc->previous_rejection }}">{{ \Illuminate\Support\Str::limit($doc->previous_rejection, 60) }}</span></div>
            @endif

            @if($actionable)
                <textarea rows="1" class="form-control form-control-sm dv-remarks @error('remarks.' . $doc->id) is-invalid @enderror"
                          wire:model.defer="remarks.{{ $doc->id }}" aria-label="Remarks for {{ $doc->type_label }}"
                          placeholder="{{ $doc->verification_status === 'rejected' ? 'Re-review note (optional)' : 'Remarks (required to reject)' }}"></textarea>
                @error('remarks.' . $doc->id) <div class="invalid-feedback d-block small">{{ $message }}</div> @enderror

                @if($doc->verification_status === 'pending')
                    <div class="dv-doc-actions">
                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="reject({{ $doc->id }})" wire:loading.attr="disabled">
                            <i class="ti ti-x"></i> Reject
                        </button>
                        <button type="button" class="btn btn-sm btn-success" wire:click="verify({{ $doc->id }})" wire:loading.attr="disabled">
                            <i class="ti ti-check"></i> Verify
                        </button>
                    </div>
                @else
                    {{-- Re-review of a rejection made by mistake --}}
                    <div class="dv-doc-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="reopen({{ $doc->id }})" wire:loading.attr="disabled" title="Move back to pending">
                            <i class="ti ti-arrow-back-up"></i> To pending
                        </button>
                        <button type="button" class="btn btn-sm btn-success" wire:click="approveRejected({{ $doc->id }})" wire:loading.attr="disabled" title="Approve after re-review">
                            <i class="ti ti-rotate-clockwise"></i> Approve
                        </button>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
