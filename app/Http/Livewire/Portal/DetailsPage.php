<?php

namespace App\Http\Livewire\Portal;

use App\Http\Livewire\Portal\Concerns\StudentPortalPage;
use App\Models\Admin\Category;
use App\Models\Admin\Country;
use App\Models\Admin\CourseFee;
use App\Models\Admin\Institute;
use App\Models\Admin\Qualification;
use App\Models\Admin\Religion;
use App\Models\Admin\State;
use App\Models\Admin\Student;
use App\Models\User;
use App\Services\FeeService;
use App\Services\OnboardingService;
use App\Support\PortalProgress;
use App\Support\StudentRules;
use App\Traits\RecordsAuditTrail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Step 1 — "Your details" (design: "Website · Step 1 — Your details").
 * Guest: creates the student login + application (draft) and its course fees, then signs in.
 * Signed-in student: edits the same details at any stage until a fee payment is confirmed.
 * Shows the fees of the chosen course with "Save and pay now".
 */
class DetailsPage extends Component
{
    use StudentPortalPage, RecordsAuditTrail;

    public $registering = true;

    // login (registration only)
    public $email, $phone, $password, $password_confirmation;

    // course
    public $institute_id, $course_id, $joining_date;

    // about you
    public $first_name, $last_name, $dob, $gender, $qualification_id, $emergency_contact, $religion_id, $category_id;

    // address & parent
    public $address, $country_id, $state_id, $city, $pincode;
    public $parent_name, $parent_phone, $parent_email, $parent_occupation;

    public $draftRestored = false;

    // Unsaved entries kept as a draft (see StudentPortalPage::updated)
    protected $step = 'details';
    protected $draftFields = [
        'email', 'phone', 'course_id', 'joining_date', 'first_name', 'last_name', 'dob', 'gender', 'qualification_id',
        'emergency_contact', 'religion_id', 'category_id', 'address', 'country_id', 'state_id', 'city', 'pincode',
        'parent_name', 'parent_phone', 'parent_email', 'parent_occupation',
    ];

    public function mount()
    {
        $user = Auth::user();

        if ($user && !$user->hasRole('student')) {
            return redirect()->route('admin.dashboard');
        }

        if ($user) {
            $student = $this->student();

            // A returning student who opens "Apply" continues where they left off.
            if (request()->routeIs('portal.register')) {
                return redirect()->route(PortalProgress::resumeRoute($student));
            }

            $this->registering = false;
            $this->fill($student->only([
                'email', 'phone', 'institute_id', 'course_id', 'first_name', 'last_name', 'gender', 'qualification_id', 'emergency_contact',
                'religion_id', 'category_id', 'address', 'country_id', 'state_id', 'city', 'pincode',
                'parent_name', 'parent_phone', 'parent_email', 'parent_occupation',
            ]));
            $this->dob = optional($student->dob)->toDateString();
            $this->joining_date = optional($student->joining_date)->toDateString();

            if ($this->canEditDetails($student)) {
                $this->draftRestored = $this->restoreDraft($student);
            }
            PortalProgress::remember($student, 'details');
        } else {
            $this->joining_date = now()->toDateString();
        }

        $this->country_id = $this->country_id ?? optional(Country::active()->where('code', 'IN')->first())->id;
    }

    // Dependent dropdowns (called from StudentPortalPage::updated)
    protected function afterUpdated($property, $value)
    {
        $reset = ['institute_id' => 'course_id', 'religion_id' => 'category_id', 'country_id' => 'state_id'][$property] ?? null;
        if ($reset) {
            $this->{$reset} = null;

            if (Auth::check() && $this->canEditDetails($student = $this->student())) {
                PortalProgress::saveDraftField($student, $this->step, $reset, null);
            }
        }
    }

    protected function values(): array
    {
        return ['institute_id' => $this->institute_id, 'course_id' => $this->course_id, 'qualification_id' => $this->qualification_id,
            'religion_id' => $this->religion_id, 'category_id' => $this->category_id, 'country_id' => $this->country_id];
    }

    public function save()
    {
        return $this->registering ? $this->register(false) : $this->update(false);
    }

    /**
     * "Save and pay now": same validation and save, then straight to the payment step.
     */
    public function saveAndPay()
    {
        return $this->registering ? $this->register(true) : $this->update(true);
    }

