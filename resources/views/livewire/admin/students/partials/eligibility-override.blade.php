{{-- Review tab: course eligibility result + "Allow with reason". Params: academic (StudentAcademicDetail|null), canOverride --}}
@if($academic && in_array($academic->eligibility_status, ['not_eligible', 'overridden'], true))
    @php $reasons = $academic->eligibility_snapshot['reasons'] ?? []; @endphp
    @if($academic->eligibility_status === 'not_eligible')
        <div class="so-alert so-alert-bad mb-3">
            <i class="ti ti-school-off"></i>
            <div class="flex-grow-1">
                <strong>Not eligible for the course (12th criteria)</strong>
                <ul class="mb-0 mt-1 ps-3">@foreach($reasons as $reason)<li>{{ $reason }}</li>@endforeach</ul>
                @if($canOverride)
                    <div class="el-override mt-2">
                        <label class="form-label fw-medium small mb-1" for="eligibility_reason">Allow anyway — reason (recorded in the audit trail)</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <input id="eligibility_reason" type="text" class="form-control form-control-sm flex-grow-1 @error('eligibility_reason') is-invalid @enderror" style="min-width: 220px;"
                                   wire:model.defer="eligibility_reason" placeholder="e.g. Marksheet verified: Physics 52 (typing error)">
                            <button type="button" class="btn btn-sm btn-warning" wire:click="overrideEligibility" wire:loading.attr="disabled">
                                <i class="ti ti-shield-check me-1"></i> Allow with reason
                            </button>
                        </div>
                        @error('eligibility_reason')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="so-alert so-alert-warn mb-3">
            <i class="ti ti-shield-check"></i>
            <div><strong>Eligibility allowed by admin</strong> — {{ optional($academic->eligibilityOverrider)->name }}, {{ optional($academic->eligibility_override_at)->format('d M Y, h:i A') }}.
                Reason: {{ $academic->eligibility_override_reason }}
                @if($reasons)<div class="small mt-1">Criteria not met: {{ implode(' ', $reasons) }}</div>@endif
                <div class="small mt-1">Changing the course, board, stream or marks checks the criteria again.</div></div>
        </div>
    @endif
@endif
