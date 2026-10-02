<?php

namespace App\Http\Livewire\Portal;

use App\Http\Livewire\Portal\Concerns\StudentPortalPage;
use App\Models\Admin\EnrollmentApproval;
use App\Models\Admin\Student;
use App\Services\OnboardingService;
use App\Support\StudentRules;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Step 3 — "Documents" (design: "Website · Step 3 — Documents"). Choosing a file uploads it.
 * A draft (or returned) application is submitted from here.
 */
class DocumentsPage extends Component
{
    use StudentPortalPage, WithFileUploads;

    public $upload_kyc_photo, $upload_medical_certificate, $upload_marksheet_10, $upload_marksheet_12, $upload_other;

    /**
     * Uploads are allowed until Gate 1 (document check) is approved; a verified file stays as it is.
     */
    protected function canUpload(Student $student, string $type): bool
    {
        if (!in_array($student->status, ['draft', 'pending_docs', 'pending_approval', 'rejected'], true)
            || $student->gateApproved(EnrollmentApproval::DOCUMENTS)) {
            return false;
        }

        return $type === 'other'
            || !$student->documents()->where('document_type', $type)->where('verification_status', 'verified')->exists();
    }

    /**
     * Livewire finished receiving a file → validate and store it.
     */
    public function updated($property)
    {
        if (!str_starts_with($property, 'upload_')) {
            return;
        }

        $type = substr($property, 7);
        $types = config('camp.student_document_types');
        abort_unless(isset($types[$type]), 404);

        $student = $this->student();
        if (!$this->canUpload($student, $type)) {
            $this->reset($property);
            $this->toast('warning', 'This document can no longer be changed.');

            return;
        }

        $this->validate([$property => StudentRules::document($type)], [], [$property => $types[$type][0]]);

        app(OnboardingService::class)->storeDocument($student, $type, $this->{$property});
        $this->reset($property);
        $this->toast('success', "{$types[$type][0]} uploaded.");
    }

    public function remove($documentId)
    {
        $student = $this->student();
        $document = $student->documents()->findOrFail($documentId);

        if ($document->verification_status === 'verified' || !$this->canUpload($student, $document->document_type)) {
            $this->toast('warning', 'This document can no longer be removed.');

            return;
        }

        app(OnboardingService::class)->deleteDocument($document);
        $this->toast('info', "{$document->type_label} removed.");
    }

    public function submit()
    {
        $student = $this->student();

        if (!in_array($student->status, ['draft', 'rejected'], true)) {
            return redirect()->route('portal.payment');
        }

        $result = app(OnboardingService::class)->submit($student);

        if (!$result['submitted']) {
            $this->toast('warning', 'Please complete the ' . implode(' and ', array_keys($result['blocking'])) . ' details first.');

            return null;
        }

        session()->flash('toast', ['type' => 'success', 'message' => $result['status'] === 'pending_approval'
            ? 'Application submitted. Our team will check your documents. Next: pay your fees.'
            : 'Application submitted. Upload the missing documents so we can start checking them.']);

        return redirect()->route($result['status'] === 'pending_approval' ? 'portal.payment' : 'portal.documents');
    }

    public function render()
    {
        $student = $this->student()->load('documents');

        return view('portal.documents', [
            'student' => $student,
            'types' => config('camp.student_document_types'),
            'documents' => $student->documents->sortByDesc('uploaded_at')->groupBy('document_type'),
            'uploadable' => collect(config('camp.student_document_types'))->mapWithKeys(fn ($t, $type) => [$type => $this->canUpload($student, $type)]),
            'missing' => app(OnboardingService::class)->checklist($student),
            'canSubmit' => in_array($student->status, ['draft', 'rejected'], true),
        ])->layout('layouts.portal', ['title' => 'Documents']);
    }
}
