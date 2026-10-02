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
 * Institute Management → Institute Courses: which master-data courses each institute offers.
 * Inactive = not offered to new students.
 *  - institute_courses.view   → see the list
 *  - institute_courses.manage → assign / activate / remove
 * Super Admin works on any institute; everyone else only on their own institute.
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

    /**
     * @param  int|null  $institute  route {institute}. Deliberately not named like the
     *                   $institute_id form field: Livewire 2 rebuilds the page URL from public
     *                   properties named like route parameters, so a clash breaks every action.
     */
    public function mount($institute = null)
    {
        $this->authorizeView();
        $user = Auth::user();

        // Institute users always work on their own institute (another institute's id → 404).
        if (!$user->isSuperAdmin()) {
            $institute = $institute ?? $user->institute_id;
        }

        if ($institute) {
            $institute = Institute::visibleTo($user)->findOrFail($institute);
            $this->scopeInstituteId = $institute->id;
            $this->scopeInstituteName = $institute->name . ' (' . $institute->code . ')';
        }
    }

    public function hydrate()
    {
        $this->authorizeView();

        // Cannot be changed from the browser by institute users.
        if (!Auth::user()->isSuperAdmin()) {
            $this->scopeInstituteId = Auth::user()->institute_id;
        }
    }

    protected function authorizeView()
    {
        abort_unless(Auth::user() && Auth::user()->can('institute_courses.view'), 403);
    }

    protected function authorizeManage()
    {
        abort_unless(Auth::user()->can('institute_courses.manage'), 403);
    }

    /**
     * Course links the user may change: all for Super Admin, own institute for others.
     */
    protected function links()
    {
        return InstituteCourse::visibleTo(Auth::user());
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
        $this->authorizeManage();
        $this->resetValidation();
        $this->resetFields();
        $this->institute_id = $this->scopeInstituteId;
        $this->dispatchBrowserEvent('open-institute-course-modal');
    }

    public function edit($id)
    {
        $this->authorizeManage();
        $link = $this->links()->with(['institute', 'course'])->findOrFail($id);

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
        $this->authorizeManage();

        // Institute users can only assign courses to their own institute, whatever the browser sends.
        if (!Auth::user()->isSuperAdmin()) {
            $this->institute_id = Auth::user()->institute_id;
        }

        $this->validate();

        if ($this->isEdit) {
            $link = $this->links()->findOrFail($this->recordId);
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
        $this->authorizeManage();
        $this->confirmingDeleteId = $id;
        $this->dispatchBrowserEvent('open-institute-course-delete-modal');
    }

    public function delete()
    {
        $this->authorizeManage();
        $link = $this->links()->find($this->confirmingDeleteId);

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
            'institutes' => Institute::visibleTo(Auth::user())->orderBy('name')->get(['id', 'name', 'code']),
            'availableCourses' => $availableCourses,
            'editing' => $this->isEdit ? $this->links()->with(['institute', 'course'])->find($this->recordId) : null,
            'canManage' => Auth::user()->can('institute_courses.manage'),
        ])->layout('layouts.admin.master');
    }
}
