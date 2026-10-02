<main class="main">
    <div style="display: flex; flex-direction: column; gap: 8px;">
        <span class="eyebrow">Admission application {{ now()->year }}</span>
        <h1 class="title">Your details</h1>
        <p class="lead">Fields marked * are required. Your progress is saved as a draft when you continue.</p>
    </div>

    @include('portal.partials.steps', ['current' => 'details', 'student' => $student])

    @if($readOnly)
        <div class="notice info" role="status">
            <span>Your application has been submitted and is being reviewed, so these details can no longer be changed here. Contact the admissions office for corrections.</span>
            <a href="{{ route('portal.status') }}" class="btn btn-secondary btn-sm">Application status</a>
        </div>
    @endif

    <form wire:submit.prevent="save" style="display: flex; flex-direction: column; gap: 28px;">
        <fieldset @if($readOnly) disabled @endif style="border: 0; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 28px;">

        <section class="card">
            <div style="display: flex; flex-direction: column; gap: 4px;">
                <h2>{{ $registering ? 'Create your login' : 'Your login' }}</h2>
                <p class="muted" style="margin: 0; font-size: 14px;">Use this to come back, upload documents and track your application.</p>
            </div>
            <div class="grid">
                @include('portal.partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'autocomplete' => 'email'])
                @include('portal.partials.field', ['name' => 'phone', 'label' => 'Mobile number', 'type' => 'tel', 'required' => true, 'placeholder' => '+91 98470 12345', 'autocomplete' => 'tel'])
                @if($registering)
                    @include('portal.partials.field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'hint' => 'At least 8 characters', 'autocomplete' => 'new-password'])
                    @include('portal.partials.field', ['name' => 'password_confirmation', 'label' => 'Confirm password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password'])
                @endif
            </div>
        </section>

        <section class="card">
            <h2>Course</h2>
            <div class="grid">
                @if($registering)
                    @include('portal.partials.field', ['name' => 'institute_id', 'label' => 'Institute', 'type' => 'select', 'required' => true, 'live' => true, 'options' => $institutes, 'placeholder' => 'Select institute'])
                @else
                    <label class="field"><span class="label">Institute</span><input class="input" disabled value="{{ optional($student->institute)->name }}"></label>
                @endif
                @include('portal.partials.field', ['name' => 'course_id', 'label' => 'Course you are applying for', 'type' => 'select', 'required' => true, 'options' => $courses,
                    'placeholder' => $institute_id ? 'Select course' : 'Choose the institute first', 'disabled' => !$institute_id])
                @include('portal.partials.field', ['name' => 'joining_date', 'label' => 'Joining date', 'type' => 'date', 'required' => true,
                    'hint' => 'Complete all steps within ' . config('camp.onboarding_days') . ' days of this date'])
            </div>
        </section>

        <section class="card">
            <h2>About you</h2>
            <div class="grid">
                @include('portal.partials.field', ['name' => 'first_name', 'label' => 'First name', 'required' => true, 'autocomplete' => 'given-name'])
                @include('portal.partials.field', ['name' => 'last_name', 'label' => 'Last name', 'required' => true, 'autocomplete' => 'family-name'])
                @include('portal.partials.field', ['name' => 'dob', 'label' => 'Date of birth', 'type' => 'date', 'required' => true])
                @include('portal.partials.field', ['name' => 'gender', 'label' => 'Gender', 'type' => 'select', 'required' => true, 'options' => config('camp.genders')])
                @include('portal.partials.field', ['name' => 'qualification_id', 'label' => 'Highest qualification', 'type' => 'select', 'required' => true, 'options' => $qualifications])
                @include('portal.partials.field', ['name' => 'emergency_contact', 'label' => 'Emergency contact', 'type' => 'tel', 'required' => true, 'placeholder' => 'Phone number'])
                @include('portal.partials.field', ['name' => 'religion_id', 'label' => 'Religion', 'type' => 'select', 'required' => true, 'live' => true, 'options' => $religions])
                @include('portal.partials.field', ['name' => 'category_id', 'label' => 'Category', 'type' => 'select', 'required' => true, 'options' => $categories,
                    'placeholder' => $religion_id ? 'Select' : 'Choose your religion first', 'disabled' => !$religion_id])
            </div>
        </section>

        <section class="card">
            <h2>Address</h2>
            @include('portal.partials.field', ['name' => 'address', 'label' => 'House / street', 'type' => 'textarea', 'required' => true])
            <div class="grid narrow">
                @include('portal.partials.field', ['name' => 'country_id', 'label' => 'Country', 'type' => 'select', 'required' => true, 'live' => true, 'options' => $countries])
                @include('portal.partials.field', ['name' => 'state_id', 'label' => 'State', 'type' => 'select', 'required' => true, 'options' => $states,
                    'placeholder' => $country_id ? 'Select' : 'Choose the country first', 'disabled' => !$country_id])
                @include('portal.partials.field', ['name' => 'city', 'label' => 'City', 'required' => true])
                @include('portal.partials.field', ['name' => 'pincode', 'label' => 'Pincode', 'required' => true, 'inputmode' => 'numeric'])
            </div>
        </section>

        <section class="card">
            <h2>Parent or guardian</h2>
            <div class="grid">
                @include('portal.partials.field', ['name' => 'parent_name', 'label' => 'Name', 'required' => true])
                @include('portal.partials.field', ['name' => 'parent_phone', 'label' => 'Phone', 'type' => 'tel', 'required' => true])
                @include('portal.partials.field', ['name' => 'parent_email', 'label' => 'Email', 'type' => 'email', 'placeholder' => 'Optional'])
                @include('portal.partials.field', ['name' => 'parent_occupation', 'label' => 'Occupation'])
            </div>
        </section>

        </fieldset>

        <div class="row-between">
            <a href="{{ $registering ? route('portal.home') : route('portal.status') }}" style="font-weight: 500; color: var(--ink-2);">Cancel</a>
            @if($readOnly)
                <a href="{{ route('portal.academic') }}" class="btn btn-primary">Next</a>
            @else
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Save and continue</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            @endif
        </div>
    </form>
</main>
