<?php

namespace App\Http\Livewire\Admin\Students;

use App\Models\Admin\Category;
use App\Models\Admin\Country;
use App\Models\Admin\Course;
use App\Models\Admin\HigherSecondaryBoard;
use App\Models\Admin\Institute;
use App\Models\Admin\MatriculationBoard;
use App\Models\Admin\Qualification;
use App\Models\Admin\Religion;
use App\Models\Admin\State;
use App\Models\Admin\Student;
use App\Models\Admin\StudentAcademicDetail;
use App\Models\Admin\StudentDocument;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin-side student onboarding (Module 2), tab by tab:
 *   basic → address → academic → documents → review (submit)
 * Each tab is saved on its own, so a student can be completed over several sittings.
 * The first save creates the student as "draft"; Submit moves it to pending_docs / pending_approval.
 * The future student portal will reuse the same tables and rules.
 */
class StudentOnboardingComponent extends Component
{
    use WithFileUploads, RecordsAuditTrail;

    const TABS = ['basic', 'address', 'academic', 'documents', 'review'];

    // Record id. NOT named $student: the route parameter is {student} (Livewire 2 URL rebuild).
    public $studentId = null;
    public $activeTab = 'basic';

    // Basic details
    public $institute_id, $course_id, $first_name, $last_name, $dob, $gender, $qualification_id;
    public $email, $phone, $emergency_contact, $religion_id, $category_id, $joining_date;

    // Address & parent
    public $address, $country_id, $state_id, $city, $pincode;
    public $parent_name, $parent_phone, $parent_email, $parent_occupation;

    // Academic details
    public $matriculation_board_id, $matriculation_mark_type = 'percentage', $matriculation_mark;
    public $higher_secondary_board_id, $higher_secondary_subject, $higher_secondary_mark_type = 'percentage', $higher_secondary_mark;

    // KYC uploads (one property per document type)
    public $upload_kyc_photo, $upload_medical_certificate, $upload_marksheet_10, $upload_marksheet_12, $upload_other;

    public $confirmingDocumentId = null;

    const PHONE_REGEX = 'regex:/^\+?[0-9]{10,15}$/';

    public function mount($student = null)
    {
        $user = Auth::user();

        if ($student) {
            abort_unless($user->can('students.view'), 403);
            $this->loadStudent(Student::findOrFail($student)); // institute scope → 404 for other institutes
            $tab = request()->query('tab');
            $this->activeTab = in_array($tab, self::TABS, true) ? $tab : 'basic';
        } else {
            abort_unless($user->can('students.create'), 403);
            $this->institute_id = $user->isSuperAdmin() ? null : $user->institute_id;
            $this->joining_date = now()->toDateString();
            $this->country_id = optional(Country::active()->where('code', 'IN')->first())->id;
        }
    }

    public function hydrate()
    {
        abort_unless(Auth::user()->can($this->studentId ? 'students.view' : 'students.create'), 403);
    }

    protected function loadStudent(Student $student)
    {
        $this->studentId = $student->id;
        $this->fill($student->only([
            'institute_id', 'course_id', 'first_name', 'last_name', 'gender', 'qualification_id', 'email', 'phone',
            'emergency_contact', 'religion_id', 'category_id', 'address', 'country_id', 'state_id', 'city', 'pincode',
            'parent_name', 'parent_phone', 'parent_email', 'parent_occupation',
        ]));
        $this->dob = optional($student->dob)->toDateString();
        $this->joining_date = optional($student->joining_date)->toDateString();
        $this->country_id = $this->country_id ?? optional(Country::active()->where('code', 'IN')->first())->id;

        if ($academic = $student->academicDetail) {
            $this->fill($academic->only([
                'matriculation_board_id', 'matriculation_mark_type', 'matriculation_mark',
                'higher_secondary_board_id', 'higher_secondary_subject', 'higher_secondary_mark_type', 'higher_secondary_mark',
            ]));
        }
    }

    /**
     * The student being edited (institute-scoped), or null while creating.
     */
    protected function student(): ?Student
    {
        return $this->studentId ? Student::findOrFail($this->studentId) : null;
    }

    protected function authorizeChange(): ?Student
    {
        $student = $this->student();
        abort_unless(Auth::user()->can($student ? 'students.update' : 'students.create'), 403);

        if ($student && !$student->isEditable()) {
            abort(403, 'This student can no longer be edited from onboarding.');
        }

        return $student;
    }

    // ---------------------------------------------------------------- navigation

    public function goTo($tab)
    {
        if (in_array($tab, self::TABS, true) && ($tab === 'basic' || $this->studentId)) {
            $this->resetValidation();
            $this->activeTab = $tab;
        }
    }

    // Dependent dropdowns
    public function updatedInstituteId()
    {
        $this->course_id = null;
    }

