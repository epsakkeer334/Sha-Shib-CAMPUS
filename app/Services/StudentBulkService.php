<?php

namespace App\Services;

use App\Models\Admin\Batch;
use App\Models\Admin\Category;
use App\Models\Admin\Country;
use App\Models\Admin\Course;
use App\Models\Admin\HigherSecondaryBoard;
use App\Models\Admin\IdCard;
use App\Models\Admin\Institute;
use App\Models\Admin\MatriculationBoard;
use App\Models\Admin\Qualification;
use App\Models\Admin\QualifyingSubjectGroup;
use App\Models\Admin\Religion;
use App\Models\Admin\State;
use App\Models\Admin\Student;
use App\Models\Admin\StudentAcademicDetail;
use App\Models\User;
use App\Support\StudentRules;
use App\Traits\RecordsAuditTrail;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Bulk student update (Module 2 step 2.9): CSV template → upload → validation preview → apply valid rows.
 * Also used by the inline grid on the students list. One row = one student: with `student_id` it updates
 * that student (blank cell = keep), without it creates one (admitted as an existing student when an ER
 * number is given, else a draft). Rows are validated with the same rules as the onboarding forms
 * (StudentRules) and applied one by one in their own transaction.
 */
class StudentBulkService
{
    use RecordsAuditTrail;

    const MAX_ROWS = 1000;

    // column => [label, required for a new student, accepted values / help]
    const COLUMNS = [
        'student_id' => ['Student ID', false, 'CAMP student ID (blank = new student). Do not change it.'],
        'institute_code' => ['Institute code', false, 'Super Admin: institute code (required for new students). Institute users: leave as is.'],
        'er_number' => ['ER number', false, 'Existing ER number exactly as issued (e.g. ER-2024-00087). Gives / changes the number (needs the ER permission).'],
        'course_code' => ['Course', true, 'Course code (or name) offered by the institute.'],
        'batch_code' => ['Batch', false, 'Batch code of that course.'],
        'current_semester' => ['Current semester', false, 'Number 1…N (admitted students only).'],
        'joining_date' => ['Joining date', true, 'YYYY-MM-DD or DD-MM-YYYY.'],
        'status' => ['Status', false, 'draft · er_issued · active (ID card issued) · alumni (Super Admin). New students with an ER number default to er_issued.'],
        'first_name' => ['First name', true, ''],
        'last_name' => ['Last name', true, ''],
        'dob' => ['Date of birth', true, 'YYYY-MM-DD or DD-MM-YYYY.'],
        'gender' => ['Gender', true, 'male · female · other'],
        'email' => ['Email', true, 'Unique per student.'],
        'phone' => ['Phone', true, '10–15 digits, optional +.'],
        'emergency_contact' => ['Emergency contact', true, 'Different from the phone.'],
        'qualification' => ['Qualification', true, 'Name as in Master Data → Qualifications.'],
        'religion' => ['Religion', true, 'Name as in Master Data.'],
        'category' => ['Category', true, 'Category of that religion.'],
        'address' => ['Address', false, ''],
        'city' => ['City', false, ''],
        'pincode' => ['Pincode', false, ''],
        'state' => ['State', false, 'State of the country.'],
        'country' => ['Country', false, 'Name or code (IN).'],
        'parent_name' => ['Parent name', false, ''],
        'parent_phone' => ['Parent phone', false, ''],
        'parent_email' => ['Parent email', false, ''],
        'parent_occupation' => ['Parent occupation', false, ''],
        'board_10' => ['10th board', false, 'Matriculation board name.'],
        'mark_type_10' => ['10th mark type', false, 'percentage · cgpa'],
        'mark_10' => ['10th mark', false, '0–100 (%) or 0–10 (CGPA).'],
        'board_12' => ['12th board', false, 'Higher secondary board name.'],
        'stream_12' => ['12th stream', false, 'Group code: PCM · PCB · COMMERCE · ARTS …'],
        'mark_type_12' => ['12th mark type', false, 'percentage · cgpa'],
        'mark_12' => ['12th mark', false, '0–100 (%) or 0–10 (CGPA).'],
    ];

