{{-- ID card face (screen preview and print). Params: $student, $card, $photo (KYC photo document or null) --}}
<div class="id-card-preview d-flex flex-column align-self-center">
    <div class="band px-3 py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold text-uppercase">{{ optional($student->institute)->name }}</span><span>STUDENT</span>
    </div>
    <div class="flex-grow-1 d-grid gap-3 px-3 py-2" style="grid-template-columns: 72px minmax(0, 1fr);">
        @if($photo)
            <img src="{{ route('admin.students.documents.show', $photo->id) }}" alt="Photo of {{ $student->full_name }}" style="width: 72px; height: 88px; object-fit: cover; border-radius: 6px;">
        @else
            <div class="d-flex align-items-center justify-content-center text-center text-muted" style="width: 72px; height: 88px; border-radius: 6px; background: #ECEEEA; font-size: 10px;">No photo</div>
        @endif
        <div class="d-flex flex-column" style="gap: 3px; min-width: 0;">
            <span class="fw-semibold" style="font-size: 15px; line-height: 18px;">{{ $student->full_name }}</span>
            <span style="font-size: 11px; line-height: 14px; color: #2E3833;">{{ optional($student->course)->name }}</span>
            <span class="amount fw-medium mt-1" style="font-size: 13px;">{{ $student->er_number }}</span>
            <span style="font-size: 10px; color: #56605A;">Issued {{ $card && $card->issue_date ? $card->issue_date->format('d M Y') : '—' }}</span>
        </div>
    </div>
    <div class="d-flex justify-content-end px-3 pb-2">
        <span style="width: 110px; border-top: 1px solid #8D958F; padding-top: 2px; font-size: 9px; color: #56605A; text-align: center;">Training Manager</span>
    </div>
</div>