    /**
     * New application: login + student (draft) + course fees.
     */
    protected function register(bool $payNow)
    {
        abort_if(Auth::check(), 403);

        $rules = array_merge(StudentRules::basic($this->values()), StudentRules::address($this->values()), [
            'email' => ['required', 'email', 'max:255', Rule::unique('students', 'email'), Rule::unique('users', 'email')],
            'phone' => ['required', StudentRules::PHONE, 'max:15', Rule::unique('users', 'phone')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $data = $this->validate($rules, $this->messages());

        // Only institutes open for admission
        $institute = Institute::active()->findOrFail($data['institute_id']);

        $user = DB::transaction(function () use ($data, $institute) {
            $user = User::create([
                'name' => trim($data['first_name'] . ' ' . $data['last_name']),
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'institute_id' => $institute->id,
                'status' => true,
            ]);
            $user->assignRole('student');

            Auth::login($user);

            $student = Student::create(collect($data)->except(['password'])->all() + [
                'user_id' => $user->id,
                'onboarding_deadline' => Carbon::parse($data['joining_date'])->addDays(config('camp.onboarding_days'))->toDateString(),
                'status' => 'draft',
            ]);
            $this->auditCreate($student, 'students', "Student registered on the admissions portal: {$student->full_name}");

            // Fees of the course are known now, so the student can pay right away.
            app(FeeService::class)->syncCourseDues($student);

            app(\App\Services\AppNotifier::class)->notify('registration_started', $student);

            return $user;
        });

        session()->regenerate();
        $user->update(['last_login_at' => now(), 'last_login_ip' => request()->ip()]);
        session()->flash('toast', ['type' => 'success', 'message' => 'Your login is created and your details are saved. Next: academic details.']);

        // No documents yet at registration, so payment is not open: continue with Academic.
        return redirect()->route('portal.academic');
    }

    protected function update(bool $payNow)
    {
        $student = $this->student();
        if (!$this->canEditDetails($student)) {
            $this->toast('warning', 'Your fee payment is confirmed, so details can no longer be changed online. Contact the admissions office for corrections.');

            return null;
        }

        $this->institute_id = $student->institute_id; // the institute cannot change after registration

        // A course with a payment already made cannot be switched online.
        if ((int) $this->course_id !== (int) $student->course_id
            && $student->payments()->whereIn('status', ['pending_verification', 'success'])->exists()) {
            $this->addError('course_id', 'You have already paid for this course. Contact the admissions office to change the course.');

            return null;
        }

        $rules = array_merge(StudentRules::basic($this->values(), $student), StudentRules::address($this->values()), [
            'email' => ['required', 'email', 'max:255', Rule::unique('students', 'email')->ignore($student->id), Rule::unique('users', 'email')->ignore(Auth::id())],
            'phone' => ['required', StudentRules::PHONE, 'max:15', Rule::unique('users', 'phone')->ignore(Auth::id())],
        ]);
        $data = $this->validate($rules, $this->messages());
        $data['onboarding_deadline'] = Carbon::parse($data['joining_date'])->addDays(config('camp.onboarding_days'))->toDateString();

        DB::transaction(function () use ($student, $data) {
            $old = $student->only(array_keys($data));
            $student->update($data);
            Auth::user()->update(['name' => $student->full_name, 'email' => $student->email, 'phone' => $student->phone]);
            $this->auditUpdate($student, 'students', array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $old),
                $data, "Student updated their details on the portal: {$student->full_name}");

            app(FeeService::class)->syncCourseDues($student->fresh());
            if ($student->submitted_at) {
                app(OnboardingService::class)->detailsChanged($student->fresh(), 'personal details');
            }
        });

        PortalProgress::clearDraft($student, 'details');
        session()->flash('toast', ['type' => 'success', 'message' => 'Your details are saved.']);

        $payNow = $payNow && PortalProgress::paymentUnlocked($student->fresh());

        return redirect()->route($payNow ? 'portal.payment' : 'portal.academic');
    }

    protected function messages()
    {
        return StudentRules::messages() + [
            'email.unique' => 'An account with this email already exists. Sign in to continue your application.',
            'phone.unique' => 'This mobile number is already registered.',
        ];
    }

    public function render()
    {
        $student = $this->registering ? null : $this->student();
        $institute = $this->institute_id ? Institute::active()->find($this->institute_id) : null;

        return view('portal.details', [
            'student' => $student,
            'readOnly' => $student && !$this->canEditDetails($student),
            'institutes' => Institute::active()->whereHas('instituteCourses', fn ($q) => $q->where('status', true))->orderBy('name')->pluck('name', 'id'),
            'courses' => $institute ? $institute->offeredCourses()->orderBy('name')->get(['courses.id', 'courses.name'])->pluck('name', 'id') : collect(),
            // Fees of the chosen course, shown before registering
            'courseFees' => $this->institute_id && $this->course_id
                ? CourseFee::active()->where('institute_id', $this->institute_id)->where('course_id', $this->course_id)->orderByDesc('admission_fee')->orderBy('sort_order')->get()
                : collect(),
            'qualifications' => Qualification::active()->orderBy('name')->pluck('name', 'id'),
            'religions' => Religion::active()->orderBy('name')->pluck('name', 'id'),
            'categories' => $this->religion_id ? Category::active()->where('religion_id', $this->religion_id)->orderBy('name')->pluck('name', 'id') : collect(),
            'countries' => Country::active()->orderBy('name')->pluck('name', 'id'),
            'states' => $this->country_id ? State::active()->where('country_id', $this->country_id)->orderBy('name')->pluck('name', 'id') : collect(),
        ])->layout('layouts.portal', ['title' => 'Your details']);
    }
}
