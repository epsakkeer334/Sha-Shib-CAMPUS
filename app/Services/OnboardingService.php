<?php

namespace App\Services;

use App\Models\Admin\EnrollmentApproval;
use App\Models\Admin\ErRequest;
use App\Models\Admin\IdCard;
use App\Models\Admin\Student;
use App\Models\Admin\StudentDocument;
use App\Support\SecureUpload;
use App\Traits\RecordsAuditTrail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Module 2 workflow after submission (plan.md Phase 1):
 *   KYC verification → Gate 1 (Admin documents) + Gate 2 (Accounts fees) → ER number → ER form & ID card.
 * Screens call these methods only, so the student portal (2.6) can reuse the same rules.
 * Every action is written to the audit trail.
 */
class OnboardingService
{
    use RecordsAuditTrail;

    public function __construct(
        protected NotificationService $notifications,
        protected SerialNumberService $serials,
        protected FeeService $fees
    ) {
    }

    // ------------------------------------------------------------------ KYC documents (admin page & student portal)

    /**
     * Store an uploaded KYC file. One file per required type: a new upload replaces the previous
     * one and keeps the reason it was rejected ("Re-uploaded · previously rejected: …"). "Other" allows several.
     */
    public function storeDocument(Student $student, string $type, UploadedFile $file): StudentDocument
    {
        $label = config("camp.student_document_types.{$type}.0");

        $document = DB::transaction(function () use ($student, $type, $file, $label) {
            $previousRejection = null;
            if ($type !== 'other') {
                $previous = $student->documents()->where('document_type', $type)->get();
                $previousRejection = optional($previous->firstWhere('verification_status', 'rejected'))->remarks;
                $previous->each(fn ($old) => $this->deleteDocumentFile($old, "Replaced {$label}"));
            }

            $document = StudentDocument::create([
                'student_id' => $student->id,
                'institute_id' => $student->institute_id,
                'document_type' => $type,
                'file_path' => SecureUpload::store($file, 'students/documents'),
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_at' => now(),
                'verification_status' => 'pending',
                'previous_rejection' => $previousRejection,
            ]);

            $this->audit('create', 'student_documents', $document, ['description' => "Uploaded {$label} for {$student->full_name}"]);

            return $document;
        });

        $this->documentsChanged($student->fresh());

        return $document;
    }

    public function deleteDocument(StudentDocument $document): void
    {
        $student = $document->student;
        $this->deleteDocumentFile($document, "Deleted {$document->type_label} of {$student->full_name}");
        $this->documentsChanged($student->fresh());
    }

    protected function deleteDocumentFile(StudentDocument $document, string $description): void
    {
        $this->audit('delete', 'student_documents', $document, ['old' => $document->toArray(), 'description' => $description]);
        Storage::disk('local')->delete($document->file_path);
        $document->delete();
    }

    // ------------------------------------------------------------------ submission & status

    /**
     * What is still missing before the application can go for approval: section => [messages].
     */
    public function checklist(Student $student): array
    {
        $missing = [];
        if (!$student->hasAddressDetails()) {
            $missing['address'][] = 'Address and parent details are incomplete.';
        }
        if (!$student->academicDetail()->exists()) {
            $missing['academic'][] = 'Academic details are not saved.';
        }
        foreach ($student->missingRequiredDocuments() as $type) {
            $missing['documents'][] = config("camp.student_document_types.{$type}.0") . ' is not uploaded.';
        }

        return $missing;
    }

