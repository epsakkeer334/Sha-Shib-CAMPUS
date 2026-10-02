@extends('admin.print.layout')

@section('title', 'ER request form ' . $student->er_number)

@php
    $academic = $student->academicDetail;
    $mark = fn ($m, $t) => \App\Models\Admin\StudentAcademicDetail::formatMark($m, $t);
    $docs = $student->documents->groupBy('document_type');
@endphp

@section('content')
<div class="sheet">
    <div class="head">
        <div>
            <h1>{{ optional($student->institute)->name }}</h1>
            <div class="muted">Institute code {{ optional($student->institute)->code }}</div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 18px; font-weight: 600;">ER request form</div>
            <div class="mono" style="font-size: 20px;">{{ $student->er_number }}</div>
            <div class="muted">Generated {{ optional(optional($student->erRequest)->generated_at)->format('d M Y') }}</div>
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
</div>
@endsection