    // column => student attribute (lookups resolve to ids)
    const STUDENT_ATTR = [
        'course_code' => 'course_id', 'batch_code' => 'batch_id', 'joining_date' => 'joining_date', 'first_name' => 'first_name', 'last_name' => 'last_name',
        'dob' => 'dob', 'gender' => 'gender', 'email' => 'email', 'phone' => 'phone', 'emergency_contact' => 'emergency_contact',
        'qualification' => 'qualification_id', 'religion' => 'religion_id', 'category' => 'category_id', 'address' => 'address', 'city' => 'city',
        'pincode' => 'pincode', 'state' => 'state_id', 'country' => 'country_id', 'parent_name' => 'parent_name', 'parent_phone' => 'parent_phone',
        'parent_email' => 'parent_email', 'parent_occupation' => 'parent_occupation',
    ];

    const ACADEMIC_ATTR = [
        'board_10' => 'matriculation_board_id', 'mark_type_10' => 'matriculation_mark_type', 'mark_10' => 'matriculation_mark',
        'board_12' => 'higher_secondary_board_id', 'stream_12' => 'higher_secondary_subject', 'mark_type_12' => 'higher_secondary_mark_type', 'mark_12' => 'higher_secondary_mark',
    ];

    const STATUSES = ['draft', 'er_issued', 'active', 'alumni'];

    protected array $cache = [];

    // ------------------------------------------------------------------ template

