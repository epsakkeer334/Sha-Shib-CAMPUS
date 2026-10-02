<main class="main">
    <div style="display: flex; flex-direction: column; gap: 8px;">
        <span class="eyebrow">Admission application {{ now()->year }}</span>
        <h1 class="title">Academic details</h1>
        <p class="lead">Enter your marks exactly as they appear on your marksheets.</p>
    </div>

    @include('portal.partials.steps', ['current' => 'academic', 'student' => $student])

    @if($readOnly)
        <div class="notice info" role="status"><span>Your application is being reviewed, so academic details can no longer be changed here.</span></div>
    @endif

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
            <div style="max-width: 360px;">
                @include('portal.partials.field', ['name' => 'higher_secondary_board_id', 'label' => 'Board', 'type' => 'select', 'required' => true, 'options' => $higherSecondaryBoards])
            </div>
            <div class="field">
                <span class="label">Subject stream *</span>
                <div class="choices" role="group" aria-label="Subject stream">
                    @foreach($streams as $value => [$title, $sub])
                        <button type="button" class="choice" aria-pressed="{{ $higher_secondary_subject === $value ? 'true' : 'false' }}"
                                wire:click="choose('higher_secondary_subject', '{{ $value }}')">
                            <span class="choice-title">{{ $title }}</span><span class="choice-sub">{{ $sub }}</span>
                        </button>
                    @endforeach
                </div>
                @error('higher_secondary_subject')<span class="error">{{ $message }}</span>@enderror
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
                @include('portal.partials.field', ['name' => 'higher_secondary_mark', 'label' => $higher_secondary_mark_type === 'cgpa' ? 'CGPA' : 'Percentage', 'required' => true,
                    'inputmode' => 'decimal', 'class' => 'mono', 'hint' => $higher_secondary_mark_type === 'cgpa' ? 'Between 0 and 10' : 'Between 0 and 100'])
            </div>
        </section>

        </fieldset>

        <div class="row-between">
            <a href="{{ route('portal.details') }}" class="btn btn-secondary">Back</a>
            @if($readOnly)
                <a href="{{ route('portal.documents') }}" class="btn btn-primary">Next</a>
            @else
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Save and continue</button>
            @endif
        </div>
    </form>
</main>
