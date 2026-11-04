<main class="main wide">
    <header class="page-head rise">
        <span class="eyebrow">Step 1 of 4 · Admission {{ now()->year }}</span>
        <h1 class="title">{{ $registering ? 'Start your application' : 'Your details' }}</h1>
        <p class="lead">Saved as you go — sign out and continue any time.</p>
    </header>

    <div class="flow">
    @include('portal.partials.steps', ['current' => 'details', 'student' => $student])
    <div class="flow-main">

    @if($readOnly)
        <div class="notice info" role="status">
            <span>Your fee payment is confirmed, so these details can no longer be changed online. Contact the admissions office for corrections.</span>
            <a href="{{ route('portal.status') }}" class="btn btn-secondary btn-sm">Application status</a>
        </div>
    @elseif($draftRestored)
        <div class="notice info" role="status"><span>We restored the changes you had not saved yet. Review them and choose “Save and continue”.</span></div>
    @endif

    <form wire:submit.prevent="save" style="display: flex; flex-direction: column; gap: 22px;">
        <fieldset @if($readOnly) disabled @endif style="border: 0; padding: 0; margin: 0; min-width: 0; display: flex; flex-direction: column; gap: 22px;">

        <section class="card">
            <div class="card-head"><span class="ico" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4.5" y="9" width="11" height="8" rx="2"></rect><path d="M7 9 V6.5 A3 3 0 0 1 13 6.5 V9"></path></svg></span><span style="display: flex; flex-direction: column; min-width: 0;"><h2>{{ $registering ? 'Create your login' : 'Your login' }}</h2><span class="sub">To come back, upload, pay and track</span></span></div>
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
            <div class="card-head"><span class="ico" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 8 L10 4.5 L17.5 8 L10 11.5 Z"></path><path d="M5.5 9.5 V13 C6.9 14.4 8.4 15 10 15 S13.1 14.4 14.5 13 V9.5"></path></svg></span><span style="display: flex; flex-direction: column; min-width: 0;"><h2>Course</h2><span class="sub">Institute, course, batch and joining date</span></span></div>
            <div class="grid">
                @if($registering)
                    @include('portal.partials.field', ['name' => 'institute_id', 'label' => 'Institute', 'type' => 'select', 'required' => true, 'live' => true, 'options' => $institutes, 'placeholder' => 'Select institute'])
                @else
                    <label class="field"><span class="label">Institute</span><input class="input" disabled value="{{ optional($student->institute)->name }}"></label>
                @endif
                @include('portal.partials.field', ['name' => 'course_id', 'label' => 'Course you are applying for', 'type' => 'select', 'required' => true, 'live' => true, 'options' => $courses,
                    'placeholder' => $institute_id ? 'Select course' : 'Choose the institute first', 'disabled' => !$institute_id])
                @include('portal.partials.field', ['name' => 'batch_id', 'label' => 'Batch', 'type' => 'select', 'required' => $batches->isNotEmpty(), 'options' => $batches,
                    'placeholder' => !$course_id ? 'Choose the course first' : ($batches->isNotEmpty() ? 'Select your batch' : 'No batch open for this course yet'),
                    'disabled' => !$course_id || $batches->isEmpty(),
                    'hint' => $course_id && $batches->isEmpty() ? 'You can continue — the admissions office will assign your batch.' : 'The batch you are joining for this course'])
                @include('portal.partials.field', ['name' => 'joining_date', 'label' => 'Joining date', 'type' => 'date', 'required' => true,
                    'hint' => 'Complete all steps within ' . config('camp.onboarding_days') . ' days of this date'])
            </div>

            {{-- Course eligibility (12th) of the chosen course --}}
            @include('portal.partials.eligibility-criteria', ['criteria' => $eligibility])

            {{-- Fees of the chosen course --}}
            @if($registering && $courseFees->isNotEmpty())
                @php
                    $admissionFees = $courseFees->where('admission_fee', true);
                    $laterFees = $admissionFees->isNotEmpty() ? $courseFees->where('admission_fee', false) : collect();
                    $payNow = $admissionFees->isNotEmpty() ? $admissionFees : $courseFees;
                @endphp
                <div style="border-radius: 16px; background: rgba(242, 244, 255, .8); border: 1px solid #D6DCF7; padding: 14px 16px; display: flex; flex-direction: column; gap: 8px;">
                    <div class="row-between">
                        <span style="font-weight: 600;">{{ $admissionFees->isNotEmpty() ? 'To pay during registration' : 'Fees for this course' }}</span>
                        <span class="mono" style="font-weight: 600;">{{ money_inr($payNow->sum('amount'), false) }}</span>
                    </div>
                    @foreach($payNow as $fee)
                        <div class="row-between" style="font-size: 14px;">
                            <span>{{ $fee->fee_head }} <span class="muted">· {{ $fee->due_label }}</span></span>
                            <span class="mono">{{ money_inr($fee->amount, false) }}</span>
                        </div>
                    @endforeach
                    <span class="hint">You can pay {{ $payNow->count() === 1 ? 'this' : 'these' }} from the Payment step once your documents are uploaded.</span>
                    @if($laterFees->isNotEmpty())
                        <div style="border-top: 1px dashed var(--line); padding-top: 10px; display: flex; flex-direction: column; gap: 6px;">
                            <div class="row-between" style="font-size: 14px;">
                                <span class="muted">Other course fees — added after your admission is confirmed (ER number)</span>
                                <span class="mono muted">{{ money_inr($laterFees->sum('amount'), false) }}</span>
                            </div>
                            @foreach($laterFees as $fee)
                                <div class="row-between muted" style="font-size: 13px;">
                                    <span>{{ $fee->fee_head }}</span><span class="mono">{{ money_inr($fee->amount, false) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </section>

        {{-- Add-on programs of the chosen course (optional, step 2.10) --}}
        @if($addons->isNotEmpty())
            @php
                $chosenIds = array_map('intval', (array) $addon_ids);
                $chosen = $addons->whereIn('id', $chosenIds);
                $atMax = $addonMax !== null && count($chosenIds) >= $addonMax;
                $addonFees = $chosen->sum(fn ($a) => (float) $a->fee_amount);
            @endphp
            <section class="card addon-card" id="addons">
                <div class="card-head">
                    <span class="ico" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3.5 H12 V6 A1.5 1.5 0 0 0 15 6 V3.5 H16.5 V8 H14 A1.5 1.5 0 0 0 14 11 H16.5 V16.5 H12 V14 A1.5 1.5 0 0 0 9 14 V16.5 H3.5 V11 H6 A1.5 1.5 0 0 0 6 8 H3.5 V3.5 H8 Z"></path></svg></span>
                    <span style="display: flex; flex-direction: column; min-width: 0;"><h2>Add-on programs <span class="muted" style="font-weight: 600; font-size: 14px;">· optional</span></h2><span class="sub">Extra programs you can take with this course{{ $addonMax ? ' — choose up to ' . $addonMax : '' }}</span></span>
                    <span class="end"><span class="badge {{ count($chosenIds) ? 'b-info' : 'b-muted' }}">{{ count($chosenIds) }}{{ $addonMax ? ' of ' . $addonMax : '' }} selected</span></span>
                </div>
                <div class="field" style="gap: 12px;">
                    <div class="addon-grid" role="group" aria-label="Add-on programs">
                        @foreach($addons as $addon)
                            @php
                                $on = in_array($addon->id, $chosenIds, true);
                                $blocked = !$on && ($addon->is_full || !$addon->status || $atMax);
                            @endphp
                            <button type="button" class="choice addon {{ $on ? 'is-on' : '' }}" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                    wire:click="toggleAddon({{ $addon->id }})" wire:loading.class="is-busy" wire:target="toggleAddon({{ $addon->id }})"
                                    @if($blocked) disabled title="{{ $addon->is_full ? 'No seats left' : ($atMax ? 'Remove one to choose another' : 'No longer offered') }}" @endif
                                    wire:key="addon-{{ $addon->id }}">
                                <span class="choice-title">{{ $addon->name }}</span>
                                @if($addon->description)<span class="choice-sub addon-desc">{{ $addon->description }}</span>@endif
                                <span class="addon-foot">
                                    <span class="addon-fee {{ $addon->isFree() ? 'free' : '' }}">{{ $addon->feeLabel() }}</span>
                                    @if($addon->is_full && !$on)
                                        <span class="addon-seats full">Full</span>
                                    @elseif($addon->seats_left !== null && $addon->seats_left <= 10)
                                        <span class="addon-seats">{{ $addon->seats_left }} {{ \Illuminate\Support\Str::plural('seat', $addon->seats_left) }} left</span>
                                    @endif
                                </span>
                            </button>
                        @endforeach
                    </div>
                    @error('addon_ids')<span class="error" role="alert">{{ $message }}</span>@enderror
                    @if($chosen->isNotEmpty())
                        <div class="addon-sum">
                            <span><b>{{ $chosen->count() }}</b> {{ \Illuminate\Support\Str::plural('add-on', $chosen->count()) }}: {{ $chosen->pluck('name')->implode(', ') }}</span>
                            <span class="mono" style="font-weight: 700;">{{ $addonFees > 0 ? '+ ' . money_inr($addonFees, false) : 'Free' }}</span>
                        </div>
                        @if($addonFees > 0)<span class="hint">Add-on fees are added to your fees when you save, and can be paid from the Payment step.</span>@endif
                    @else
                        <span class="hint">Skip this if you don't need any — you can still add them later, until your first payment is confirmed.</span>
                    @endif
                </div>
            </section>
            <style>
                .addon-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(230px, 100%), 1fr)); gap: 12px; }
                .choice.addon { gap: 6px; padding: 14px 16px; min-height: 108px; justify-content: space-between; }
                .choice.addon[aria-pressed="true"] { padding: 13px 15px; }
                .choice.addon[disabled] { opacity: .55; }
                .choice.addon.is-busy { opacity: .7; }
                .addon-desc { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
                .addon-foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%; margin-top: 2px; }
                .addon-fee { font-family: 'JetBrains Mono', ui-monospace, monospace; font-weight: 600; font-size: 13.5px; color: var(--ink); }
                .addon-fee.free { font-family: inherit; color: var(--ok); font-weight: 700; }
                .addon-seats { font-size: 12px; font-weight: 700; color: var(--warn); background: var(--warn-bg); border-radius: 999px; padding: 1px 8px; }
                .addon-seats.full { color: var(--danger); background: var(--danger-bg); }
                @media (max-width: 600px) { .addon-card .card-head .end { display: none; } .choice.addon { min-height: 0; } }
                .addon-sum { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 12px 14px; border-radius: 14px; background: #F4F4FB; border: 1px solid #D6DCF7; font-size: 14px; animation: rise .3s var(--ease); }
            </style>
        @endif

        <section class="card">
            <div class="card-head"><span class="ico" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"></circle><path d="M3.8 17 C5 13.8 7.3 12.4 10 12.4 S15 13.8 16.2 17"></path></svg></span><span style="display: flex; flex-direction: column; min-width: 0;"><h2>About you</h2><span class="sub">As on your certificates</span></span></div>
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
            <div class="card-head"><span class="ico" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5 L10 3.5 L17 9.5 V16.5 H3 Z"></path><path d="M8 16.5 V12 H12 V16.5"></path></svg></span><span style="display: flex; flex-direction: column; min-width: 0;"><h2>Address</h2><span class="sub">Where you live now</span></span></div>
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
            <div class="card-head"><span class="ico" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="7.5" r="2.7"></circle><path d="M2.5 16.5 C3.3 13.9 5.2 12.7 7.5 12.7 S11.7 13.9 12.5 16.5"></path><path d="M13 5.2 A2.6 2.6 0 0 1 13 10.2"></path><path d="M14.5 12.9 C16.2 13.3 17.3 14.5 17.8 16.5"></path></svg></span><span style="display: flex; flex-direction: column; min-width: 0;"><h2>Parent or guardian</h2><span class="sub">Who we contact about your admission</span></span></div>
            <div class="grid">
                @include('portal.partials.field', ['name' => 'parent_name', 'label' => 'Name', 'required' => true])
                @include('portal.partials.field', ['name' => 'parent_phone', 'label' => 'Phone', 'type' => 'tel', 'required' => true])
                @include('portal.partials.field', ['name' => 'parent_email', 'label' => 'Email', 'type' => 'email', 'placeholder' => 'Optional'])
                @include('portal.partials.field', ['name' => 'parent_occupation', 'label' => 'Occupation'])
            </div>
        </section>

        </fieldset>

        <div class="actionbar">
            <a href="{{ $registering ? route('portal.home') : route('portal.status') }}" class="btn btn-secondary btn-sm">{{ $registering ? 'Cancel' : 'Application status' }}</a>
            <div class="group">
                @if($readOnly)
                    <a href="{{ route('portal.academic') }}" class="btn btn-primary">Next</a>
                @else
                    @if($student && $student->dues()->exists() && \App\Support\PortalProgress::paymentUnlocked($student))
                        <button type="button" class="btn btn-secondary" wire:click="saveAndPay" wire:loading.attr="disabled" wire:target="save,saveAndPay">Save and pay now</button>
                    @endif
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save,saveAndPay">
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
