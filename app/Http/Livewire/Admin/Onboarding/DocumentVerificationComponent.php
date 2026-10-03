<?php

namespace App\Http\Livewire\Admin\Onboarding;

use App\Models\Admin\EnrollmentApproval;
use App\Models\Admin\Student;
use App\Models\Admin\StudentDocument;
use App\Services\OnboardingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use RuntimeException;

/**
 * Module 2.2 — Admin document verification (Gate 1). Design: "Admin · Document verification".
 * Queue tabs: Pending / Rejected / Gate approved. Left: students; right: document cards + gate panel.
 */
class DocumentVerificationComponent extends Component
{
    public $tab = 'pending';
    public $search = '';
    public $selectedId = null;

    /** @var array<int,string> document id => remarks */
    public $remarks = [];
    public $gateRemarks = '';

    protected $queryString = ['tab' => ['except' => 'pending'], 'selectedId' => ['as' => 'student', 'except' => null]];

    public function mount()
    {
        $this->authorizeAccess();
    }

    public function hydrate()
    {
        $this->authorizeAccess();
    }

    protected function authorizeAccess()
    {
        abort_unless(Auth::user()->can('onboarding.verify_documents'), 403);
    }

    public function updatedTab()
    {
        $this->selectedId = null;
        $this->resetErrorBag();
    }

    public function select($id)
    {
        $this->selectedId = $id;
        $this->remarks = [];
        $this->gateRemarks = '';
        $this->resetErrorBag();
    }

    protected function queue()
    {
        $gate = fn ($status) => fn ($q) => $q->where('gate', EnrollmentApproval::DOCUMENTS)->where('status', $status);

        $query = Student::with(['course', 'documents', 'approvals']);

        match ($this->tab) {
            'rejected' => $query->where(fn ($q) => $q->whereHas('approvals', $gate('rejected'))
                ->orWhere(fn ($q) => $q->whereHas('approvals', $gate('pending'))->whereHas('documents', fn ($d) => $d->where('verification_status', 'rejected')))),
            'approved' => $query->whereHas('approvals', $gate('approved')),
            default => $query->whereIn('status', ['pending_docs', 'pending_approval'])->whereHas('approvals', $gate('pending')),
        };

        if ($term = trim($this->search)) {
            $query->where(fn ($q) => $q->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
        }

        return $query->orderBy('onboarding_deadline')->limit(100)->get();
    }

    protected function counts(): array
    {
        $gate = fn ($status) => fn ($q) => $q->where('gate', EnrollmentApproval::DOCUMENTS)->where('status', $status);

        return [
            'pending' => Student::whereIn('status', ['pending_docs', 'pending_approval'])->whereHas('approvals', $gate('pending'))->count(),
            'rejected' => Student::where(fn ($q) => $q->whereHas('approvals', $gate('rejected'))
                ->orWhere(fn ($q) => $q->whereHas('approvals', $gate('pending'))->whereHas('documents', fn ($d) => $d->where('verification_status', 'rejected'))))->count(),
            'approved' => Student::whereHas('approvals', $gate('approved'))->count(),
        ];
    }

    protected function selected(): ?Student
    {
        return $this->selectedId ? Student::with(['course', 'documents.verifier', 'approvals.approver'])->find($this->selectedId) : null;
    }

    protected function document($id): StudentDocument
    {
        return StudentDocument::with('student')->findOrFail($id); // institute-scoped
    }

    // ------------------------------------------------------------------ actions

    public function verify($documentId)
    {
        $onboarding = app(OnboardingService::class);
        $this->run(fn () => $onboarding->verifyDocument($this->document($documentId), $this->remarks[$documentId] ?? null), 'Document verified.');
    }

    public function reject($documentId)
    {
        $onboarding = app(OnboardingService::class);
        $this->validate(["remarks.{$documentId}" => 'required|string|min:5|max:500'], [], ["remarks.{$documentId}" => 'remarks']);
        $this->run(fn () => $onboarding->rejectDocument($this->document($documentId), $this->remarks[$documentId]), 'Document rejected — the student has been notified.', 'warning');
    }

    /**
     * Re-review: a rejected document is approved after all (wrong rejection).
     */
    public function approveRejected($documentId)
    {
        $onboarding = app(OnboardingService::class);
        $this->run(fn () => $onboarding->approveAfterReReview($this->document($documentId), $this->remarks[$documentId] ?? null),
            'Document approved after re-review — the student has been notified.');
    }

    /**
     * Re-review: withdraw the rejection and put the document back in the pending queue.
     */
    public function reopen($documentId)
    {
        $onboarding = app(OnboardingService::class);
        $this->run(fn () => $onboarding->reopenDocument($this->document($documentId), $this->remarks[$documentId] ?? null),
            'Rejection withdrawn — the document is back in review.', 'info');
    }

    public function remind()
    {
        $onboarding = app(OnboardingService::class);
        $student = $this->selected();
        abort_unless($student, 404);

        $labels = $onboarding->remindMissingDocuments($student);
        $this->toast($labels ? 'success' : 'info', $labels ? 'Reminder sent for: ' . implode(', ', $labels) : 'No required documents are missing.');
    }

    public function approveGate()
    {
        $onboarding = app(OnboardingService::class);
        $student = $this->selected();
        abort_unless($student, 404);

        $this->run(function () use ($onboarding, $student) {
            $onboarding->approveGate($student, EnrollmentApproval::DOCUMENTS, $this->gateRemarks ?: null);
        }, 'Gate 1 approved. The student now waits for Accounts (Gate 2).');
    }

    public function rejectGate()
    {
        $onboarding = app(OnboardingService::class);
        $student = $this->selected();
        abort_unless($student, 404);

        $this->validate(['gateRemarks' => 'required|string|min:5|max:500'], [], ['gateRemarks' => 'reason']);
        $this->run(fn () => $onboarding->rejectGate($student, EnrollmentApproval::DOCUMENTS, $this->gateRemarks),
            'Application returned to the student for corrections.', 'warning');
        $this->gateRemarks = '';
    }

    protected function run(callable $action, string $message, string $type = 'success')
    {
        try {
            $action();
            $this->remarks = [];
            $this->toast($type, $message);
        } catch (RuntimeException $e) {
            $this->toast('danger', $e->getMessage());
        }
    }

    protected function toast($type, $message)
    {
        $this->dispatchBrowserEvent('show-toast', ['type' => $type, 'message' => $message]);
    }

    public function render()
    {
        $onboarding = app(OnboardingService::class);
        $student = $this->selected();

        return view('livewire.admin.onboarding.document-verification-component', [
            'students' => $this->queue(),
            'counts' => $this->counts(),
            'student' => $student,
            'documentTypes' => config('camp.student_document_types'),
            'documents' => $student ? $student->documents->sortByDesc('uploaded_at')->groupBy('document_type') : collect(),
            'gate' => $student ? $student->gate(EnrollmentApproval::DOCUMENTS) : null,
            'gateBlocker' => $student ? $onboarding->gateBlocker($student, EnrollmentApproval::DOCUMENTS) : null,
        ])->layout('layouts.admin.master');
    }
}
