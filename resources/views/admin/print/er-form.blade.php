@extends('admin.print.layout')

@section('title', 'ER request form ' . $student->er_number)
@section('print_record_url', route('admin.students.printed', [$student->id, 'er-form']))
@section('print_record_key', 'er-form:' . $student->id)

@php
    $academic = $student->academicDetail;
    $mark = fn ($m, $t) => \App\Models\Admin\StudentAcademicDetail::formatMark($m, $t);
    $docs = $student->documents->groupBy('document_type');
    $photo = $docs->has('kyc_photo') ? $docs->get('kyc_photo')->sortByDesc('uploaded_at')->first() : null;
@endphp

@push('styles')
<style>
    .er-head { align-items: stretch; gap: 28px; padding-bottom: 20px; }
    .er-left { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: space-between; gap: 18px; }
    .er-head .inst { gap: 16px; }
    .er-head .inst-logo { width: auto; height: 60px; max-width: 120px; }
    .er-head h1 { font-size: 24px; line-height: 30px; }
    .er-title { border-top: 1px solid var(--line); padding-top: 14px; }
    .er-kicker { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; font-size: 13px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--brand); }
    .er-kicker::before { content: ""; width: 4px; height: 14px; border-radius: 2px; background: var(--accent); }
    .er-meta { display: flex; flex-wrap: wrap; gap: 10px; }
    .er-field { padding: 6px 14px 7px; border: 1px solid var(--line); border-radius: 6px; background: #F7F8FC; }
    .er-field span { display: block; font-size: 10px; line-height: 14px; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
    .er-field strong { display: block; font-size: 15px; line-height: 22px; font-weight: 600; color: var(--ink); }
    .er-field strong.mono { font-size: 17px; font-weight: 500; letter-spacing: .02em; }
    .er-photo-frame { flex-shrink: 0; align-self: flex-start; padding: 4px; border: 1px solid #C9CBDC; border-radius: 6px; background: #fff; }
    .er-photo { display: block; width: 30mm; height: 38mm; object-fit: cover; border-radius: 3px; background: #F3F4F9; }
    .er-photo-empty { display: flex; align-items: center; justify-content: center; text-align: center; border: 1px dashed #B5B8CE; color: var(--muted); font-size: 11px; line-height: 15px; padding: 8px; }
    /* Keep the form on one A4 sheet */
    @page { size: A4; margin: 12mm; }
    @media print {
        .sheet th, .sheet td { padding-top: 4px; padding-bottom: 4px; }
        .sheet h2 { margin: 14px 0 4px; }
        .sheet .head { margin-bottom: 14px; padding-bottom: 16px; }
        .sheet .sign { margin-top: 40px; }
        .sheet .brand-foot { margin-top: 18px; }
    }
</style>
@endpush

@section('content')
<div class="sheet">
    <div class="head er-head">
        <div class="er-left">
            <div class="inst">
                <img class="inst-logo" src="{{ optional($student->institute)->logo ? $student->institute->logo_url : asset('brand/seal-navy.png') }}" alt="">
                <div>
                    <h1>{{ optional($student->institute)->name }}</h1>
                    <div class="muted">Institute code {{ optional($student->institute)->code }}</div>
                </div>
            </div>
            <div class="er-title">
                <div class="er-kicker">ER request form</div>
                <div class="er-meta">
                    <div class="er-field"><span>ER number</span><strong class="mono">{{ $student->er_number }}</strong></div>
                    <div class="er-field"><span>Generated on</span><strong>{{ optional(optional($student->erRequest)->generated_at)->format('d M Y') ?: '—' }}</strong></div>
                </div>
            </div>
        </div>
        <div class="er-photo-frame">
            @if($photo)
                <img class="er-photo" src="{{ route('admin.students.documents.show', $photo->id) }}" alt="Student photo">
            @else
                <div class="er-photo er-photo-empty">Affix passport size photo</div>
            @endif
        </div>
    </div>

    <h2>Student</h2>
    <table>
        <tr><th>Name</th><td><strong>{{ $student->full_name }}</strong></td></tr>
        <tr><th>Course</th><td>{{ optional($student->course)->name }} ({{ optional($student->course)->code }})</td></tr>
        <tr><th>Date of birth / Gender</th><td>{{ optional($student->dob)->format('d M Y') }} / {{ config('camp.genders.' . $student->gender) }}</td></tr>
        <tr><th>Qualification</th><td>{{ optional($student->qualification)->name }}</td></tr>
        <tr><th>Religion / Category</th><td>{{ optional($student->religion)->name }} / {{ optional($student->category)->name }}</td></tr>
        <tr><th>Phone / Email</th><td>{{ $student->phone }} / {{ $student->email }}</td></tr>
        <tr><th>Emergency contact</th><td>{{ $student->emergency_contact }}</td></tr>
        <tr><th>Address</th><td>{{ $student->address }}, {{ $student->city }}, {{ optional($student->state)->name }}, {{ optional($student->country)->name }} — {{ $student->pincode }}</td></tr>
        <tr><th>Parent / Guardian</th><td>{{ $student->parent_name }} @if($student->parent_occupation)({{ $student->parent_occupation }})@endif · {{ $student->parent_phone }}</td></tr>
        <tr><th>Joining date</th><td>{{ $student->formatted_joining_date }}</td></tr>
    </table>

    <h2>Academic details</h2>
    <table>
        <tr><th>Class 10</th><td>{{ optional(optional($academic)->matriculationBoard)->name }} — {{ $academic ? $mark($academic->matriculation_mark, $academic->matriculation_mark_type) : '—' }}</td></tr>
        <tr><th>Class 12</th><td>{{ optional(optional($academic)->higherSecondaryBoard)->name }} ({{ optional($academic)->higher_secondary_subject }}) — {{ $academic ? $mark($academic->higher_secondary_mark, $academic->higher_secondary_mark_type) : '—' }}</td></tr>
    </table>

    <h2>Verification</h2>
    <table>
        @foreach(config('camp.student_document_types') as $type => [$label, $required])
            @if($required || $docs->has($type))
                <tr><th>{{ $label }}</th><td>{{ $docs->has($type) ? ucfirst($docs->get($type)->sortByDesc('uploaded_at')->first()->verification_status) : 'Not uploaded' }}</td></tr>
            @endif
        @endforeach
        @foreach($student->approvals as $gate)
            <tr><th>{{ $gate->label }}</th><td>{{ ucfirst($gate->status) }} · {{ optional($gate->approver)->name }} · {{ optional($gate->approved_at)->format('d M Y') }}</td></tr>
        @endforeach
    </table>

    <div class="sign">
        <span>Student</span>
        <span>Training Manager</span>
        <span>Institute seal</span>
    </div>
    <div class="brand-foot">
        <img src="{{ asset('brand/logo-navy.png') }}" alt="Sha-Shib Campus">
        <span>Centralised Academic Management &amp; Performance Unified System</span>
    </div>
</div>
@endsection