    /**
     * Submit (or resubmit) the application. Details must be complete; missing documents only hold
     * the student at "Pending Documents". Opens both gates and adds the fees of the course.
     *
     * @return array{submitted: bool, blocking: array, status: string}
     */
    public function submit(Student $student): array
    {
        $missing = $this->checklist($student);
        $blocking = array_diff_key($missing, ['documents' => true]);

        if ($blocking) {
            return ['submitted' => false, 'blocking' => $blocking, 'status' => $student->status];
        }

        $old = $student->status;
        $student->update([
            'status' => isset($missing['documents']) ? 'pending_docs' : 'pending_approval',
            'submitted_at' => now(),
        ]);
        $this->audit('submit', 'students', $student, ['old' => ['status' => $old], 'new' => ['status' => $student->status], 'description' => "Submitted onboarding: {$student->full_name}"]);

        $this->openGates($student);

        // Fees of the course (from the institute's fee structure), so payment can start right away.
        if (!$student->dues()->exists()) {
            $this->fees->generateDues($student);
        }

        return ['submitted' => true, 'blocking' => [], 'status' => $student->status];
    }

    /**
     * Called on (re)submission: both gates exist and a previously rejected gate is reopened.
     */
    public function openGates(Student $student): void
    {
        foreach ([EnrollmentApproval::DOCUMENTS, EnrollmentApproval::FEES] as $gate) {
            $approval = EnrollmentApproval::firstOrCreate(
                ['student_id' => $student->id, 'gate' => $gate],
                ['institute_id' => $student->institute_id, 'status' => 'pending']
            );

            if ($approval->status === 'rejected') {
                $approval->update(['status' => 'pending', 'approved_by' => null, 'approved_at' => null]);
                $this->audit('reopen_gate', 'enrollment', $student, ['description' => "{$approval->label} reopened after resubmission"]);
            }
        }
    }

    /**
     * pending_docs ⇄ pending_approval depending on whether every required document is present.
     */
    public function refreshStatus(Student $student): void
    {
        if (!in_array($student->status, ['pending_docs', 'pending_approval'], true)) {
            return;
        }

        $status = $student->missingRequiredDocuments() ? 'pending_docs' : 'pending_approval';
        if ($status !== $student->status) {
            $old = $student->status;
            $student->update(['status' => $status]);
            $this->audit('status_change', 'students', $student, ['old' => ['status' => $old], 'new' => ['status' => $status]]);
        }
    }

    /**
     * A document was uploaded / replaced / removed after submission: Gate 1 must be checked again.
     */
    public function documentsChanged(Student $student): void
    {
        $gate = $student->gate(EnrollmentApproval::DOCUMENTS);

        if ($gate && $gate->status === 'approved' && !$student->er_number) {
            $gate->update(['status' => 'pending', 'approved_by' => null, 'approved_at' => null]);
            $this->audit('reopen_gate', 'enrollment', $student, ['description' => 'Gate 1 reopened: documents changed after approval']);
        }

        $this->refreshStatus($student);
    }

    /**
     * The student changed personal / academic details after submission: if Gate 1 was already
     * approved, the admin must look again.
     */
    public function detailsChanged(Student $student): void
    {
        $gate = $student->gate(EnrollmentApproval::DOCUMENTS);

        if ($gate && $gate->status === 'approved' && !$student->er_number) {
            $gate->update(['status' => 'pending', 'approved_by' => null, 'approved_at' => null]);
            $this->audit('reopen_gate', 'enrollment', $student, ['description' => 'Gate 1 reopened: the student changed their details after approval']);
        }
    }

    // ------------------------------------------------------------------ 2.2 document verification

    public function verifyDocument(StudentDocument $document, ?string $remarks = null): void
    {
        $this->assertDocumentGateOpen($document->student);

        if ($document->verification_status === 'rejected') {
            $this->approveAfterReReview($document, $remarks);

            return;
        }

        $document->update([
            'verification_status' => 'verified',
            'verified_by' => Auth::id(),
            'verified_at' => now(),
            'remarks' => $remarks ?: null,
        ]);

        $this->audit('approve', 'student_documents', $document, [
            'reason' => $remarks,
            'description' => "Verified {$document->type_label} of {$document->student->full_name}",
        ]);
    }