    public function updatedReligionId()
    {
        $this->category_id = null;
    }

    public function updatedCountryId()
    {
        $this->state_id = null;
    }

    // ---------------------------------------------------------------- tab 1: basic

    protected function basicRules(): array
    {
        $original = $this->student();

        // A course/master value already saved on the student stays valid even if deactivated later.
        $keep = fn ($field) => $original && (string) $original->{$field} === (string) $this->{$field};

        return [
            'institute_id' => ['required', Rule::exists('institutes', 'id')->where('status', true)->whereNull('deleted_at')],
            'course_id' => $keep('course_id') ? ['required'] : [
                'required',
                Rule::exists('institute_courses', 'course_id')
                    ->where('institute_id', $this->institute_id)->where('status', true)->whereNull('deleted_at'),
                Rule::exists('courses', 'id')->where('status', true)->whereNull('deleted_at'),
            ],
            'first_name' => ['required', 'string', 'max:100', "regex:/^[\pL\s.'-]+$/u"],
            'last_name' => ['required', 'string', 'max:100', "regex:/^[\pL\s.'-]+$/u"],
            'dob' => ['required', 'date', 'before:today', 'after:1950-01-01'],
            'gender' => ['required', Rule::in(array_keys(config('camp.genders')))],
            'qualification_id' => $keep('qualification_id') ? ['required'] : ['required', Rule::exists('qualifications', 'id')->where('status', true)->whereNull('deleted_at')],
            'email' => ['required', 'email', 'max:255', Rule::unique('students', 'email')->ignore($this->studentId)],
            'phone' => ['required', self::PHONE_REGEX],
            'emergency_contact' => ['required', self::PHONE_REGEX, 'different:phone'],
            'religion_id' => $keep('religion_id') ? ['required'] : ['required', Rule::exists('religions', 'id')->where('status', true)->whereNull('deleted_at')],
            'category_id' => $keep('category_id') && $keep('religion_id') ? ['required'] : [
                'required',
                Rule::exists('categories', 'id')->where('religion_id', $this->religion_id)->where('status', true)->whereNull('deleted_at'),
            ],
            'joining_date' => ['required', 'date', 'after:2000-01-01'],
        ];
    }

    public function saveBasic()
    {
        $student = $this->authorizeChange();

        if ($student) {
            $this->institute_id = $student->institute_id; // institute is fixed once the student exists
        } elseif (!Auth::user()->isSuperAdmin()) {
            $this->institute_id = Auth::user()->institute_id; // institute users: own institute only
        }

        $data = $this->validate($this->basicRules(), $this->messages());
        $data['onboarding_deadline'] = \Carbon\Carbon::parse($data['joining_date'])->addDays(config('camp.onboarding_days'))->toDateString();

        if ($student) {
            $old = $student->only(array_keys($data));
            $student->update($data);
            $this->auditUpdate($student, 'students', $this->stringify($old), $this->stringify($student->only(array_keys($data))), "Updated basic details: {$student->full_name}");
            $this->toast('success', 'Basic details saved.');
            $this->activeTab = 'address';

            return null;
        }

        $data['status'] = 'draft';
        $student = Student::create($data);
        $this->auditCreate($student, 'students', "Started onboarding: {$student->full_name}");
        session()->flash('toast', ['type' => 'success', 'message' => 'Student created as draft. Continue with address details.']);

        return redirect()->route('admin.students.edit', ['student' => $student->id, 'tab' => 'address']);
    }

    // ---------------------------------------------------------------- tab 2: address & parent

