<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Student;
use App\Models\Admin\StudentPayment;
use Illuminate\Support\Facades\Storage;

/**
 * Printable pages (browser print / save as PDF) and private payment proofs.
 * Models are institute-scoped, so other institutes' records return 404.
 */
class OnboardingPrintController extends Controller
{
    public function receipt(StudentPayment $payment)
    {
        abort_unless($payment->status === 'success' && $payment->receipt_number, 404);
        $payment->load(['student.institute', 'student.course', 'due', 'gateway', 'verifier']);

        return view('admin.print.receipt', ['payment' => $payment, 'student' => $payment->student]);
    }

    public function erForm(Student $student)
    {
        abort_unless($student->er_number, 404);
        $student->load(['institute', 'course', 'qualification', 'religion', 'category', 'state', 'country', 'academicDetail.matriculationBoard', 'academicDetail.higherSecondaryBoard', 'documents', 'approvals.approver', 'erRequest']);

        return view('admin.print.er-form', ['student' => $student]);
    }

    public function idCard(Student $student)
    {
        abort_unless($student->er_number && $student->idCard, 404);
        $student->load(['institute', 'course', 'idCard', 'documents']);

        return view('admin.print.id-card', [
            'student' => $student,
            'card' => $student->idCard,
            'photo' => $student->documents->where('document_type', 'kyc_photo')->sortByDesc('uploaded_at')->first(),
        ]);
    }

    public function paymentProof(StudentPayment $payment)
    {
        abort_unless($payment->proof_file_path && Storage::disk('local')->exists($payment->proof_file_path), 404);

        return Storage::disk('local')->response($payment->proof_file_path, basename($payment->proof_file_path), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
