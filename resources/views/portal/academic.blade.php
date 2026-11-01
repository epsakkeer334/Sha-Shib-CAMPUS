<main class="main wide">
    <header class="page-head rise">
        <span class="eyebrow">Step 2 of 4 · Admission {{ now()->year }}</span>
        <h1 class="title">Your academic details</h1>
        <p class="lead">Enter marks exactly as printed on your marksheets.</p>
    </header>

    <div class="flow">
    @include('portal.partials.steps', ['current' => 'academic', 'student' => $student])
    <div class="flow-main">

    @if($readOnly)
        <div class="notice info" role="status"><span>Your fee payment is confirmed, so academic details can no longer be changed online.</span></div>
    @elseif($draftRestored)
        <div class="notice info" role="status"><span>We restored the changes you had not saved yet. Review them and choose “Save and continue”.</span></div>
    @endif

    <form wire:submit.prevent="save" style="display: flex; flex-direction: column; gap: 22px;">
        <fieldset @if($readOnly) disabled @endif style="border: 0; padding: 0; margin: 0; min-width: 0; display: flex; flex-direction: column; gap: 22px;">

        <section class="card">
            <div class="card-head"><span class="ico" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 4.5 C5.5 3.7 8 3.9 10 5.2 C12 3.9 14.5 3.7 16.5 4.5 V15.5 C14.5 14.7 12 14.9 10 16.2 C8 14.9 5.5 14.7 3.5 15.5 Z"></path><path d="M10 5.2 V16.2"></path></svg></span><span style="display: flex; flex-direction: column; min-width: 0;"><h2>Class 10 · Matriculation</h2><span class="sub">Board and overall result</span></span></div>
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr));">
                @include('portal.partials.field', ['name' => 'matriculation_board_id', 'label' => 'Board', 'type' => 'select', 'required' => true, 'options' => $matriculationBoards])
                <div class="field">
                    <span class="label">Marks given as <span class="req" aria-hidden="true">*</span></span>
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
            <div class="card-head"><span class="ico" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 8 L10 4.5 L17.5 8 L10 11.5 Z"></path><path d="M5.5 9.5 V13 C6.9 14.4 8.4 15 10 15 S13.1 14.4 14.5 13 V9.5"></path></svg></span><span style="display: flex; flex-direction: column; min-width: 0;"><h2>Class 12 · Higher secondary</h2><span class="sub">Board, stream and marks</span></span></div>
            @include('portal.partials.eligibility-criteria', ['criteria' => $criteria])
            <div class="grid">
                @include('portal.partials.field', ['name' => 'higher_secondary_board_id', 'label' => 'Board', 'type' => 'select', 'required' => true, 'live' => true, 'options' => $higherSecondaryBoards])
            </div>
            <div class="field">
                <span class="label">Subject stream <span class="req" aria-hidden="true">*</span></span>
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
                @if($criteria->isNotEmpty())<span class="hint">Only streams this course accepts are shown.</span>@endif
            </div>
            <div class="grid">
                <div class="field">
                    <span class="label">Marks given as <span class="req" aria-hidden="true">*</span></span>
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
                    <span class="label">Marks per subject @if($entry['required'])<span class="req" aria-hidden="true">*</span>@endif <span class="muted" style="font-weight: 600;">· {{ $higher_secondary_mark_type === 'cgpa' ? 'CGPA, 0–10' : '%, 0–100' }}</span></span>
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
                                    <span class="elig-mark-result">{{ $row['ok'] ? '✓' : '✗' }} {{ rtrim(rtrim(number_format($row['percent'], 2), '0'), '.') }}%{{ $higher_secondary_mark_type === 'cgpa' ? ' (converted)' : '' }}{{ $row['ok'] ? '' : ' · needs ' . rtrim(rtrim(number_format($subject['min'], 2), '0'), '.') . '%' }}</span>
                                @enderror
                            </label>
                        @endforeach
                    </div>
                </div>

                @if($check && $check['status'] !== 'not_configured')
                    @php $complete = collect($check['subjects'])->every(fn ($s) => $s['percent'] !== null); @endphp
                    @if($check['status'] === 'eligible')
                        <div class="notice ok" role="status"><span><strong>✓ You meet the eligibility criteria for this course.</strong>
                            @if($check['aggregate'] !== null) Average {{ rtrim(rtrim(number_format($check['aggregate'], 2), '0'), '.') }}%.@endif</span></div>
                    @elseif($complete || !$check['criterion_id'])
                        <div class="notice danger" role="alert">
                            <span><strong>Not eligible for this course yet.</strong> {{ implode(' ', $check['reasons']) }}
                                You can save, but you can't submit until the marks meet the criteria. Marksheet differs? Contact the admissions office.</span>
                        </div>
                    @endif
                @endif
            @elseif($higher_secondary_subject && $check && $check['status'] === 'not_eligible')
                <div class="notice danger" role="alert"><span><strong>Not eligible for this course.</strong> {{ implode(' ', $check['reasons']) }}</span></div>
            @endif
        </section>

        </fieldset>

        <div class="actionbar">
            <a href="{{ route('portal.details') }}" class="btn btn-secondary btn-sm">Back</a>
            <div class="group">
                @if($readOnly)
                    <a href="{{ route('portal.documents') }}" class="btn btn-primary">Next</a>
                @else
                    @if($student->dues()->exists() && \App\Support\PortalProgress::paymentUnlocked($student))
                        <button type="button" class="btn btn-secondary" wire:click="saveAndPay" wire:loading.attr="disabled">Save and pay now</button>
                    @endif
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">Save and continue <svg class="arrow" width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align: -4px;"><path d="M4 10 H16 M11 5 L16 10 L11 15"></path></svg></span>
                        <span wire:loading wire:target="save"><span class="spin"></span> Saving…</span>
                    </button>
                @endif
            </div>
        </div>
    </form>
    </div>
    </div>
</main>
