<?php

namespace App\Http\Livewire\Admin\Onboarding;

use App\Models\Admin\CourseFee;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteCourse;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Module 2.3 — fee structure per institute course (e.g. Admission fee ₹15,000 due on joining,
 * Semester 1 fee due 14 days after joining). Student dues are generated from it.
 * Super Admin: any institute; others: own institute.
 */
class FeeStructureComponent extends Component
{
    use RecordsAuditTrail;

    public $instituteId = null;
    public $courseId = null;

    // form
    public $editingId = null, $fee_head, $amount, $due_days = 0, $sort_order = 0, $status = 1;

    public function mount()
    {
        abort_unless(Auth::user()->can('fees.manage'), 403);
        $this->instituteId = Auth::user()->isSuperAdmin() ? null : Auth::user()->institute_id;
    }

    public function hydrate()
    {
        abort_unless(Auth::user()->can('fees.manage'), 403);
        if (!Auth::user()->isSuperAdmin()) {
            $this->instituteId = Auth::user()->institute_id;
        }
    }

    public function updatedInstituteId()
    {
        $this->courseId = null;
    }

    protected function rules()
    {
        return [
            'fee_head' => ['required', 'string', 'max:150', Rule::unique('course_fees', 'fee_head')
                ->where('institute_id', $this->instituteId)->where('course_id', $this->courseId)->whereNull('deleted_at')->ignore($this->editingId)],
            'amount' => 'required|numeric|min:1|max:9999999',
            'due_days' => 'required|integer|min:0|max:3650',
            'sort_order' => 'required|integer|min:0|max:999',
            'status' => 'boolean',
        ];
    }

    protected function assertCourseSelected()
    {
        abort_unless($this->instituteId && $this->courseId && InstituteCourse::where('institute_id', $this->instituteId)->where('course_id', $this->courseId)->exists(), 404);
    }

    public function create()
    {
        $this->assertCourseSelected();
        $this->resetForm();
        $this->dispatchBrowserEvent('open-fee-structure-modal');
    }

    public function edit($id)
    {
        $fee = CourseFee::findOrFail($id);
        $this->resetValidation();
        $this->fill(['editingId' => $fee->id, 'fee_head' => $fee->fee_head, 'amount' => $fee->amount, 'due_days' => $fee->due_days, 'sort_order' => $fee->sort_order, 'status' => $fee->status ? 1 : 0]);
        $this->dispatchBrowserEvent('open-fee-structure-modal');
    }

    public function save()
    {
        $this->assertCourseSelected();
        $data = $this->validate();
        $data['status'] = (bool) $data['status'];

        if ($this->editingId) {
            $fee = CourseFee::findOrFail($this->editingId);
            $old = $fee->only(array_keys($data));
            $fee->update($data);
            $this->auditUpdate($fee, 'course_fees', $old, $fee->only(array_keys($data)), "Updated fee structure: {$fee->fee_head}");
        } else {
            $fee = CourseFee::create($data + ['institute_id' => $this->instituteId, 'course_id' => $this->courseId]);
            $this->auditCreate($fee, 'course_fees', "Added fee structure line: {$fee->fee_head}");
        }

        $this->dispatchBrowserEvent('close-fee-structure-modal');
        $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => 'Fee structure saved. Existing students keep their dues; use "Generate from fee structure" to add new lines.']);
        $this->resetForm();
    }

    public function delete($id)
    {
        $fee = CourseFee::findOrFail($id);

        if ($fee->dues()->exists()) {
            $this->dispatchBrowserEvent('show-toast', ['type' => 'warning', 'message' => 'This fee is already charged to students. Set it Inactive instead.']);

            return;
        }

        $this->auditDelete($fee, 'course_fees', "Removed fee structure line: {$fee->fee_head}");
        $fee->delete();
        $this->dispatchBrowserEvent('show-toast', ['type' => 'danger', 'message' => 'Fee removed from the structure.']);
    }

    protected function resetForm()
    {
        $this->resetValidation();
        $this->reset(['editingId', 'fee_head', 'amount']);
        $this->due_days = 0;
        $this->sort_order = (int) CourseFee::where('institute_id', $this->instituteId)->where('course_id', $this->courseId)->max('sort_order') + 1;
        $this->status = 1;
    }

    public function render()
    {
        $user = Auth::user();
        $courses = $this->instituteId
            ? InstituteCourse::with('course')->where('institute_id', $this->instituteId)->get()->pluck('course')->filter()->sortBy('name')
            : collect();

        $fees = $this->instituteId && $this->courseId
            ? CourseFee::where('institute_id', $this->instituteId)->where('course_id', $this->courseId)->orderBy('sort_order')->get()
            : collect();

        return view('livewire.admin.onboarding.fee-structure-component', [
            'institutes' => $user->isSuperAdmin() ? Institute::orderBy('name')->get(['id', 'name', 'code']) : Institute::whereKey($user->institute_id)->get(['id', 'name', 'code']),
            'courses' => $courses,
            'fees' => $fees,
            'total' => $fees->where('status', true)->sum('amount'),
            'isSuperAdmin' => $user->isSuperAdmin(),
        ])->layout('layouts.admin.master');
    }
}
