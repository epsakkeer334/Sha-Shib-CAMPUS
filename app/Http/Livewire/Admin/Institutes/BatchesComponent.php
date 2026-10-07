<?php

namespace App\Http\Livewire\Admin\Institutes;

use App\Models\Admin\Batch;
use App\Models\Admin\Course;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteCourse;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Institute Management → Batches: batches of each institute course, with a unique batch code.
 * Super Admin: every institute; Institute Admin (batches.manage): own institute only.
 * Students choose a batch when they register (portal) or are added by the office.
 */
class BatchesComponent extends Component
{
    use WithPagination, RecordsAuditTrail;

    protected $paginationTheme = 'bootstrap';

    const PER_PAGE = 15;

    // filters
    public $search = '';
    public $filterInstitute = '';
    public $filterCourse = '';
    public $filterStatus = '';

    protected $queryString = [
        'search' => ['except' => ''], 'filterInstitute' => ['except' => '', 'as' => 'institute'],
        'filterCourse' => ['except' => '', 'as' => 'course'], 'filterStatus' => ['except' => '', 'as' => 'status'],
    ];

    // form (modal)
    public $editingId = null;
    public $instituteId = null, $courseId = null;
    public $name, $code, $start_date, $end_date, $capacity, $remarks;
    public $status = 1;

    public $confirmingDeleteId = null;

    public function mount()
    {
        $this->authorizeBatches();
        $this->instituteId = Auth::user()->isSuperAdmin() ? null : Auth::user()->institute_id;
    }

    public function hydrate()
    {
        $this->authorizeBatches();
        if (!Auth::user()->isSuperAdmin()) {
            $this->instituteId = Auth::user()->institute_id; // own institute only, whatever the browser sends
            $this->filterInstitute = '';
        }
    }

    protected function authorizeBatches()
    {
        abort_unless(Auth::user() && Auth::user()->can('batches.manage'), 403);
    }

    public function updated($property)
    {
        if (in_array($property, ['search', 'filterInstitute', 'filterCourse', 'filterStatus'], true)) {
            $this->resetPage();
        }
        if ($property === 'filterInstitute') {
            $this->filterCourse = '';
        }
        if ($property === 'instituteId') {
            $this->courseId = null;
        }
        if ($property === 'code') {
            $this->code = Batch::normalizeCode($this->code);
            $this->validateOnly('code'); // duplicate / format check while typing
        }
    }

    public function clearFilters()
    {
        $this->reset(['search', 'filterInstitute', 'filterCourse', 'filterStatus']);
        $this->resetPage();
    }