    /**
     * A document was rejected by mistake: the admin looks at it again and approves it.
     * The earlier rejection reason is kept in the audit trail; the student is told it is accepted.
     */
    public function approveAfterReReview(StudentDocument $document, ?string $remarks = null): void
    {
        $student = $document->student;
        $this->assertDocumentGateOpen($student);

        if ($document->verification_status !== 'rejected') {
            throw new RuntimeException('Only a rejected document can be re-reviewed.');
        }

        $previous = $document->remarks;
        $document->update([
            'verification_status' => 'verified',
            'verified_by' => Auth::id(),
            'verified_at' => now(),
            'remarks' => $remarks ?: 'Approved after re-review',
        ]);

        $this->audit('re_review_approve', 'student_documents', $document, [
            'old' => ['verification_status' => 'rejected', 'remarks' => $previous],
            'new' => ['verification_status' => 'verified', 'remarks' => $document->remarks],
            'reason' => $remarks,
            'description' => "Re-reviewed and approved {$document->type_label} of {$student->full_name} (was rejected)",
        ]);

        $this->refreshStatus($student->fresh());

        $this->notifications->send($student, 'document_accepted', "Your {$document->type_label} is accepted",
            "Dear {$student->first_name},\n\nWe checked your {$document->type_label} again and it is accepted. "
            . "You do not need to upload it again.\n\n" . optional($student->institute)->name);
    }

    /**
     * Undo a rejection without deciding yet: the document goes back to "pending" for review.
     */
    public function reopenDocument(StudentDocument $document, ?string $note = null): void
    {
        $student = $document->student;
        $this->assertDocumentGateOpen($student);

        if ($document->verification_status !== 'rejected') {
            throw new RuntimeException('Only a rejected document can be moved back to review.');
        }

        $previous = $document->remarks;
        $document->update(['verification_status' => 'pending', 'verified_by' => null, 'verified_at' => null, 'remarks' => null]);

        $this->audit('re_review_reopen', 'student_documents', $document, [
            'old' => ['verification_status' => 'rejected', 'remarks' => $previous],
            'new' => ['verification_status' => 'pending'],
            'reason' => $note,
            'description' => "Rejection of {$document->type_label} of {$student->full_name} withdrawn — back to review",
        ]);

        $this->refreshStatus($student->fresh());
    }

    public function rejectDocument(StudentDocument $document, string $remarks): void
    {
        $student = $document->student;
        $this->assertDocumentGateOpen($student);

        $document->update([
            'verification_status' => 'rejected',
            'verified_by' => Auth::id(),
            'verified_at' => now(),
            'remarks' => $remarks,
        ]);

        $this->audit('reject', 'student_documents', $document, [
            'reason' => $remarks,
            'description' => "Rejected {$document->type_label} of {$student->full_name}",
        ]);

        $this->refreshStatus($student->fresh());

        $this->notifications->send($student, 'document_rejected', "Action needed: re-upload your {$document->type_label}",
            "Dear {$student->first_name},\n\nYour {$document->type_label} could not be accepted.\nReason: {$remarks}\n\n"
            . "Please upload a new copy before {$student->formatted_onboarding_deadline}.\n\n" . optional($student->institute)->name);
    }

    /**
     * Email the student the list of required documents still missing.
     */
    public function remindMissingDocuments(Student $student): array
    {
        $missing = $student->missingRequiredDocuments();
        if (!$missing) {
            return [];
        }

        $labels = array_map(fn ($type) => config("camp.student_document_types.{$type}.0"), $missing);
        $this->notifications->send($student, 'document_reminder', 'Reminder: documents pending for your admission',
            "Dear {$student->first_name},\n\nPlease upload the following documents:\n- " . implode("\n- ", $labels)
            . "\n\nComplete onboarding before {$student->formatted_onboarding_deadline}.\n\n" . optional($student->institute)->name);

        $this->audit('remind', 'students', $student, ['description' => 'Reminder sent for: ' . implode(', ', $labels)]);

        return $labels;
    }

