<?php

namespace App\Http\Livewire\Portal;

use App\Http\Livewire\Portal\Concerns\StudentPortalPage;
use App\Models\Admin\EnrollmentApproval;
use Livewire\Component;

/**
 * Application status (design: "Website · Application status"): progress timeline,
 * action-needed banners (rejected documents, returned application, unpaid fees) and documents.
 */
class StatusPage extends Component
{
    use StudentPortalPage;

    public function render()
    {
        $student = $this->student()->load(['course', 'documents', 'approvals', 'dues.payments', 'payments', 'erRequest', 'idCard']);

        $docGate = $student->gate(EnrollmentApproval::DOCUMENTS);
        $feeGate = $student->gate(EnrollmentApproval::FEES);
        $required = collect(config('camp.student_document_types'))->filter(fn ($t) => $t[1]);
        $latestDocs = $student->documents->sortByDesc('uploaded_at')->unique('document_type')->keyBy('document_type');
        $rejectedDocs = $student->documents->where('verification_status', 'rejected');
        $verifiedCount = $latestDocs->only($required->keys()->all())->where('verification_status', 'verified')->count();
        $pendingPayments = $student->payments->where('status', 'pending_verification');
        $outstanding = $student->outstandingAmount();

        // Next step for a draft application
        $nextStep = match (true) {
            !$student->hasAddressDetails() => ['portal.details', 'your details'],
            !$student->academicDetail()->exists() => ['portal.academic', 'academic details'],
            default => ['portal.documents', 'documents and submit'],
        };

        return view('portal.status', compact(
            'student', 'docGate', 'feeGate', 'required', 'latestDocs', 'rejectedDocs', 'verifiedCount', 'pendingPayments', 'outstanding', 'nextStep'
        ))->layout('layouts.portal', ['title' => 'Application status']);
    }
}