    /** Students for the pre-filled template (institute scope applies to institute users). */
    public function studentsQuery(array $filters)
    {
        return Student::with(['institute', 'course', 'batch', 'qualification', 'religion', 'category', 'state', 'country',
            'academicDetail.matriculationBoard', 'academicDetail.higherSecondaryBoard', 'currentPeriod'])
            ->when($filters['institute'] ?? null, fn ($q, $v) => $q->where('institute_id', $v))
            ->when($filters['course'] ?? null, fn ($q, $v) => $q->where('course_id', $v))
            ->when($filters['batch'] ?? null, fn ($q, $v) => $q->where('batch_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('first_name')->orderBy('id');
    }

    /** CSV text: headers + one row per student (or one example row). */
    public function templateCsv(?Collection $students = null): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF"); // Excel: UTF-8
        fputcsv($out, array_keys(self::COLUMNS));
        if ($students === null) {
            fputcsv($out, ['example', 'SHA/2001/1010', '', 'B11', 'SHA-B11-2026', '1', now()->toDateString(), 'er_issued', 'Example', 'Student', '2006-05-14',
                'female', 'example@mail.test', '+919876543210', '+919876500000', 'Plus Two / 12th (Higher Secondary)', 'Hindu', 'General', '12 MG Road',
                'Kochi', '682001', 'Kerala', 'India', 'Parent Name', '+919876511111', '', 'Business', 'State Boards', 'percentage', '88', 'CBSE', 'PCM', 'percentage', '76']);
        } else {
            foreach ($students as $student) {
                fputcsv($out, array_values($this->rowFor($student)));
            }
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** A student as template values (the canonical form used to detect changes). */
    public function rowFor(Student $student): array
    {
        $a = $student->academicDetail;

        return [
            'student_id' => (string) $student->id,
            'institute_code' => (string) optional($student->institute)->code,
            'er_number' => (string) $student->er_number,
            'course_code' => (string) optional($student->course)->code,
            'batch_code' => (string) optional($student->batch)->code,
            'current_semester' => $student->er_number ? (string) optional($student->currentPeriod)->period_no : '',
            'joining_date' => (string) optional($student->joining_date)->toDateString(),
            'status' => (string) $student->status,
            'first_name' => (string) $student->first_name,
            'last_name' => (string) $student->last_name,
            'dob' => (string) optional($student->dob)->toDateString(),
            'gender' => (string) $student->gender,
            'email' => (string) $student->email,
            'phone' => (string) $student->phone,
            'emergency_contact' => (string) $student->emergency_contact,
            'qualification' => (string) optional($student->qualification)->name,
            'religion' => (string) optional($student->religion)->name,
            'category' => (string) optional($student->category)->name,
            'address' => (string) $student->address,
            'city' => (string) $student->city,
            'pincode' => (string) $student->pincode,
            'state' => (string) optional($student->state)->name,
            'country' => (string) optional($student->country)->name,
            'parent_name' => (string) $student->parent_name,
            'parent_phone' => (string) $student->parent_phone,
            'parent_email' => (string) $student->parent_email,
            'parent_occupation' => (string) $student->parent_occupation,
            'board_10' => (string) optional(optional($a)->matriculationBoard)->name,
            'mark_type_10' => (string) optional($a)->matriculation_mark_type,
            'mark_10' => $this->num(optional($a)->matriculation_mark),
            'board_12' => (string) optional(optional($a)->higherSecondaryBoard)->name,
            'stream_12' => (string) optional($a)->higher_secondary_subject,
            'mark_type_12' => (string) optional($a)->higher_secondary_mark_type,
            'mark_12' => $this->num(optional($a)->higher_secondary_mark),
        ];
    }

    protected function num($value): string
    {
        return $value === null || $value === '' ? '' : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    // ------------------------------------------------------------------ parse

    /**
     * Read a CSV file (UTF-8 with / without BOM, comma or semicolon).
     *
     * @return array{rows: array<int, array>, warnings: array, error: ?string}
     */
    public function parse(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false || trim($content) === '') {
            return ['rows' => [], 'warnings' => [], 'error' => 'The file is empty.'];
        }
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $firstLine = strtok($content, "\r\n");
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $headers = array_map(fn ($h) => strtolower(trim(preg_replace('/\s+/', '_', (string) $h))), fgetcsv($handle, 0, $delimiter) ?: []);
        $known = array_intersect($headers, array_keys(self::COLUMNS));
        if (!$known || !in_array('student_id', $headers, true)) {
            fclose($handle);

            return ['rows' => [], 'warnings' => [], 'error' => 'Use the CAMP template: the header row must contain student_id and the other column names.'];
        }
        $warnings = ($unknown = array_diff(array_filter($headers), array_keys(self::COLUMNS)))
            ? ['Ignored unknown columns: ' . implode(', ', $unknown) . '.'] : [];

        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue; // blank line
            }
            $row = [];
            foreach ($headers as $i => $header) {
                if (isset(self::COLUMNS[$header])) {
                    $row[$header] = trim((string) ($cells[$i] ?? ''));
                }
            }
            if (strtolower($row['student_id'] ?? '') === 'example') {
                continue;
            }
            $rows[$line] = $row;
            if (count($rows) > self::MAX_ROWS) {
                fclose($handle);

                return ['rows' => [], 'warnings' => [], 'error' => 'The file has more than ' . self::MAX_ROWS . ' rows — split it into smaller files.'];
            }
        }
        fclose($handle);

        return ['rows' => $rows, 'warnings' => $warnings, 'error' => $rows ? null : 'The file has no student rows.'];
    }

    // ------------------------------------------------------------------ validate (preview)

    /**
     * Validate rows (line => [column => value]) for the signed-in user. Nothing is saved.
     *
     * @return array{rows: array, counts: array}
     */
    public function validateRows(array $rows, User $user): array
    {
        $result = [];
        foreach ($rows as $line => $raw) {
            $result[] = $this->validateRow((int) $line, $raw, $user);
        }

        // Duplicates inside the file (email, ER number)
        foreach (['email' => 'email', 'er_number' => 'ER number'] as $col => $label) {
            $seen = [];
            foreach ($result as $i => $r) {
                $value = strtolower(trim($r['raw'][$col] ?? ''));
                if ($value !== '' && ($r['action'] !== 'error' || !isset($r['errors'][$col]))) {
                    $seen[$value][] = $i;
                }
            }
            foreach ($seen as $value => $indexes) {
                if (count($indexes) > 1) {
                    $lines = implode(', ', array_map(fn ($i) => $result[$i]['line'], $indexes));
                    foreach ($indexes as $i) {
                        $result[$i]['errors'][$col] = "Same {$label} on rows {$lines} of this file.";
                        $result[$i]['action'] = 'error';
                    }
                }
            }
        }

        $counts = ['total' => count($result), 'new' => 0, 'update' => 0, 'unchanged' => 0, 'error' => 0];
        foreach ($result as $r) {
            $counts[$r['action']]++;
        }

        return ['rows' => $result, 'counts' => $counts];
    }

    protected function validateRow(int $line, array $raw, User $user): array
    {
        $row = ['line' => $line, 'raw' => $raw, 'action' => 'error', 'student_id' => null, 'label' => trim(($raw['first_name'] ?? '') . ' ' . ($raw['last_name'] ?? '')),
            'changes' => [], 'errors' => [], 'warnings' => [], 'student' => [], 'academic' => [], 'er' => null, 'er_action' => null,
            'semester' => null, 'status' => null, 'stamp' => null, 'institute_id' => null];
        $cell = fn ($col) => trim((string) ($raw[$col] ?? ''));

        // ---- student / institute
        $student = null;
        if ($cell('student_id') !== '') {
            $student = ctype_digit($cell('student_id'))
                ? Student::withoutGlobalScopes()->whereNull('deleted_at')->with(['institute', 'course', 'batch', 'qualification', 'religion', 'category', 'state', 'country',
                    'academicDetail.matriculationBoard', 'academicDetail.higherSecondaryBoard', 'currentPeriod', 'idCard'])->find((int) $cell('student_id'))
                : null;
            if (!$student || (!$user->isSuperAdmin() && (int) $student->institute_id !== (int) $user->institute_id)) {
                $row['errors']['student_id'] = 'No student with this ID in your institute.';

                return $row;
            }
            $row['student_id'] = $student->id;
            $row['stamp'] = optional($student->updated_at)->toDateTimeString();
            $row['label'] = $student->full_name;
        }

        $instituteId = $user->isSuperAdmin() ? null : (int) $user->institute_id;
        if ($cell('institute_code') !== '') {
            $inst = $this->lookup('institutes', $cell('institute_code'));
            if (!$inst) {
                $row['errors']['institute_code'] = 'Unknown institute code.';
            } elseif ($instituteId && $inst !== $instituteId) {
                $row['errors']['institute_code'] = 'You can only update students of your own institute.';
            } else {
                $instituteId = $inst;
            }
        }
        if ($student) {
            if ($instituteId && $instituteId !== (int) $student->institute_id) {
                $row['errors']['institute_code'] = 'A student cannot be moved to another institute here.';
            }
            $instituteId = (int) $student->institute_id;
        } elseif (!$instituteId) {
            $row['errors']['institute_code'] ??= 'Required for a new student (institute code).';
        }
        $row['institute_id'] = $instituteId;
        if ($row['errors']) {
            return $row;
        }

        $old = $student ? $this->rowFor($student) : [];
        $isNew = !$student;

        // ---- required for new students
        if ($isNew) {
            foreach (self::COLUMNS as $col => [, $required]) {
                if ($required && $cell($col) === '') {
                    $row['errors'][$col] = 'Required for a new student.';
                }
            }
        }

        // ---- resolve each given cell to its canonical value / id
        $new = [];   // column => canonical display value (changed only)
        $ids = [];   // student attribute => value
        $acad = [];  // academic attribute => value

        foreach (array_keys(self::COLUMNS) as $col) {
            if (in_array($col, ['student_id', 'institute_code'], true) || $cell($col) === '') {
                continue;
            }
            $parent = ['category' => 'religion', 'state' => 'country', 'batch_code' => 'course_code'][$col] ?? null;
            if ($parent && isset($row['errors'][$parent])) {
                continue; // fix the parent value first
            }
            [$canonical, $value, $error] = $this->resolve($col, $cell($col), $instituteId, $ids, $student);
            if ($error) {
                $row['errors'][$col] = $error;
                continue;
            }
            if (!$isNew && (string) $canonical === (string) ($old[$col] ?? '')) {
                continue; // unchanged
            }
            $new[$col] = $canonical;
            if (isset(self::STUDENT_ATTR[$col])) {
                $ids[self::STUDENT_ATTR[$col]] = $value;
            } elseif (isset(self::ACADEMIC_ATTR[$col])) {
                $acad[self::ACADEMIC_ATTR[$col]] = $value;
            }
            if (!$isNew) {
                $row['changes'][$col] = [$old[$col] ?? '', $canonical];
            }
        }

        // ---- shared onboarding rules on the merged values
        if (!$row['errors'] && ($ids || $isNew)) {
            $values = array_merge($student ? $student->only(array_values(self::STUDENT_ATTR)) : [], $ids, ['institute_id' => $instituteId]);
            foreach (['dob', 'joining_date'] as $date) {
                if (($values[$date] ?? null) instanceof \DateTimeInterface) {
                    $values[$date] = $values[$date]->format('Y-m-d');
                }
            }
            // New students: every basic field; updates: the changed fields (+ the ones that depend on them).
            // Address fields are optional for existing students: only the given ones are checked.
            $basic = StudentRules::basic($values, $student);
            $rules = array_merge($basic, StudentRules::address($values));
            $check = array_merge($isNew ? array_keys($basic) : [], array_keys($ids), ['institute_id']);
            foreach (['course_id' => 'batch_id', 'religion_id' => 'category_id', 'country_id' => 'state_id'] as $parent => $child) {
                if (isset($ids[$parent])) {
                    $check[] = $child;
                }
            }
            $rules = array_intersect_key($rules, array_flip(array_unique($check)));
            $validator = Validator::make($values, $rules, StudentRules::messages());
            foreach ($validator->errors()->messages() as $attr => $messages) {
                $row['errors'][$this->columnFor($attr)] = $messages[0];
            }
        }

        // ---- academic: the merged record must be complete
        if (!$row['errors'] && $acad) {
            $existing = optional(optional($student)->academicDetail)->only(array_values(self::ACADEMIC_ATTR)) ?? [];
            $merged = array_merge(array_filter((array) $existing, fn ($v) => $v !== null), $acad);
            $validator = Validator::make($merged, StudentRules::academic($merged + ['current_group' => $existing['higher_secondary_subject'] ?? null]), StudentRules::messages());
            foreach ($validator->errors()->messages() as $attr => $messages) {
                $row['errors'][$this->columnFor($attr)] = $messages[0];
            }
            $row['academic'] = $merged;
            if (!$row['errors'] && app(EligibilityService::class)->criteriaFor((int) ($ids['course_id'] ?? optional($student)->course_id))->isNotEmpty()) {
                $row['warnings'][] = '12th subject marks for the eligibility check are entered on the student page.';
            }
        }

        // ---- course change of an admitted student / with payments
        if (!$isNew && isset($ids['course_id'])) {
            if ($student->er_number) {
                $row['errors']['course_code'] = 'The course of an admitted student cannot be changed in bulk — open the student.';
            } elseif ($student->payments()->whereIn('status', ['pending_verification', 'success'])->exists()) {
                $row['errors']['course_code'] = 'This student has paid for the course; the course cannot be changed.';
            }
        }

        // ---- email of the portal login
        if (!$isNew && isset($ids['email']) && $student->user_id
            && User::where('email', $ids['email'])->whereKeyNot($student->user_id)->exists()) {
            $row['errors']['email'] = 'This email is used by another login.';
        }

        // ---- ER number
        $hasEr = (bool) optional($student)->er_number;
        if (array_key_exists('er_number', $new)) {
            if (!$user->can('students.manage_er')) {
                $row['warnings'][] = 'ER number ignored: you do not have permission to give or change ER numbers.';
                unset($row['changes']['er_number'], $new['er_number']);
            } elseif ($problem = app(ErNumberService::class)->problem($new['er_number'], $student)) {
                $row['errors']['er_number'] = $problem;
            } elseif ($hasEr) {
                $row['er'] = $new['er_number'];
                $row['er_action'] = 'change';
            } elseif ($student && !ErNumberService::canAssign($student)) {
                $row['errors']['er_number'] = 'This student cannot get an ER number (rejected).';
            } else {
                $row['er'] = $new['er_number'];
                $row['er_action'] = 'assign';
            }
        }
        $willHaveEr = $hasEr || $row['er_action'] === 'assign';

        // ---- status
        if (array_key_exists('status', $new)) {
            $target = $new['status'];
            $from = optional($student)->status;
            if ($target === 'alumni' && !$user->isSuperAdmin()) {
                $row['errors']['status'] = 'Only the Super Admin can set Alumni.';
            } elseif ($target === 'draft' && !$isNew) {
                $row['errors']['status'] = 'A student cannot be moved back to draft.';
            } elseif (in_array($target, ['er_issued', 'active'], true) && !$willHaveEr) {
                $row['errors']['status'] = 'Needs an ER number (fill er_number).';
            } elseif ($from === 'active' && $target === 'er_issued') {
                $row['errors']['status'] = 'An active student (ID card issued) cannot go back to ER issued.';
            } else {
                $row['status'] = $target;
            }
        } elseif ($isNew && $row['er_action'] === 'assign') {
            $row['status'] = 'er_issued';
        }

        // ---- current semester
        if (array_key_exists('current_semester', $new)) {
            $courseId = (int) ($ids['course_id'] ?? optional($student)->course_id);
            $total = (int) optional(Course::find($courseId))->total_periods ?: 1;
            $semester = (int) $new['current_semester'];
            if (!$willHaveEr) {
                $row['errors']['current_semester'] = 'Only for admitted students (with an ER number).';
            } elseif ($semester < 1 || $semester > $total) {
                $row['errors']['current_semester'] = "Between 1 and {$total} for this course.";
            } else {
                $row['semester'] = $semester;
                $probe = $student ? (clone $student)->fill($ids) : new Student(array_merge($ids, ['institute_id' => $instituteId]));
                if (!app(PeriodService::class)->periodFor($probe, $semester)) {
                    $row['warnings'][] = "No academic calendar for semester {$semester} yet — the semester will not be set (set up Academic Periods).";
                }
            }
        }

        $row['student'] = $ids;
        $row['action'] = $row['errors'] ? 'error' : ($isNew ? 'new' : ($row['changes'] ? 'update' : 'unchanged'));

        return $row;
    }

    /** [canonical display value, stored value, error] of one cell. */
    protected function resolve(string $col, string $value, ?int $instituteId, array $ids, ?Student $student): array
    {
        $lookup = function (string $table, string $label, $scope = null) use ($value) {
            $id = $this->lookup($table, $value, $scope);
            if ($id) {
                return [$this->display($table, $id), $id, null];
            }
            $hint = $this->suggest($table, $value, $scope);

            return [null, null, "Unknown {$label} \"{$value}\"" . ($hint ? " — did you mean \"{$hint}\"?" : '.')];
        };

        switch ($col) {
            case 'er_number':
                return [ErNumberService::normalize($value), ErNumberService::normalize($value), null];
            case 'course_code':
                return $lookup('courses', 'course');
            case 'batch_code':
                $courseId = $ids['course_id'] ?? optional($student)->course_id;
                $batch = Batch::withoutGlobalScopes()->whereNull('deleted_at')->whereRaw('LOWER(code) = ?', [strtolower($value)])->first();
                if (!$batch) {
                    return [null, null, "Unknown batch code \"{$value}\"."];
                }

                return [$batch->code, $batch->id, (int) $batch->course_id !== (int) $courseId || (int) $batch->institute_id !== (int) $instituteId ? 'This batch belongs to another course / institute.' : null];
            case 'current_semester':
                return ctype_digit($value) ? [(string) (int) $value, (int) $value, null] : [null, null, 'Enter a number (1, 2, 3 …).'];
            case 'joining_date':
            case 'dob':
                $date = $this->date($value);

                return $date ? [$date, $date, null] : [null, null, 'Use YYYY-MM-DD or DD-MM-YYYY.'];
            case 'status':
                $v = strtolower(str_replace([' ', '-'], '_', $value));

                return in_array($v, self::STATUSES, true) ? [$v, $v, null] : [null, null, 'Use draft, er_issued, active or alumni.'];
            case 'gender':
                $v = ['m' => 'male', 'f' => 'female', 'o' => 'other'][strtolower($value)] ?? strtolower($value);

                return array_key_exists($v, config('camp.genders')) ? [$v, $v, null] : [null, null, 'Use male, female or other.'];
            case 'email':
            case 'parent_email':
                return [strtolower($value), strtolower($value), null];
            case 'qualification':
                return $lookup('qualifications', 'qualification');
            case 'religion':
                return $lookup('religions', 'religion');
            case 'category':
                $religionId = $ids['religion_id'] ?? optional($student)->religion_id;

                return $lookup('categories', 'category for this religion', $religionId);
            case 'country':
                return $lookup('countries', 'country');
            case 'state':
                $countryId = $ids['country_id'] ?? optional($student)->country_id;

                return $lookup('states', 'state for this country', $countryId);
            case 'board_10':
                return $lookup('matriculation_boards', '10th board');
            case 'board_12':
                return $lookup('higher_secondary_boards', '12th board');
            case 'stream_12':
                $group = QualifyingSubjectGroup::whereRaw('LOWER(code) = ?', [strtolower($value)])->first();

                return $group ? [$group->code, $group->code, null] : [null, null, "Unknown 12th stream \"{$value}\" (use a group code such as PCM)."];
            case 'mark_type_10':
            case 'mark_type_12':
                $v = ['%' => 'percentage', 'percent' => 'percentage'][strtolower($value)] ?? strtolower($value);

                return array_key_exists($v, config('camp.mark_types')) ? [$v, $v, null] : [null, null, 'Use percentage or cgpa.'];
            case 'mark_10':
            case 'mark_12':
                return is_numeric($value) ? [$this->num($value), (float) $value, null] : [null, null, 'Enter a number.'];
            default:
                return [$value, $value, null];
        }
    }

    protected function date(string $value): ?string
    {
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'Y/m/d', 'd.m.Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!' . $format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date->toDateString();
                }
            } catch (Throwable $e) {
                // try the next format
            }
        }

        return null;
    }

    /** Master lists: lower-cased name / code => id (cached per run). */
    protected function master(string $table, $scope = null): array
    {
        $key = $table . ':' . $scope;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        $map = [];
        $add = function ($rows, array $fields) use (&$map) {
            foreach ($rows as $r) {
                foreach ($fields as $f) {
                    if ($r->{$f} !== null && $r->{$f} !== '') {
                        $map[mb_strtolower(trim($r->{$f}))] ??= $r->id;
                    }
                }
            }
        };
        switch ($table) {
            case 'institutes': $add(Institute::get(['id', 'code', 'name']), ['code', 'name']); break;
            case 'courses': $add(Course::get(['id', 'code', 'name']), ['code', 'name']); break;
            case 'qualifications': $add(Qualification::get(['id', 'name']), ['name']); break;
            case 'religions': $add(Religion::get(['id', 'name']), ['name']); break;
            case 'categories': $add(Category::where('religion_id', $scope)->get(['id', 'name']), ['name']); break;
            case 'countries': $add(Country::get(['id', 'name', 'code']), ['name', 'code']); break;
            case 'states': $add(State::where('country_id', $scope)->get(['id', 'name', 'code']), ['name', 'code']); break;
            case 'matriculation_boards': $add(MatriculationBoard::get(['id', 'name']), ['name']); break;
            case 'higher_secondary_boards': $add(HigherSecondaryBoard::get(['id', 'name']), ['name']); break;
        }

        return $this->cache[$key] = $map;
    }

    protected function lookup(string $table, string $value, $scope = null): ?int
    {
        return $this->master($table, $scope)[mb_strtolower(trim($value))] ?? null;
    }

    protected function display(string $table, int $id): string
    {
        $model = ['institutes' => Institute::class, 'courses' => Course::class, 'qualifications' => Qualification::class, 'religions' => Religion::class,
            'categories' => Category::class, 'countries' => Country::class, 'states' => State::class,
            'matriculation_boards' => MatriculationBoard::class, 'higher_secondary_boards' => HigherSecondaryBoard::class][$table];
        $row = $model::find($id);

        return (string) ($table === 'courses' ? $row->code : ($table === 'institutes' ? $row->code : $row->name));
    }

    protected function suggest(string $table, string $value, $scope = null): ?string
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;
        foreach (array_keys($this->master($table, $scope)) as $name) {
            $distance = levenshtein(mb_strtolower($value), $name);
            if ($distance < $bestDistance) {
                [$best, $bestDistance] = [$name, $distance];
            }
        }

        return $best !== null && $bestDistance <= max(2, (int) (mb_strlen($value) / 3)) ? $this->display($table, $this->master($table, $scope)[$best]) : null;
    }

    protected function columnFor(string $attribute): string
    {
        $map = array_flip(self::STUDENT_ATTR) + array_flip(self::ACADEMIC_ATTR) + ['institute_id' => 'institute_code'];

        return $map[$attribute] ?? $attribute;
    }

    // ------------------------------------------------------------------ apply

    /**
     * Apply validated rows (new / update only), each in its own transaction.
     *
     * @return array{created: int, updated: int, skipped: array, warnings: array}
     */
    public function apply(array $rows, ?int $importId = null): array
    {
        $out = ['created' => 0, 'updated' => 0, 'skipped' => [], 'warnings' => []];
        $tag = $importId ? "bulk update #{$importId}" : 'bulk update (grid)';

        foreach ($rows as $row) {
            if (!in_array($row['action'], ['new', 'update'], true) || !empty($row['errors'])) {
                continue;
            }
            try {
                $warnings = DB::transaction(fn () => $row['action'] === 'new' ? $this->create($row, $tag) : $this->update($row, $tag));
                $out[$row['action'] === 'new' ? 'created' : 'updated']++;
                foreach ($warnings as $w) {
                    $out['warnings'][] = "Row {$row['line']}: {$w}";
                }
            } catch (Throwable $e) {
                $out['skipped'][$row['line']] = $e->getMessage();
            }
        }

        return $out;
    }

    protected function create(array $row, string $tag): array
    {
        $data = $row['student'];
        $data['institute_id'] = $row['institute_id'];
        $data['status'] = 'draft';
        $data['admission_source'] = 'import';
        $data['onboarding_deadline'] = Carbon::parse($data['joining_date'])->addDays(config('camp.onboarding_days'))->toDateString();
        $student = Student::create($data);
        $this->auditCreate($student, 'students', "Created {$student->full_name} by {$tag}");

        if ($row['academic']) {
            $this->saveAcademic($student, $row['academic']);
        }

        return $this->afterSave($student->fresh(), $row, $tag, true);
    }

    protected function update(array $row, string $tag): array
    {
        $student = Student::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($row['student_id']);
        if ($row['stamp'] && optional($student->updated_at)->toDateTimeString() !== $row['stamp']) {
            throw new \RuntimeException('The student was changed by someone else after the preview — download the template again.');
        }

        if ($row['student']) {
            $old = $student->only(array_keys($row['student']));
            $student->update($row['student']);
            if (isset($row['student']['email']) && $student->user_id) {
                User::whereKey($student->user_id)->update(['email' => $row['student']['email']]);
            }
            if (isset($row['student']['course_id']) || isset($row['student']['joining_date'])) {
                app(FeeService::class)->syncCourseDues($student->fresh());
            }
            $this->auditUpdate($student, 'students', array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $old),
                $row['student'], "Updated {$student->full_name} by {$tag}");
        }
        if ($row['academic']) {
            $this->saveAcademic($student, $row['academic']);
        }

        return $this->afterSave($student->fresh(), $row, $tag, false);
    }

    protected function saveAcademic(Student $student, array $academic): void
    {
        if (!empty($academic['higher_secondary_subject'])) {
            $academic['qualifying_subject_group_id'] = optional(QualifyingSubjectGroup::where('code', $academic['higher_secondary_subject'])->first())->id;
        }
        StudentAcademicDetail::updateOrCreate(['student_id' => $student->id], $academic);
        $result = app(EligibilityService::class)->evaluate($student->fresh());

        // Existing (admitted) students are not blocked by the 12th criteria — recorded with the reason.
        $detail = $student->academicDetail()->first();
        if ($result && $student->fresh()->er_number && $detail->eligibility_status === 'not_eligible') {
            $detail->forceFill(['eligibility_status' => 'overridden', 'eligibility_override_by' => Auth::id(), 'eligibility_override_at' => now(),
                'eligibility_override_reason' => 'Bulk import — existing student'])->save();
        }
    }

    /** ER number, status and semester (after the student record is saved). */
    protected function afterSave(Student $student, array $row, string $tag, bool $isNew): array
    {
        $warnings = [];
        $semesterDone = false;

        if ($row['er_action'] === 'assign') {
            $result = app(ErNumberService::class)->assign($student, $row['er'], [
                'semester' => $row['semester'], 'card_issued' => $row['status'] === 'active', 'source' => $isNew ? 'import' : 'existing', 'reason' => ucfirst($tag),
            ]);
            $warnings = array_merge($warnings, $result['warnings']);
            $semesterDone = true;
            $student->refresh();
        } elseif ($row['er_action'] === 'change') {
            app(ErNumberService::class)->change($student, $row['er'], ucfirst($tag));
            $student->refresh();
        }

        if ($row['status'] && $row['status'] !== $student->status) {
            if ($row['status'] === 'active') {
                $card = IdCard::withoutGlobalScopes()->firstOrCreate(['student_id' => $student->id], ['institute_id' => $student->institute_id, 'status' => 'pending', 'tm_signature_status' => 'pending']);
                $card->update(['print_count' => max(1, (int) $card->print_count), 'tm_signature_status' => 'physically_signed', 'status' => 'issued', 'issue_date' => $card->issue_date ?? now()->toDateString()]);
            }
            $old = $student->status;
            $student->update(['status' => $row['status']]);
            $this->audit('status_change', 'students', $student, ['old' => ['status' => $old], 'new' => ['status' => $row['status']], 'description' => "Status of {$student->full_name} set to {$row['status']} by {$tag}"]);
        }

        if ($row['semester'] && !$semesterDone && !app(PeriodService::class)->placeInPeriod($student, (int) $row['semester'], ucfirst($tag))) {
            $warnings[] = "semester {$row['semester']} not set — no academic calendar yet.";
        }

        return $warnings;
    }

    /** CSV of the preview rows with an `errors` column (rows with errors only). */
    public function errorReportCsv(array $preview): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_merge(['row'], array_keys(self::COLUMNS), ['errors']));
        foreach ($preview['rows'] ?? [] as $r) {
            if ($r['action'] !== 'error') {
                continue;
            }
            $errors = collect($r['errors'])->map(fn ($msg, $col) => "{$col}: {$msg}")->implode(' | ');
            fputcsv($out, array_merge([$r['line']], array_map(fn ($c) => $r['raw'][$c] ?? '', array_keys(self::COLUMNS)), [$errors]));
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }
}