    protected function assertDocumentGateOpen(Student $student): void
    {
        if ($student->gateApproved(EnrollmentApproval::DOCUMENTS)) {
            throw new RuntimeException('Gate 1 is already approved for this student.');
        }
    }

    // ------------------------------------------------------------------ dual gate

    /**
     * Why the gate cannot be approved yet, or null when it can.
     */
    public function gateBlocker(Student $student, string $gate): ?string
    {
        $approval = $student->gate($gate);

        if (!$approval) {
            return 'The onboarding has not been submitted yet.';
        }
        if ($approval->status === 'approved') {
            return 'This gate is already approved.';
        }
        if ($student->status === 'rejected') {
            return 'The application was rejected and must be resubmitted first.';
        }

        if ($gate === EnrollmentApproval::DOCUMENTS) {
            if ($student->status !== 'pending_approval') {
                return 'Required documents are still missing.';
            }
            if (!$student->requiredDocumentsVerified()) {
                return 'Every required document must be verified first.';
            }

            return null;
        }

        // Gate 2 — fees
        if (!$student->gateApproved(EnrollmentApproval::DOCUMENTS)) {
            return 'Gate 1 (documents) must be approved first.';
        }
        if (!$student->dues()->exists()) {
            return 'No fees are set up for this student.';
        }
        if ($student->payments()->where('status', 'pending_verification')->exists()) {
            return 'Some payments are still waiting for verification.';
        }
        if ($student->outstandingAmount() > 0) {
            return 'Outstanding dues: ' . money_inr($student->outstandingAmount());
        }

        return null;
    }

    public function approveGate(Student $student, string $gate, ?string $remarks = null): void
    {
        if ($blocker = $this->gateBlocker($student, $gate)) {
            throw new RuntimeException($blocker);
        }

        $approval = $student->gate($gate);
        $approval->update(['status' => 'approved', 'approved_by' => Auth::id(), 'approved_at' => now(), 'remarks' => $remarks ?: null]);

        $this->audit('approve', 'enrollment', $student, [
            'reason' => $remarks,
            'description' => "{$approval->label} approved for {$student->full_name}",
        ]);

        $this->issueErIfReady($student->fresh(['approvals']));
    }

    /**
     * Gate 1 rejection: the whole application goes back to the student / admin for correction.
     */
    public function rejectGate(Student $student, string $gate, string $remarks): void
    {
        $approval = $student->gate($gate);
        if (!$approval || $approval->status === 'approved') {
            throw new RuntimeException('This gate cannot be rejected.');
        }

        $approval->update(['status' => 'rejected', 'approved_by' => Auth::id(), 'approved_at' => now(), 'remarks' => $remarks]);
        $student->update(['status' => 'rejected']);

        $this->audit('reject', 'enrollment', $student, [
            'reason' => $remarks,
            'description' => "{$approval->label} rejected for {$student->full_name}",
        ]);

        $this->notifications->send($student, 'application_rejected', 'Your admission application needs corrections',
            "Dear {$student->first_name},\n\nYour application was returned for corrections.\nReason: {$remarks}\n\n"
            . 'Please update it and submit again.' . "\n\n" . optional($student->institute)->name);
    }

    /**
     * Both gates approved → ER number (central series), ER request form and a pending ID card.
     */
    public function issueErIfReady(Student $student): bool
    {
        if ($student->er_number
            || !$student->gateApproved(EnrollmentApproval::DOCUMENTS)
            || !$student->gateApproved(EnrollmentApproval::FEES)) {
            return false;
        }

        DB::transaction(function () use ($student) {
            $erNumber = $this->serials->next('ER', $student->institute);

            $student->update(['er_number' => $erNumber, 'status' => 'er_issued']);

            ErRequest::firstOrCreate(['student_id' => $student->id], [
                'institute_id' => $student->institute_id,
                'generated_at' => now(),
                'status' => 'generated',
                'tm_signature_status' => 'pending',
            ]);

            IdCard::firstOrCreate(['student_id' => $student->id], [
                'institute_id' => $student->institute_id,
                'status' => 'pending',
                'tm_signature_status' => 'pending',
            ]);

            $this->audit('issue_er', 'enrollment', $student, ['new' => ['er_number' => $erNumber], 'description' => "ER number {$erNumber} issued to {$student->full_name}"]);
        });

        $this->notifications->send($student, 'er_issued', "Your ER number: {$student->er_number}",
            "Dear {$student->first_name},\n\nYour documents and fees are verified. Your ER number is {$student->er_number}.\n"
            . 'Your ID card will be ready after it is signed by the Training Manager.' . "\n\n" . optional($student->institute)->name);

        return true;
    }

