@php
    $tick = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8.5 L6.5 12 L13 4.5"></path></svg>';
    $uploadIcon = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16 V4 M7 9 L12 4 L17 9"></path><path d="M4 16 V20 H20 V16"></path></svg>';
    $badge = fn ($doc) => match ($doc->verification_status) {
        'verified' => ['b-ok', 'Verified'],
        'rejected' => ['b-danger', 'Re-upload needed'],
        default => $student->submitted_at ? ['b-info', 'Being checked'] : ['b-ok', 'Uploaded'],
    };
@endphp
<main class="main wide">
    <header class="page-head rise">
        <span class="eyebrow">Step 3 of 4 · Admission {{ now()->year }}</span>
        <h1 class="title">Upload your documents</h1>
        <p class="lead">Clear photos or scans · JPG, PNG or PDF · up to 5 MB each.</p>
    </header>

    <div class="flow">
    @include('portal.partials.steps', ['current' => 'documents', 'student' => $student])
    <div class="flow-main">

    <section class="card flush">
        <div style="padding: 20px 24px; border-bottom: 1px solid var(--line-2);"><div class="card-head"><span class="ico" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5.5 2.5 H11.5 L15 6 V17.5 H5.5 Z"></path><path d="M11.5 2.5 V6 H15"></path><path d="M8 10 H12.5 M8 13 H12.5"></path></svg></span><span style="display: flex; flex-direction: column; min-width: 0;"><h2>Your documents</h2><span class="sub">Only the admissions team can see these files</span></span></div></div>
        @foreach($types as $type => [$label, $required, $mimes, $maxKb])
            @php
                $docs = $documents->get($type, collect());
                $accept = collect(explode(',', $mimes))->map(fn ($m) => '.' . $m)->implode(',');
                $canUpload = $uploadable[$type];
            @endphp

            @foreach($docs as $doc)
                @php [$cls, $text] = $badge($doc); @endphp
                <div class="docrow" wire:key="doc-{{ $doc->id }}">
                    <a class="doc-icon" href="{{ route('portal.document', $doc->id) }}" target="_blank" title="Open {{ $doc->original_name }}">
                        @if($doc->is_image)<img src="{{ route('portal.document', $doc->id) }}" alt="">@else PDF @endif
                    </a>
                    <div style="display: flex; flex-direction: column; min-width: 0;">
                        <span style="font-weight: 600;">{{ $label }}@if($required) *@endif</span>
                        <span class="muted" style="font-size: 14px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $doc->original_name }} · {{ $doc->size_label }}</span>
                        @if($doc->verification_status === 'rejected' && $doc->remarks)
                            <span style="font-size: 14px; color: var(--danger);">“{{ $doc->remarks }}”</span>
                        @endif
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: flex-end;">
                        <span class="badge {{ $cls }}">@if($cls === 'b-ok'){!! $tick !!}@endif{{ $text }}</span>
                        @if($canUpload && $type !== 'other')
                            <label class="btn btn-secondary btn-sm" style="cursor: pointer;">
                                {{ $doc->verification_status === 'rejected' ? 'Re-upload' : 'Replace' }}
                                <input type="file" class="visually-hidden" wire:model="upload_{{ $type }}" accept="{{ $accept }}">
                            </label>
                        @endif
                        @if($canUpload && ($type === 'other' || !$student->submitted_at) && $doc->verification_status !== 'verified')
                            <button type="button" class="link-btn" style="color: var(--danger); font-weight: 500;" wire:click="remove({{ $doc->id }})">Remove</button>
                        @endif
                    </div>
                </div>
            @endforeach

            @if($docs->isEmpty() && $type !== 'other')
                <div style="padding: 16px 24px; border-bottom: 1px solid var(--line-2); display: flex; flex-direction: column; gap: 10px;" wire:key="empty-{{ $type }}">
                    <span style="font-weight: 600;">{{ $label }}@if($required) *@endif</span>
                    @if($canUpload)
                        <label class="dropzone" style="flex-direction: row; justify-content: center; gap: 12px;">
                            {!! $uploadIcon !!}
                            <span style="display: flex; flex-direction: column; text-align: left;"><span><b style="color: var(--brand);">Choose a file</b> or drag it here</span>
                            <span class="muted" style="font-size: 12.5px;">On a phone you can take a photo</span></span>
                            <input type="file" class="visually-hidden" wire:model="upload_{{ $type }}" accept="{{ $accept }}">
                        </label>
                    @else
                        <span class="muted">Not uploaded</span>
                    @endif
                </div>
            @endif

            @if($type === 'other' && $canUpload)
                <div class="docrow" wire:key="add-other">
                    <div class="doc-icon" style="background: #EDF0F7; color: var(--muted);"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M10 4 V16 M4 10 H16"></path></svg></div>
                    <div style="display: flex; flex-direction: column;"><span style="font-weight: 600;">{{ $label }}</span><span class="muted" style="font-size: 14px;">Optional, e.g. transfer or community certificate</span></div>
                    <label class="btn btn-secondary btn-sm" style="cursor: pointer;">Add file
                        <input type="file" class="visually-hidden" wire:model="upload_other" accept="{{ $accept }}">
                    </label>
                </div>
            @endif

            <div wire:key="uploading-{{ $type }}" wire:loading wire:target="upload_{{ $type }}" style="padding: 10px 24px;" class="muted"><span class="spin" style="border-color: rgba(var(--brand-rgb), .25); border-top-color: var(--brand); vertical-align: -3px;"></span> Uploading {{ strtolower($label) }}…</div>
            @error('upload_' . $type)<div class="error" style="padding: 10px 24px;" wire:key="error-{{ $type }}">{{ $message }}</div>@enderror
        @endforeach
        <div class="hint" style="padding: 14px 24px; background: rgba(242, 245, 252, .7);">After you submit, our admissions team checks each document. Anything to redo shows on your status page and comes as a message.</div>
    </section>

    @if($canSubmit && $missing)
        <div class="notice info" role="status">
            <div style="display: flex; flex-direction: column; gap: 4px;">
                <strong>Before you submit</strong>
                @foreach($missing as $section => $items)
                    @foreach($items as $item)
                        <span>{{ $item }}
                            @if($section === 'address')<a href="{{ route('portal.details') }}">Fix</a>@elseif($section === 'academic')<a href="{{ route('portal.academic') }}">Fix</a>@endif
                        </span>
                    @endforeach
                @endforeach
            </div>
        </div>
    @endif

    <div class="actionbar">
        <a href="{{ route('portal.academic') }}" class="btn btn-secondary btn-sm">Back</a>
        <div class="group">
            @if($canSubmit)
                @if($student->dues()->exists() && \App\Support\PortalProgress::paymentUnlocked($student))
                    <a href="{{ route('portal.payment') }}" class="btn btn-secondary">Pay now</a>
                @endif
                <button type="button" class="btn btn-primary" wire:click="submit" wire:loading.attr="disabled" wire:target="submit">
                    <span wire:loading.remove wire:target="submit">{{ $student->status === 'rejected' ? 'Submit corrected application' : 'Submit application' }} <svg class="arrow" width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align: -4px;"><path d="M4 10 H16 M11 5 L16 10 L11 15"></path></svg></span>
                    <span wire:loading wire:target="submit"><span class="spin"></span> Submitting…</span>
                </button>
            @elseif(\App\Support\PortalProgress::paymentUnlocked($student))
                <a href="{{ route('portal.payment') }}" class="btn btn-primary">Continue to payment <svg class="arrow" width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align: -4px;"><path d="M4 10 H16 M11 5 L16 10 L11 15"></path></svg></a>
            @endif
        </div>
    </div>
    </div>
    </div>
</main>
