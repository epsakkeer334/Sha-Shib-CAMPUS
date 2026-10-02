<?php

namespace App\Http\Livewire\Admin\Institutes;

use App\Models\Admin\Course;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteCourse;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Institute Management → Institute Courses (Super Admin only):
 * which master-data courses each institute offers. Inactive = not offered to new students.
 */
class InstituteCoursesComponent extends Component
{
    use RecordsAuditTrail;

    public $institute_id = null;
    public $course_ids = [];
    public $status = 1;
    public $recordId = null;
    public $isEdit = false;
    public $confirmingDeleteId = null;

    // Set when opened from Institutes → Courses
    public $scopeInstituteId = null;
    public $scopeInstituteName = null;

    protected $listeners = [
        'editRecord' => 'edit',
        'deleteRecord' => 'confirmDelete',
    ];

    protected $messages = [
        'course_ids.required' => 'Select at least one course.',
    ];

    public function mount($institute_id = null)
    {
        $this->authorizeSuperAdmin();

        if ($institute_id) {
            $institute = Institute::findOrFail($institute_id);
            $this->scopeInstituteId = $institute->id;
            $this->scopeInstituteName = $institute->name . ' (' . $institute->code . ')';
        }
    }

    public function hydrate()
    {
        $this->authorizeSuperAdmin();
    }

    protected function authorizeSuperAdmin()
    {
        abort_unless(Auth::user() && Auth::user()->isSuperAdmin(), 403);
    }

    protected function rules()
    {
        if ($this->isEdit) {
            return ['status' => 'boolean'];
        }

        return [
            'institute_id' => ['required', Rule::exists('institutes', 'id')->whereNull('deleted_at')],
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => [Rule::exists('courses', 'id')->where('status', true)->whereNull('deleted_at')],
            'status' => 'boolean',
        ];
    }

    public function updatedInstituteId()
    {
        $this->course_ids = [];
    }

    public function openModal()
    {
        $this->resetValidation();
        $this->resetFields();
        $this->institute_id = $this->scopeInstituteId;
        $this->dispatchBrowserEvent('open-institute-course-modal');
    }

    public function edit($id)
    {
        $link = InstituteCourse::with(['institute', 'course'])->findOrFail($id);

        $this->resetValidation();
        $this->resetFields();
        $this->isEdit = true;
        $this->recordId = $link->id;
        $this->institute_id = $link->institute_id;
        $this->course_ids = [$link->course_id];
        $this->status = $link->status ? 1 : 0;

        $this->dispatchBrowserEvent('open-institute-course-modal');
    }

    public function save()
    {
        $this->validate();

        if ($this->isEdit) {
            $link = InstituteCourse::findOrFail($this->recordId);
            $old = ['status' => $link->status];
            $link->update(['status' => (bool) $this->status]);
            $this->auditUpdate($link, 'institute_courses', $old, ['status' => $link->status], 'Updated course offering: ' . $this->linkLabel($link));
            $message = 'Course offering updated.';
        } else {
            $added = DB::transaction(function () {
                $added = 0;
                foreach (array_unique($this->course_ids) as $courseId) {
                    // Re-adding a removed course restores the old row (unique institute + course).
                    $link = InstituteCourse::withTrashed()->firstOrNew([
                        'institute_id' => $this->institute_id,
                        'course_id' => $courseId,
                    ]);

                    if ($link->exists && !$link->trashed()) {
                        continue;
                    }

                    if ($link->trashed()) {
                        $link->restore();
                    }
                    $link->status = (bool) $this->status;
                    $link->save();

                    $this->auditCreate($link->load(['institute', 'course']), 'institute_courses', 'Added course offering: ' . $this->linkLabel($link));
                    $added++;
                }

                return $added;
            });
            $message = "{$added} course(s) added to the institute.";
        }

        $this->closeModal();
        $this->emit('refreshTable');
        $this->toast('success', $message);
    }

    protected function linkLabel(InstituteCourse $link): string
    {
        $link->loadMissing(['institute', 'course']);

        return optional($link->course)->code . ' at ' . optional($link->institute)->code;
    }

    public function confirmDelete($id)
    {
        $this->confirmingDeleteId = $id;
        $this->dispatchBrowserEvent('open-institute-course-delete-modal');
    }

    public function delete()
    {
        $link = InstituteCourse::find($this->confirmingDeleteId);

        if ($link) {
            // Once students exist (Module 2), links with enrolled students must be set Inactive instead.
            $this->auditDelete($link, 'institute_courses', 'Removed course offering: ' . $this->linkLabel($link));
            $link->delete();
            $this->emit('refreshTable');
            $this->toast('danger', 'Course removed from the institute.');
        }

        $this->confirmingDeleteId = null;
        $this->dispatchBrowserEvent('close-institute-course-delete-modal');
    }

    public function closeModal()
    {
        $this->resetFields();
        $this->dispatchBrowserEvent('close-institute-course-modal');
    }

    protected function resetFields()
    {
        $this->reset(['institute_id', 'course_ids', 'recordId', 'isEdit']);
        $this->status = 1;
    }

    protected function toast($type, $message)
    {
        $this->dispatchBrowserEvent('show-toast', ['type' => $type, 'message' => $message]);
    }

    public function render()
    {
        // Courses the selected institute does not offer yet (active courses only).
        $availableCourses = collect();
        if (!$this->isEdit && $this->institute_id) {
            $linked = InstituteCourse::where('institute_id', $this->institute_id)->pluck('course_id');
            $availableCourses = Course::active()->whereNotIn('id', $linked)->orderBy('name')->get(['id', 'name', 'code']);
        }

        return view('livewire.admin.institutes.institute-courses-component', [
            'institutes' => Institute::orderBy('name')->get(['id', 'name', 'code']),
            'availableCourses' => $availableCourses,
            'editing' => $this->isEdit ? InstituteCourse::with(['institute', 'course'])->find($this->recordId) : null,
        ])->layout('layouts.admin.master');
    }
}
