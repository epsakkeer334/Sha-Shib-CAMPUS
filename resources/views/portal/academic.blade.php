<main class="main">
    <div style="display: flex; flex-direction: column; gap: 8px;">
        <span class="eyebrow">Admission application {{ now()->year }}</span>
        <h1 class="title">Academic details</h1>
        <p class="lead">Enter your marks exactly as they appear on your marksheets.</p>
    </div>

    @include('portal.partials.steps', ['current' => 'academic', 'student' => $student])

    @if($readOnly)
        <div class="notice info" role="status"><span>Your fee payment is confirmed, so academic details can no longer be changed online.</span></div>
    @elseif($draftRestored)
        <div class="notice info" role="status"><span>We restored the changes you had not saved yet. Review them and choose “Save and continue”.</span></div>
    @endif

    @include('portal.partials.fee-summary', ['student' => $student])

    <form wire:submit.prevent="save" style="display: flex; flex-direction: column; gap: 28px;">
        <fieldset @if($readOnly) disabled @endif style="border: 0; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 28px;">

        <section class="card">
            <h2>Class 10 (Matriculation)</h2>
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr));">
                @include('portal.partials.field', ['name' => 'matriculation_board_id', 'label' => 'Board', 'type' => 'select', 'required' => true, 'options' => $matriculationBoards])
                <div class="field">
                    <span class="label">Marks given as *</span>
                    <div class="seg" role="group" aria-label="Class 10 mark type">
                        @foreach(config('camp.mark_types') as $value => $label)
                            <button type="button" aria-pressed="{{ $matriculation_mark_type === $value ? 'true' : 'false' }}"
                                    wire:click="choose('matriculation_mark_type', '{{ $value }}')">{{ $value === 'cgpa' ? 'CGPA' : 'Percentage' }}</button>
                        @endforeach
                    </div>
                </div>
                @include('portal.partials.field', ['name' => 'matriculation_mark', 'label' => $matriculation_mark_type === 'cgpa' ? 'CGPA' : 'Percentage', 'required' => true,
                    'inputmode' => 'decimal', 'class' => 'mono', 'hint' => $matriculation_mark_type === 'cgpa' ? 'Between 0 and 10' : 'Between 0 and 100'])
            </div>
        </section>

        <section class="card">
            <h2>Class 12 (Higher secondary)</h2>
            @include('portal.partials.eligibility-criteria', ['criteria' => $criteria])
            <div style="max-width: 360px;">
                @include('portal.partials.field', ['name' => 'higher_secondary_board_id', 'label' => 'Board', 'type' => 'select', 'required' => true, 'live' => true, 'options' => $higherSecondaryBoards])
            </div>
            <div class="field">
                <span class="label">Subject stream *</span>
                <div class="choices" role="group" aria-label="Subject stream">
                    @foreach($groups as $group)
                        <button type="button" class="choice" aria-pressed="{{ $higher_secondary_subject === $group->code ? 'true' : 'false' }}"
                                wire:click="choose('higher_secondary_subject', '{{ $group->code }}')">
                            <span class="choice-title">{{ $group->code === 'COMMERCE' || $group->code === 'ARTS' ? ucfirst(strtolower($group->code)) : $group->code }}</span>
                            <span class="choice-sub">{{ $group->subject_list ?: $group->name }}</span>
                        </button>
                    @endforeach
                </div>
                @error('higher_secondary_subject')<span class="error">{{ $message }}</span>@enderror
                @if($criteria->isNotEmpty())<span class="hint">Only the streams this course accepts are listed.</span>@endif
            </div>
            <div class="grid">
                <div class="field">
                    <span class="label">Marks given as *</span>
                    <div class="seg" role="group" aria-label="Class 12 mark type">
                        @foreach(config('camp.mark_types') as $value => $label)
                            <button type="button" aria-pressed="{{ $higher_secondary_mark_type === $value ? 'true' : 'false' }}"
                                    wire:click="choose('higher_secondary_mark_type', '{{ $value }}')">{{ $value === 'cgpa' ? 'CGPA' : 'Percentage' }}</button>
                        @endforeach
                    </div>
                </div>
                @include('portal.partials.field', ['name' => 'higher_secondary_mark', 'label' => ($higher_secondary_mark_type === 'cgpa' ? 'Overall CGPA' : 'Overall percentage'), 'required' => true,
                    'inputmode' => 'decimal', 'class' => 'mono', 'hint' => $higher_secondary_mark_type === 'cgpa' ? 'Between 0 and 10' : 'Between 0 and 100'])
            </div>

            {{-- Marks per subject + live eligibility check --}}
            @if($higher_secondary_subject && $entry['subjects'])
                @php $rows = collect($check['subjects'] ?? [])->keyBy('id'); @endphp
                <div class="field">
                    <span class="label">Marks per subject{{ $entry['required'] ? ' *' : '' }} <span class="muted" style="font-weight: 400;">({{ $higher_secondary_mark_type === 'cgpa' ? 'CGPA, 0–10' : '%, 0–100' }})</span></span>
                    <div class="elig-marks">
                        @foreach($entry['subjects'] as $subject)
                            @php $row = $rows->get($subject['id']); @endphp
                            <label class="elig-mark {{ $row && $row['percent'] !== null ? ($row['ok'] ? 'is-ok' : 'is-bad') : '' }}" for="sm_{{ $subject['id'] }}">
                                <span class="elig-mark-name">{{ $subject['name'] }}
                                    @if($subject['min'] !== null)<span class="muted">min {{ rtrim(rtrim(number_format($subject['min'], 2), '0'), '.') }}%</span>@endif
                                </span>
                                <input id="sm_{{ $subject['id'] }}" class="input mono @error('subject_marks.' . $subject['id']) invalid @enderror" inputmode="decimal"
                                       wire:model.lazy="subject_marks.{{ $subject['id'] }}" placeholder="{{ $higher_secondary_mark_type === 'cgpa' ? '0 – 10' : '0 – 100' }}">
                                @error('subject_marks.' . $subject['id'])<span class="error">{{ $message }}</span>
                                @elseif($row && $row['percent'] !== null && $subject['min'] !== null)
                                    <span class="elig-mark-result">{{ $row['ok'] ? '✓' : '✗' }} {{ rtrim(rtrim(number_format($row['percent'], 2), '0'), '.') }}%{{ $higher_secondary_mark_type === 'cgpa' ? ' (converted)' : '' }}{{ $row['ok'] ? '' : ' — needs ' . rtrim(rtrim(number_format($subject['min'], 2), '0'), '.') . '%' }}</span>
                                @enderror
                            </label>
                        @endforeach
                    </div>
                </div>

                @if($check && $check['status'] !== 'not_configured')
                    @php $complete = collect($check['subjects'])->every(fn ($s) => $s['percent'] !== null); @endphp
                    @if($check['status'] === 'eligible')
                        <div class="notice ok" role="status"><span><strong>You meet the eligibility criteria for this course.</strong>
                            @if($check['aggregate'] !== null) Average {{ rtrim(rtrim(number_format($check['aggregate'], 2), '0'), '.') }}%.@endif</span></div>
                    @elseif($complete || !$check['criterion_id'])
                        <div class="notice danger" role="alert">
                            <span><strong>Not eligible for this course yet.</strong> {{ implode(' ', $check['reasons']) }}
                                You can save your details, but the application cannot be submitted until the marks meet the criteria. Contact the admissions office if your marksheet differs.</span>
                        </div>
                    @endif
                @endif
            @elseif($higher_secondary_subject && $check && $check['status'] === 'not_eligible')
                <div class="notice danger" role="alert"><span><strong>Not eligible for this course.</strong> {{ implode(' ', $check['reasons']) }}</span></div>
            @endif
        </section>

        </fieldset>

        <div class="row-between">
            <a href="{{ route('portal.details') }}" class="btn btn-secondary">Back</a>
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                @if($readOnly)
                    <a href="{{ route('portal.documents') }}" class="btn btn-primary">Next</a>
                @else
                    @if($student->dues()->exists() && \App\Support\PortalProgress::paymentUnlocked($student))
                        <button type="button" class="btn btn-secondary" wire:click="saveAndPay" wire:loading.attr="disabled">Save and pay now</button>
                    @endif
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Save and continue</button>
                @endif
            </div>
        </div>
    </form>
</main>