    protected function addressRules(): array
    {
        return [
            'address' => ['required', 'string', 'max:500'],
            'country_id' => ['required', Rule::exists('countries', 'id')->whereNull('deleted_at')],
            'state_id' => ['required', Rule::exists('states', 'id')->where('country_id', $this->country_id)->whereNull('deleted_at')],
            'city' => ['required', 'string', 'max:100'],
            'pincode' => ['required', 'regex:/^[0-9A-Za-z -]{4,10}$/'],
            'parent_name' => ['required', 'string', 'max:150'],
            'parent_phone' => ['required', self::PHONE_REGEX],
            'parent_email' => ['nullable', 'email', 'max:255'],
            'parent_occupation' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function saveAddress()
    {
        $student = $this->authorizeChange();
        abort_unless($student, 404);

        $data = $this->validate($this->addressRules(), $this->messages());
        $old = $student->only(array_keys($data));
        $student->update($data);

        $this->auditUpdate($student, 'students', $old, $student->only(array_keys($data)), "Updated address & parent details: {$student->full_name}");
        $this->toast('success', 'Address & parent details saved.');
        $this->activeTab = 'academic';
    }

    // ---------------------------------------------------------------- tab 3: academic

    protected function academicRules(): array
    {
        $markRule = fn ($type) => ['required', 'numeric', 'min:0', 'max:' . ($type === 'cgpa' ? 10 : 100)];

        return [
            'matriculation_board_id' => ['required', Rule::exists('matriculation_boards', 'id')->whereNull('deleted_at')],
            'matriculation_mark_type' => ['required', Rule::in(array_keys(config('camp.mark_types')))],
            'matriculation_mark' => $markRule($this->matriculation_mark_type),
            'higher_secondary_board_id' => ['required', Rule::exists('higher_secondary_boards', 'id')->whereNull('deleted_at')],
            'higher_secondary_subject' => ['required', Rule::in(array_keys(config('camp.higher_secondary_subjects')))],
            'higher_secondary_mark_type' => ['required', Rule::in(array_keys(config('camp.mark_types')))],
            'higher_secondary_mark' => $markRule($this->higher_secondary_mark_type),
        ];
    }

    public function saveAcademic()
    {
        $student = $this->authorizeChange();
        abort_unless($student, 404);

        $data = $this->validate($this->academicRules(), $this->messages());

        $academic = StudentAcademicDetail::firstOrNew(['student_id' => $student->id]);
        $old = $academic->exists ? $academic->only(array_keys($data)) : [];
        $academic->fill($data)->save();

        $this->auditUpdate($student, 'students', $this->stringify($old), $this->stringify($data), "Updated academic details: {$student->full_name}");
        $this->toast('success', 'Academic details saved.');
        $this->activeTab = 'documents';
    }

    // ---------------------------------------------------------------- tab 4: KYC documents

    public function uploadDocument($type)
    {
        $student = $this->authorizeChange();
        abort_unless($student && isset(config('camp.student_document_types')[$type]), 404);

        [$label, , $mimes, $maxKb] = config("camp.student_document_types.{$type}");
        $property = "upload_{$type}";

        $this->validate(
            [$property => ['required', 'file', "mimes:{$mimes}", "max:{$maxKb}"]],
            ["{$property}.required" => "Choose a file for {$label}."],
            [$property => $label]
        );

        $file = $this->{$property};

        DB::transaction(function () use ($student, $type, $file, $label) {
            // One file per required type: a new upload replaces the previous one. "Other" allows several.
            if ($type !== 'other') {
                $student->documents()->where('document_type', $type)->get()->each(fn ($old) => $this->removeDocument($old, "Replaced {$label}"));
            }

            $path = $file->store("students/{$student->id}", 'local');

            $document = StudentDocument::create([
                'student_id' => $student->id,
                'institute_id' => $student->institute_id,
                'document_type' => $type,
                'file_path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_at' => now(),
                'verification_status' => 'pending',
            ]);

            $this->auditCreate($document, 'student_documents', "Uploaded {$label} for {$student->full_name}");
        });

        $this->reset($property);
        $this->toast('success', "{$label} uploaded.");
    }

    public function confirmDeleteDocument($id)
    {
        $this->confirmingDocumentId = $id;
        $this->dispatchBrowserEvent('open-document-delete-modal');
    }

    public function deleteDocument()
    {
        $student = $this->authorizeChange();
        $document = $student ? $student->documents()->find($this->confirmingDocumentId) : null;

        if ($document) {
            $this->removeDocument($document, "Deleted {$document->type_label} of {$student->full_name}");
            $this->toast('danger', "{$document->type_label} removed.");
        }

        $this->confirmingDocumentId = null;
        $this->dispatchBrowserEvent('close-document-delete-modal');
    }

    protected function removeDocument(StudentDocument $document, string $description)
    {
        $this->auditDelete($document, 'student_documents', $description);
        Storage::disk('local')->delete($document->file_path);
        $document->delete();
    }

    // ---------------------------------------------------------------- tab 5: review & submit

    /**
     * What is still missing before the student can go for approval: tab => [messages].
     */
    protected function checklist(?Student $student): array
    {
        if (!$student) {
            return ['basic' => ['Basic details not saved yet.']];
        }

        $missing = [];
        if (!$student->hasAddressDetails()) {
            $missing['address'][] = 'Address and parent details are incomplete.';
        }
        if (!$student->academicDetail) {
            $missing['academic'][] = 'Academic details are not saved.';
        }
        foreach ($student->missingRequiredDocuments() as $type) {
            $missing['documents'][] = config("camp.student_document_types.{$type}.0") . ' is not uploaded.';
        }

        return $missing;
    }

    public function submit()
    {
        $student = $this->authorizeChange();
        abort_unless($student, 404);

        $missing = $this->checklist($student);

        // Details must be complete; missing documents only hold the student at "Pending Documents".
        $blocking = array_diff_key($missing, ['documents' => true]);
        if ($blocking) {
            $this->activeTab = array_key_first($blocking);
            $this->toast('warning', 'Complete the ' . implode(', ', array_keys($blocking)) . ' details before submitting.');

            return;
        }

        $old = $student->status;
        $student->update([
            'status' => isset($missing['documents']) ? 'pending_docs' : 'pending_approval',
            'submitted_at' => now(),
        ]);

        $this->auditUpdate($student, 'students', ['status' => $old], ['status' => $student->status], "Submitted onboarding: {$student->full_name}");
        $this->toast(
            $student->status === 'pending_approval' ? 'success' : 'warning',
            $student->status === 'pending_approval'
                ? 'Onboarding submitted for approval (document verification & fee verification).'
                : 'Saved as Pending Documents — upload the missing documents to send it for approval.'
        );
    }

    // ---------------------------------------------------------------- helpers

    protected function messages()
    {
        return [
            'first_name.regex' => 'Only letters, spaces, dots, apostrophes and hyphens are allowed.',
            'last_name.regex' => 'Only letters, spaces, dots, apostrophes and hyphens are allowed.',
            'phone.regex' => 'Enter a valid phone number (10–15 digits, optional +).',
            'emergency_contact.regex' => 'Enter a valid phone number (10–15 digits, optional +).',
            'emergency_contact.different' => 'Emergency contact must be different from the student phone.',
            'parent_phone.regex' => 'Enter a valid phone number (10–15 digits, optional +).',
            'course_id.exists' => 'This course is not offered (or not active) at the selected institute.',
            'category_id.exists' => 'Select a category of the chosen religion.',
            'state_id.exists' => 'Select a state of the chosen country.',
            'dob.before' => 'Date of birth must be in the past.',
        ];
    }

    protected function stringify(array $values): array
    {
        return array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $values);
    }

    protected function toast($type, $message)
    {
        $this->dispatchBrowserEvent('show-toast', ['type' => $type, 'message' => $message]);
    }

    public function render()
    {
        $user = Auth::user();
        $student = $this->studentId ? Student::with(['institute', 'course', 'documents', 'academicDetail'])->find($this->studentId) : null;

        $courses = $this->institute_id
            ? optional(Institute::find($this->institute_id))->offeredCourses()?->orderBy('name')->get(['courses.id', 'courses.name', 'courses.code']) ?? collect()
            : collect();

        // Keep the saved (maybe now inactive) course selectable.
        if ($student && $student->course && !$courses->contains('id', $student->course_id) && (int) $this->institute_id === (int) $student->institute_id) {
            $courses->push($student->course);
        }

        return view('livewire.admin.students.student-onboarding-component', [
            'student' => $student,
            'editable' => !$student || $student->isEditable(),
            'canEdit' => $user->can($student ? 'students.update' : 'students.create'),
            'isSuperAdmin' => $user->isSuperAdmin(),
            'institutes' => $user->isSuperAdmin()
                ? Institute::active()->orderBy('name')->get(['id', 'name', 'code'])
                : Institute::whereKey($user->institute_id)->get(['id', 'name', 'code']),
            'courses' => $courses,
            'qualifications' => $this->withCurrent(Qualification::active()->orderBy('name')->pluck('name', 'id'), Qualification::class, $this->qualification_id),
            'religions' => $this->withCurrent(Religion::active()->orderBy('name')->pluck('name', 'id'), Religion::class, $this->religion_id),
            'categories' => $this->religion_id
                ? $this->withCurrent(Category::active()->where('religion_id', $this->religion_id)->orderBy('name')->pluck('name', 'id'), Category::class, $this->category_id)
                : collect(),
            'countries' => Country::active()->orderBy('name')->pluck('name', 'id'),
            'states' => $this->country_id ? State::active()->where('country_id', $this->country_id)->orderBy('name')->pluck('name', 'id') : collect(),
            'matriculationBoards' => $this->withCurrent(MatriculationBoard::active()->orderBy('name')->pluck('name', 'id'), MatriculationBoard::class, $this->matriculation_board_id),
            'higherSecondaryBoards' => $this->withCurrent(HigherSecondaryBoard::active()->orderBy('name')->pluck('name', 'id'), HigherSecondaryBoard::class, $this->higher_secondary_board_id),
            'documentTypes' => config('camp.student_document_types'),
            'documents' => $student ? $student->documents->sortByDesc('uploaded_at')->groupBy('document_type') : collect(),
            'checklist' => $this->checklist($student),
        ])->layout('layouts.admin.master');
    }

    /**
     * Active options plus the currently saved value (so a later-deactivated master row still shows).
     */
    protected function withCurrent($options, string $model, $current)
    {
        if ($current && !$options->has($current) && ($row = $model::find($current))) {
            $options->put($row->id, $row->name . ' (inactive)');
        }

        return $options;
    }
}