    protected function rules()
    {
        return [
            'instituteId' => ['required', Rule::exists('institutes', 'id')->whereNull('deleted_at')],
            'courseId' => ['required', function ($attribute, $value, $fail) {
                if (!InstituteCourse::where('institute_id', $this->instituteId)->where('course_id', $value)->exists()) {
                    $fail('This course is not offered by the selected institute.');
                }
            }],
            'name' => ['required', 'string', 'max:150'],
            // unique across all institutes, deleted batches included
            'code' => ['required', 'regex:' . Batch::CODE_PATTERN, Rule::unique('batches', 'code')->ignore($this->editingId)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'status' => 'boolean',
        ];
    }

    protected $messages = [
        'code.unique' => 'This batch code is already used. Batch codes must be unique.',
        'code.regex' => 'Use 2–30 capital letters, digits or - / _ . (e.g. SHA-B11-2026).',
    ];

    protected $validationAttributes = ['instituteId' => 'institute', 'courseId' => 'course', 'name' => 'batch name', 'code' => 'batch code'];

    public function create($courseId = null, $instituteId = null)
    {
        $this->resetForm();
        if ($instituteId && Auth::user()->isSuperAdmin()) {
            $this->instituteId = (int) $instituteId;
        }
        if ($courseId) {
            $this->courseId = (int) $courseId;
        }
        $this->dispatchBrowserEvent('open-batch-modal');
    }

    public function edit($id)
    {
        $batch = Batch::findOrFail($id); // institute-scoped
        $this->resetValidation();
        $this->fill([
            'editingId' => $batch->id, 'instituteId' => $batch->institute_id, 'courseId' => $batch->course_id,
            'name' => $batch->name, 'code' => $batch->code, 'start_date' => optional($batch->start_date)->toDateString(),
            'end_date' => optional($batch->end_date)->toDateString(), 'capacity' => $batch->capacity,
            'remarks' => $batch->remarks, 'status' => $batch->status ? 1 : 0,
        ]);
        $this->dispatchBrowserEvent('open-batch-modal');
    }

    /** Fill the code with institute prefix - course code - start year (unique). */
    public function suggestCode()
    {
        $this->code = Batch::suggestCode(Institute::find($this->instituteId), Course::find($this->courseId), $this->start_date);
        $this->resetValidation('code');
    }

    public function save()
    {
        if (!Auth::user()->isSuperAdmin()) {
            $this->instituteId = Auth::user()->institute_id;
        }
        $this->code = Batch::normalizeCode($this->code);
        $data = $this->validate();

        $values = [
            'institute_id' => $this->instituteId, 'course_id' => $this->courseId, 'name' => trim($data['name']), 'code' => $data['code'],
            'start_date' => $data['start_date'] ?: null, 'end_date' => $data['end_date'] ?: null,
            'capacity' => $data['capacity'] !== null && $data['capacity'] !== '' ? (int) $data['capacity'] : null,
            'remarks' => $data['remarks'] ?: null, 'status' => (bool) $data['status'],
        ];

        if ($this->editingId) {
            $batch = Batch::findOrFail($this->editingId);
            if ($batch->students()->exists()) {
                // students are already in it: the institute / course cannot move
                unset($values['institute_id'], $values['course_id']);
            }
            $old = $batch->only(array_keys($values));
            $batch->update($values);
            $this->auditUpdate($batch, 'batches', $old, $batch->only(array_keys($values)), "Updated batch {$batch->code}");
            $message = "Batch {$batch->code} updated.";
        } else {
            $batch = Batch::create($values);
            $this->auditCreate($batch, 'batches', "Created batch {$batch->code} — {$batch->name}");
            $message = "Batch {$batch->code} created.";
        }

        $this->dispatchBrowserEvent('close-batch-modal');
        $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => $message]);
        $this->resetForm();
    }

    public function toggleStatus($id)
    {
        $batch = Batch::findOrFail($id);
        $old = ['status' => $batch->status];
        $batch->update(['status' => !$batch->status]);
        $this->auditUpdate($batch, 'batches', $old, ['status' => $batch->status], ($batch->status ? 'Opened' : 'Closed') . " batch {$batch->code}");
        $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => "Batch {$batch->code} is now " . ($batch->status ? 'open for admission.' : 'closed.')]);
    }

    public function confirmDelete($id)
    {
        $this->confirmingDeleteId = Batch::findOrFail($id)->id;
        $this->dispatchBrowserEvent('open-batch-delete-modal');
    }

    public function delete()
    {
        $batch = Batch::findOrFail($this->confirmingDeleteId);
        $this->confirmingDeleteId = null;
        $this->dispatchBrowserEvent('close-batch-delete-modal');

        if ($batch->students()->exists()) {
            $this->dispatchBrowserEvent('show-toast', ['type' => 'warning', 'message' => "Batch {$batch->code} has students, so it cannot be deleted. Close it instead."]);

            return;
        }

        $this->auditDelete($batch, 'batches', "Deleted batch {$batch->code}");
        $batch->delete();
        $this->dispatchBrowserEvent('show-toast', ['type' => 'danger', 'message' => "Batch {$batch->code} deleted."]);
    }

    protected function resetForm()
    {
        $this->resetValidation();
        $this->reset(['editingId', 'name', 'code', 'start_date', 'end_date', 'capacity', 'remarks']);
        $this->status = 1;
        if (Auth::user()->isSuperAdmin()) {
            $this->instituteId = $this->filterInstitute ? (int) $this->filterInstitute : $this->instituteId;
        }
        $this->courseId = $this->filterCourse ? (int) $this->filterCourse : null;
    }

    public function render()
    {
        $user = Auth::user();
        $isSuperAdmin = $user->isSuperAdmin();
        $scopeInstitute = $isSuperAdmin ? $this->filterInstitute : $user->institute_id;

        $batches = Batch::with(['institute', 'course'])
            ->withCount(['students as seats_taken' => fn ($q) => $q->where('status', '!=', 'rejected')])
            ->when($scopeInstitute, fn ($q) => $q->where('batches.institute_id', $scopeInstitute))
            ->when($this->filterCourse, fn ($q) => $q->where('batches.course_id', $this->filterCourse))
            ->when($this->filterStatus !== '', fn ($q) => $q->where('batches.status', $this->filterStatus === 'open'))
            ->when(trim($this->search), fn ($q, $term) => $q->where(fn ($w) => $w->where('batches.name', 'like', "%{$term}%")
                ->orWhere('batches.code', 'like', "%{$term}%")
                ->orWhereHas('course', fn ($c) => $c->where('code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"))))
            ->orderByDesc('batches.status')->orderByDesc('batches.start_date')->orderBy('batches.code')
            ->paginate(self::PER_PAGE);

        $base = fn () => Batch::when($scopeInstitute, fn ($q) => $q->where('institute_id', $scopeInstitute));
        $courseIds = InstituteCourse::when($scopeInstitute, fn ($q) => $q->where('institute_id', $scopeInstitute))->pluck('course_id');

        return view('livewire.admin.institutes.batches-component', [
            'batches' => $batches,
            'stats' => [
                'total' => $base()->count(),
                'open' => $base()->where('status', true)->count(),
                'closed' => $base()->where('status', false)->count(),
                'students' => \App\Models\Admin\Student::whereNotNull('batch_id')->when($scopeInstitute, fn ($q) => $q->where('institute_id', $scopeInstitute))->count(),
            ],
            'isSuperAdmin' => $isSuperAdmin,
            'institutes' => $isSuperAdmin ? Institute::orderBy('name')->get(['id', 'name', 'code']) : collect(),
            'filterCourses' => Course::whereIn('id', $courseIds)->orderBy('code')->get(['id', 'code', 'name']),
            'modalCourses' => $this->instituteId
                ? InstituteCourse::with('course')->where('institute_id', $this->instituteId)->get()->pluck('course')->filter()->sortBy('code')
                : collect(),
            'editingHasStudents' => $this->editingId ? Batch::find($this->editingId)?->students()->exists() : false,
            'codeAvailable' => $this->code && preg_match(Batch::CODE_PATTERN, (string) $this->code)
                && !Batch::withTrashed()->withoutGlobalScopes()->where('code', $this->code)->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))->exists(),
            'hasFilters' => $this->search || $this->filterInstitute || $this->filterCourse || $this->filterStatus !== '',
        ])->layout('layouts.admin.master');
    }
}