    // ------------------------------------------------------------------ 2.4 ER request form

    public function markFormPrinted(ErRequest $form): void
    {
        $form->update([
            'printed_at' => $form->printed_at ?? now(),
            'status' => $form->status === 'generated' ? 'printed' : $form->status,
        ]);
        $this->audit('print', 'er_requests', $form, ['description' => "ER form printed for {$form->student->full_name}"]);
    }

    public function markFormSigned(ErRequest $form): void
    {
        if (!$form->printed_at) {
            throw new RuntimeException('Print the ER request form before marking it signed.');
        }
        if ($form->tm_signature_status === 'physically_signed') {
            throw new RuntimeException('The form is already marked signed.');
        }

        $form->update(['tm_signature_status' => 'physically_signed', 'tm_signed_by' => Auth::id(), 'tm_signed_at' => now(), 'status' => 'signed']);
        $this->audit('sign', 'er_requests', $form, ['description' => "ER form marked as physically signed by the TM for {$form->student->full_name}"]);
    }

    public function markFormArchived(ErRequest $form): void
    {
        if ($form->tm_signature_status !== 'physically_signed') {
            throw new RuntimeException('The form must be signed by the TM before it is archived.');
        }
        if ($form->archived_at) {
            throw new RuntimeException('The form is already archived.');
        }

        $form->update(['archived_at' => now(), 'archived_by' => Auth::id(), 'status' => 'archived']);
        $this->audit('archive', 'er_requests', $form, ['description' => "Signed ER form archived for {$form->student->full_name}"]);
    }

    // ------------------------------------------------------------------ 2.5 ID card

    public function markCardPrinted(IdCard $card): void
    {
        $card->increment('print_count');
        $this->audit('print', 'id_cards', $card, ['description' => "ID card printed for {$card->student->full_name} (print #{$card->print_count})"]);
    }

    /**
     * TM signed the printed card by hand → issued; the student becomes Active.
     */
    public function issueCard(IdCard $card): void
    {
        if ($card->print_count < 1) {
            throw new RuntimeException('Print the ID card before marking it signed and issued.');
        }
        if ($card->tm_signature_status === 'physically_signed') {
            throw new RuntimeException('The ID card is already issued.');
        }

        $card->update(['tm_signature_status' => 'physically_signed', 'signed_by' => Auth::id(), 'issue_date' => now()->toDateString(), 'status' => 'issued']);

        $student = $card->student;
        if ($student->status === 'er_issued') {
            $student->update(['status' => 'active']);
        }

        $this->audit('issue', 'id_cards', $card, ['description' => "ID card signed & issued to {$student->full_name}"]);
    }

    /**
     * Lost / damaged card: a new card must be printed and signed again.
     */
    public function reprintCard(IdCard $card, string $reason): void
    {
        if ($card->tm_signature_status !== 'physically_signed') {
            throw new RuntimeException('Only an issued card can be reprinted.');
        }

        $card->update(['status' => 'reprinted', 'tm_signature_status' => 'pending', 'signed_by' => null]);
        $this->audit('reprint', 'id_cards', $card, ['reason' => $reason, 'description' => "ID card reprint started for {$card->student->full_name}"]);
    }
}
