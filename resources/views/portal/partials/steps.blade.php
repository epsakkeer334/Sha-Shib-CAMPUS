{{-- Application steps bar. Params: $current (details|academic|documents|payment), $student (nullable) --}}
@php
    $stepDefs = [
        'details' => ['Your details', $student ? route('portal.details') : null],
        'academic' => ['Academic', $student ? route('portal.academic') : null],
        'documents' => ['Documents', $student ? route('portal.documents') : null],
        'payment' => ['Payment', $student ? route('portal.payment') : null],
    ];
    $doneMap = [
        'details' => (bool) $student,
        'academic' => $student && $student->academicDetail()->exists(),
        'documents' => $student && $student->submitted_at && !$student->missingRequiredDocuments(),
        'payment' => $student && $student->dues()->exists() && $student->outstandingAmount() <= 0,
    ];
@endphp
<ol class="steps" aria-label="Application steps">
    @foreach($stepDefs as $key => [$name, $url])
        @php $state = $key === $current ? 'current' : ($doneMap[$key] ? 'done' : 'todo'); @endphp
        <li class="{{ $state }}" @if($state === 'current') aria-current="step" @endif>
            <span class="bar"></span>
            <span class="tag">{{ $state === 'done' ? 'Done' : 'Step ' . $loop->iteration }}</span>
            @if($url && $state !== 'current')
                <a href="{{ $url }}" class="name">{{ $name }}</a>
            @else
                <span class="name">{{ $name }}</span>
            @endif
        </li>
    @endforeach
</ol>
