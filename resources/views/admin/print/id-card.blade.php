@extends('admin.print.layout')

@section('title', 'ID card ' . $student->er_number)
@section('print_record_url', route('admin.students.printed', [$student->id, 'id-card']))
@section('print_record_key', 'id-card:' . $student->id)

@push('styles')
<style>
    .card-print { width: 85.6mm; height: 54mm; margin: 24px auto; border: 1px solid #C9CEC6; border-radius: 3mm; overflow: hidden; display: flex; flex-direction: column; background: #fff; }
    .card-print .band { background: #1D1C4C; color: #fff; padding: 1.6mm 3.5mm; display: flex; align-items: center; justify-content: space-between; gap: 2mm; font-size: 2.6mm; letter-spacing: .04em; border-bottom: .8mm solid #F26522; }
    .card-print .band .inst { display: flex; align-items: center; gap: 1.6mm; min-width: 0; }
    .card-print .band .inst img { width: 6mm; height: 6mm; object-fit: contain; flex-shrink: 0; }
    .card-print .band .inst strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .card-print .band .role { color: #FBB866; font-weight: 700; }
    .card-print .body { flex-grow: 1; display: grid; grid-template-columns: 19mm 1fr; gap: 3mm; padding: 3mm 3.5mm 0; }
    .card-print .photo { width: 19mm; height: 23mm; object-fit: cover; border-radius: 1.5mm; background: #ECEEEA; }
    .card-print .sign { margin: 0; padding: 0 3.5mm 2mm; display: flex; justify-content: space-between; align-items: flex-end; }
    .card-print .sign img { height: 3.6mm; width: auto; border: 0; padding: 0; }
    .card-print .sign span { flex: 0 0 29mm; font-size: 2.2mm; padding-top: .5mm; }
    @media print { .card-print { margin: 0; } }
</style>
@endpush

@section('content')
<div class="card-print">
    <div class="band"><span class="inst"><img src="{{ asset('brand/seal-white.png') }}" alt=""><strong>{{ strtoupper(optional($student->institute)->name) }}</strong></span><span class="role">STUDENT</span></div>
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
    <div class="sign"><img src="{{ asset('brand/logo-navy.png') }}" alt="Sha-Shib Campus"><span>Training Manager</span></div>
</div>
<p class="muted" style="text-align: center; font-size: 12px;">Print at 100% scale on card stock. Valid only after the Training Manager signs it by hand.</p>
@endsection
