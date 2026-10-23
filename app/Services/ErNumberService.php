<?php

namespace App\Services;

use App\Models\Admin\Student;
use App\Models\Admin\StudentErNumber;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ER number management for existing students (Module 2 step 2.8), Super Admin / Institute Admin with
 * `students.manage_er`:
 *  - assign an existing (legacy) number exactly as issued, or generate the next one from the CAMP series;
 *  - change it (reason required; ID card flagged for reprint, ER form printed again);
 *  - clear it (Super Admin only, while nothing depends on it).
 * Numbers are unique across all students, and a number in the history is never reused. A typed number in
 * the CAMP format (ER-YYYY-#####) moves the series counter past it.
 */
class ErNumberService
{
    use RecordsAuditTrail;

    const PATTERN = '/^[A-Z0-9][A-Z0-9\/_.-]{2,39}$/';

    public function __construct(protected SerialNumberService $serials, protected OnboardingService $onboarding)
    {
    }

    public static function normalize(?string $value): string
    {
        return strtoupper(trim((string) $value));
    }

    /** Why this number cannot be given to the student (null = it can). */
    public function problem(?string $value, ?Student $student = null): ?string
    {
        $er = self::normalize($value);
        if ($er === '') {
            return 'Enter the ER number.';
        }
        if (!preg_match(self::PATTERN, $er)) {
            return 'Use 3–40 letters, digits or - / _ . (e.g. ER-2024-00087).';
        }
        $holder = Student::withoutGlobalScopes()->withTrashed()->whereRaw('UPPER(er_number) = ?', [$er])
            ->when($student, fn ($q) => $q->whereKeyNot($student->id))->first(['id', 'first_name', 'last_name', 'deleted_at']);
        if ($holder) {
            return "ER number {$er} already belongs to another student" . ($holder->deleted_at ? ' (deleted record)' : '') . '.';
        }
        $used = StudentErNumber::where(fn ($q) => $q->whereRaw('UPPER(er_number) = ?', [$er])->orWhereRaw('UPPER(previous_er_number) = ?', [$er]))
            ->when($student, fn ($q) => $q->where('student_id', '!=', $student->id))->exists();

        return $used ? "ER number {$er} was used before by another student and cannot be given again." : null;
    }

    /** Students who may get an ER number through the existing-student path (not admitted, not rejected). */
    public static function canAssign(Student $student): bool
    {
        return !$student->er_number && in_array($student->status, ['draft', 'pending_docs', 'pending_approval'], true);
    }

    /**
     * Give an existing (legacy) number or, with $er = null, the next one from the series, and admit the
     * student as an existing student. $options: see OnboardingService::admitExisting (+ reason).
     *
     * @return array{er_number: string, warnings: array}
     */
    public function assign(Student $student, ?string $er, array $options = []): array
    {
        if (!self::canAssign($student)) {
            throw new RuntimeException($student->er_number ? 'This student already has an ER number — use Change.' : 'A rejected student cannot get an ER number.');
        }

        $generated = $er === null || trim($er) === '';
        $warnings = [];
        $number = DB::transaction(function () use ($student, $er, $generated, $options, &$warnings) {
            if ($generated) {
                $number = $this->serials->next('ER', $student->institute);
            } else {
                $number = self::normalize($er);
                if ($problem = $this->problem($number, $student)) {
                    throw new RuntimeException($problem);
                }
                $this->serials->reserve('ER', $number);
            }

            $this->history($student, $number, null, $generated ? 'generated' : 'assigned', $options['reason'] ?? null);
            $warnings = $this->onboarding->admitExisting($student, $number, $options);

            return $number;
        });

        if (!empty($options['notify'])) {
            app(AppNotifier::class)->notify('er_issued', $student->fresh());
        }

        return ['er_number' => $number, 'warnings' => $warnings];
    }

    /** Correct an ER number (reason required). */
    public function change(Student $student, string $newEr, string $reason): string
    {
        $old = $student->er_number;
        if (!$old) {
            throw new RuntimeException('The student has no ER number yet — use Assign.');
        }
        $new = self::normalize($newEr);
        if ($new === self::normalize($old)) {
            throw new RuntimeException('That is already the student\'s ER number.');
        }
        if ($problem = $this->problem($new, $student)) {
            throw new RuntimeException($problem);
        }
        if (trim($reason) === '') {
            throw new RuntimeException('Give the reason for the change.');
        }

        DB::transaction(function () use ($student, $old, $new, $reason) {
            $this->serials->reserve('ER', $new);
            $student->update(['er_number' => $new]);

            // Printed papers carry the old number: ER form printed again, ID card reprinted.
            if (($form = $student->erRequest) && $form->status !== 'archived' && $form->status !== 'generated') {
                $form->update(['status' => 'generated', 'printed_at' => null, 'tm_signature_status' => 'pending', 'tm_signed_by' => null, 'tm_signed_at' => null]);
            }
            if (($card = $student->idCard) && $card->print_count > 0 && $card->status !== 'reprinted') {
                $card->update(['status' => 'reprinted', 'tm_signature_status' => 'pending', 'signed_by' => null]);
            }

            $this->history($student, $new, $old, 'changed', $reason);
            $this->audit('change_er', 'enrollment', $student, ['old' => ['er_number' => $old], 'new' => ['er_number' => $new], 'reason' => $reason,
                'description' => "ER number of {$student->full_name} changed from {$old} to {$new}"]);
        });

        return $new;
    }

    /** Remove the ER number (Super Admin only), while no ID card or ER form has been printed. */
    public function clear(Student $student, string $reason): void
    {
        if (!Auth::user() || !Auth::user()->isSuperAdmin()) {
            throw new RuntimeException('Only the Super Admin can clear an ER number.');
        }
        $old = $student->er_number;
        if (!$old) {
            throw new RuntimeException('The student has no ER number.');
        }
        if (optional($student->idCard)->print_count > 0 || optional($student->erRequest)->printed_at) {
            throw new RuntimeException('The ER form or ID card is already printed with this number — change it instead.');
        }
        if (trim($reason) === '') {
            throw new RuntimeException('Give the reason.');
        }

        DB::transaction(function () use ($student, $old, $reason) {
            optional($student->erRequest)->delete();
            optional($student->idCard)->delete();
            $student->update(['er_number' => null, 'status' => 'pending_approval']);
            $this->history($student, null, $old, 'cleared', $reason);
            $this->audit('clear_er', 'enrollment', $student, ['old' => ['er_number' => $old], 'new' => ['er_number' => null], 'reason' => $reason,
                'description' => "ER number {$old} of {$student->full_name} cleared"]);
        });
    }

    protected function history(Student $student, ?string $er, ?string $previous, string $action, ?string $reason): void
    {
        StudentErNumber::create([
            'student_id' => $student->id, 'institute_id' => $student->institute_id, 'er_number' => $er, 'previous_er_number' => $previous,
            'action' => $action, 'reason' => $reason, 'changed_by' => Auth::id(),
        ]);
        if (in_array($action, ['assigned', 'generated'], true)) {
            $this->audit($action === 'assigned' ? 'assign_er' : 'generate_er', 'enrollment', $student, ['new' => ['er_number' => $er], 'reason' => $reason,
                'description' => ($action === 'assigned' ? 'Existing ER number ' : 'ER number ') . "{$er} " . ($action === 'assigned' ? 'entered' : 'generated') . " for {$student->full_name}"]);
        }
    }
}
