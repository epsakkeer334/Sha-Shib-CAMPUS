<?php

namespace App\Support;

use App\Models\Admin\Student;
use Illuminate\Support\Facades\DB;

/**
 * Admissions portal progress: which step the student was on, unsaved form drafts, and
 * whether details may still be edited. Writes go straight to the table so they do not
 * touch updated_at / updated_by or the audit trail (they are not business changes).
 */
class PortalProgress
{
    const STEPS = ['details', 'academic', 'documents', 'payment'];

    const STEP_ROUTES = [
        'details' => 'portal.details',
        'academic' => 'portal.academic',
        'documents' => 'portal.documents',
        'payment' => 'portal.payment',
    ];

    public static function remember(Student $student, string $step): void
    {
        if (in_array($step, self::STEPS, true) && $student->portal_last_step !== $step) {
            DB::table('students')->where('id', $student->id)->update(['portal_last_step' => $step]);
            $student->portal_last_step = $step;
        }
    }

    /**
     * Where a returning student continues: the step they were last on while the application
     * is still open; the first incomplete step otherwise; the status page when all is done.
     */
    public static function resumeRoute(Student $student): string
    {
        if (in_array($student->status, ['er_issued', 'active', 'alumni'], true)) {
            return 'portal.status';
        }

        if ($student->portal_last_step && isset(self::STEP_ROUTES[$student->portal_last_step])) {
            // The payment step stays closed until the documents are uploaded.
            return $student->portal_last_step === 'payment' && !self::paymentUnlocked($student)
                ? 'portal.documents'
                : self::STEP_ROUTES[$student->portal_last_step];
        }

        return match (true) {
            !$student->hasAddressDetails() => 'portal.details',
            !$student->academicDetail()->exists() => 'portal.academic',
            in_array($student->status, ['draft', 'rejected'], true) || $student->missingRequiredDocuments() => 'portal.documents',
            $student->outstandingAmount() > 0 && self::paymentUnlocked($student) => 'portal.payment',
            default => 'portal.status',
        };
    }

    /**
     * Details, academic and documents can be changed until a fee payment is confirmed
     * (and never after the ER number is issued).
     */
    public static function canEdit(Student $student): bool
    {
        return in_array($student->status, ['draft', 'pending_docs', 'pending_approval', 'rejected'], true)
            && !$student->payments()->where('status', 'success')->exists();
    }

    /**
     * Payment (Pay now / the Payment step) opens once every required document is uploaded.
     * A student who already submitted a payment keeps access (e.g. a document rejected later).
     */
    public static function paymentUnlocked(Student $student): bool
    {
        return !$student->missingRequiredDocuments()
            || $student->payments()->whereIn('status', ['pending_verification', 'success'])->exists();
    }

    // ------------------------------------------------------------------ unsaved form drafts

    public static function draft(Student $student, string $step): array
    {
        return (array) data_get(json_decode($student->getRawOriginal('portal_drafts') ?? '[]', true), $step, []);
    }

    public static function saveDraftField(Student $student, string $step, string $field, $value): void
    {
        $drafts = json_decode(DB::table('students')->where('id', $student->id)->value('portal_drafts') ?? '[]', true) ?: [];
        $drafts[$step][$field] = is_scalar($value) || $value === null ? $value : null;

        DB::table('students')->where('id', $student->id)->update(['portal_drafts' => json_encode($drafts)]);
    }

    public static function clearDraft(Student $student, string $step): void
    {
        $drafts = json_decode(DB::table('students')->where('id', $student->id)->value('portal_drafts') ?? '[]', true) ?: [];
        unset($drafts[$step]);

        DB::table('students')->where('id', $student->id)->update(['portal_drafts' => $drafts ? json_encode($drafts) : null]);
    }
}
