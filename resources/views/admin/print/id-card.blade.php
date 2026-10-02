@extends('admin.print.layout')

@section('title', 'ID card ' . $student->er_number)

@push('styles')
<style>
    .card-print { width: 85.6mm; height: 54mm; margin: 24px auto; border: 1px solid #C9CEC6; border-radius: 3mm; overflow: hidden; display: flex; flex-direction: column; background: #fff; }
    .card-print .band { background: #0E6B55; color: #fff; padding: 2mm 3.5mm; display: flex; justify-content: space-between; font-size: 2.6mm; letter-spacing: .04em; }
    .card-print .body { flex-grow: 1; display: grid; grid-template-columns: 19mm 1fr; gap: 3mm; padding: 3mm 3.5mm 0; }
    .card-print .photo { width: 19mm; height: 23mm; object-fit: cover; border-radius: 1.5mm; background: #ECEEEA; }
    .card-print .sign { margin: 0; padding: 0 3.5mm 2mm; display: flex; justify-content: flex-end; }
    .card-print .sign span { flex: 0 0 29mm; font-size: 2.2mm; padding-top: .5mm; }
    @media print { .card-print { margin: 0; } }
</style>
@endpush

@section('content')
<div class="card-print">
    <div class="band"><strong>{{ strtoupper(optional($student->institute)->name) }}</strong><span>STUDENT</span></div>
    <div class="body">
        @if($photo)
            <img class="photo" src="{{ route('admin.students.documents.show', $photo->id) }}" alt="Photo">
        @else
            <div class="photo"></div>
        @endif
        <div style="display: flex; flex-direction: column; gap: .8mm; min-width: 0;">
            <strong style="font-size: 3.8mm; line-height: 4.4mm;">{{ $student->full_name }}</strong>
            <span style="font-size: 2.7mm; line-height: 3.3mm;">{{ optional($student->course)->name }}</span>
            <span class="mono" style="font-size: 3.2mm; margin-top: 1mm;">{{ $student->er_number }}</span>
            <span class="muted" style="font-size: 2.4mm;">Issued {{ $card->issue_date ? $card->issue_date->format('d M Y') : now()->format('d M Y') }}</span>
        </div>
    </div>
    <div class="sign"><span>Training Manager</span></div>
</div>
<p class="muted" style="text-align: center; font-size: 12px;">Print at 100% scale on card stock. Valid only after the Training Manager signs it by hand.</p>
@endsection
