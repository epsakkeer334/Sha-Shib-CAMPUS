<?php

namespace App\Support;

use App\Models\Admin\Student;
use Illuminate\Validation\Rule;

/**
 * Validation rules of student onboarding, shared by the admin onboarding page and the
 * student portal so both enforce exactly the same data rules.
 * $in holds the current form values (institute_id, course_id, religion_id, country_id, mark types …).
 */
class StudentRules
{
    const PHONE = 'regex:/^\+?[0-9]{10,15}$/';
    const NAME = "regex:/^[\pL\s.'-]+$/u";

    /**
     * Basic details. A course / master value already saved on the student stays valid even
     * if it was deactivated later.
     */
    public static function basic(array $in, ?Student $original = null): array
    {
        $keep = fn ($field) => $original && (string) $original->{$field} === (string) ($in[$field] ?? null);

        return [
            'institute_id' => ['required', Rule::exists('institutes', 'id')->where('status', true)->whereNull('deleted_at')],
            'course_id' => $keep('course_id') ? ['required'] : [
                'required',
                Rule::exists('institute_courses', 'course_id')
                    ->where('institute_id', $in['institute_id'] ?? null)->where('status', true)->whereNull('deleted_at'),
                Rule::exists('courses', 'id')->where('status', true)->whereNull('deleted_at'),
            ],
            'first_name' => ['required', 'string', 'max:100', self::NAME],
            'last_name' => ['required', 'string', 'max:100', self::NAME],
            'dob' => ['required', 'date', 'before:today', 'after:1950-01-01'],
            'gender' => ['required', Rule::in(array_keys(config('camp.genders')))],
            'qualification_id' => $keep('qualification_id') ? ['required'] : ['required', Rule::exists('qualifications', 'id')->where('status', true)->whereNull('deleted_at')],
            'email' => ['required', 'email', 'max:255', Rule::unique('students', 'email')->ignore(optional($original)->id)],
            'phone' => ['required', self::PHONE],
            'emergency_contact' => ['required', self::PHONE, 'different:phone'],
            'religion_id' => $keep('religion_id') ? ['required'] : ['required', Rule::exists('religions', 'id')->where('status', true)->whereNull('deleted_at')],
            'category_id' => $keep('category_id') && $keep('religion_id') ? ['required'] : [
                'required',
                Rule::exists('categories', 'id')->where('religion_id', $in['religion_id'] ?? null)->where('status', true)->whereNull('deleted_at'),
            ],
            'joining_date' => ['required', 'date', 'after:2000-01-01'],
            'batch_id' => self::batch($in, $original),
        ];
    }

    /**
     * Batch of the chosen institute course: required when that course has open batches, must belong
     * to it, and a full batch is refused (a student already in the batch keeps their seat).
     */
    public static function batch(array $in, ?Student $original = null): array
    {
        $instituteId = $in['institute_id'] ?? null;
        $courseId = $in['course_id'] ?? null;
        $keepsSeat = fn ($value) => $original && (int) $original->batch_id === (int) $value;
        $hasBatches = $instituteId && $courseId && \App\Models\Admin\Batch::withoutGlobalScopes()->openFor($instituteId, $courseId)->exists();

        return [
            $hasBatches ? 'required' : 'nullable',
            function ($attribute, $value, $fail) use ($instituteId, $courseId, $keepsSeat) {
                if (!$value) {
                    return;
                }
                $batch = \App\Models\Admin\Batch::withoutGlobalScopes()->find($value);
                if (!$batch || (int) $batch->institute_id !== (int) $instituteId || (int) $batch->course_id !== (int) $courseId) {
                    $fail('Choose a batch of the selected course.');

                    return;
                }
                if ($keepsSeat($value)) {
                    return;
                }
                if (!$batch->status || ($batch->end_date && $batch->end_date->isPast())) {
                    $fail('This batch is closed for admission.');
                } elseif ($batch->isFull()) {
                    $fail('This batch is full. Choose another batch.');
                }
            },
        ];
    }

    public static function address(array $in): array
    {
        return [
            'address' => ['required', 'string', 'max:500'],
            'country_id' => ['required', Rule::exists('countries', 'id')->whereNull('deleted_at')],
            'state_id' => ['required', Rule::exists('states', 'id')->where('country_id', $in['country_id'] ?? null)->whereNull('deleted_at')],
            'city' => ['required', 'string', 'max:100'],
            'pincode' => ['required', 'regex:/^[0-9A-Za-z -]{4,10}$/'],
            'parent_name' => ['required', 'string', 'max:150'],
            'parent_phone' => ['required', self::PHONE],
            'parent_email' => ['nullable', 'email', 'max:255'],
            'parent_occupation' => ['nullable', 'string', 'max:100'],
        ];
    }

    public static function academic(array $in): array
    {
        $mark = fn ($type) => ['required', 'numeric', 'min:0', 'max:' . ($type === 'cgpa' ? 10 : 100)];

        return [
            'matriculation_board_id' => ['required', Rule::exists('matriculation_boards', 'id')->whereNull('deleted_at')],
            'matriculation_mark_type' => ['required', Rule::in(array_keys(config('camp.mark_types')))],
            'matriculation_mark' => $mark($in['matriculation_mark_type'] ?? null),
            'higher_secondary_board_id' => ['required', Rule::exists('higher_secondary_boards', 'id')->whereNull('deleted_at')],
            'higher_secondary_subject' => ['required', Rule::in(array_keys(config('camp.higher_secondary_subjects')))],
            'higher_secondary_mark_type' => ['required', Rule::in(array_keys(config('camp.mark_types')))],
            'higher_secondary_mark' => $mark($in['higher_secondary_mark_type'] ?? null),
        ];
    }

    /**
     * Upload rule of one KYC document type (mimes / max size from config/camp.php).
     */
    public static function document(string $type): array
    {
        [, , $mimes, $maxKb] = config("camp.student_document_types.{$type}");

        return ['required', 'file', "mimes:{$mimes}", "max:{$maxKb}"];
    }

    public static function messages(): array
    {
        return [
            'batch_id.required' => 'Choose the batch for this course.',
            'first_name.regex' => 'Only letters, spaces, dots, apostrophes and hyphens are allowed.',
            'last_name.regex' => 'Only letters, spaces, dots, apostrophes and hyphens are allowed.',
            'phone.regex' => 'Enter a valid phone number (10–15 digits, optional +).',
            'emergency_contact.regex' => 'Enter a valid phone number (10–15 digits, optional +).',
            'emergency_contact.different' => 'Emergency contact must be different from the student phone.',
            'parent_phone.regex' => 'Enter a valid phone number (10–15 digits, optional +).',
            'course_id.exists' => 'This course is not offered (or not active) at the selected institute.',
            'category_id.exists' => 'Select a category of the chosen religion.',
            'state_id.exists' => 'Select a state of the chosen country.',
            'dob.before' => 'Date of birth must be in the past.',
            'matriculation_mark.max' => 'Mark must be between 0 and :max.',
            'higher_secondary_mark.max' => 'Mark must be between 0 and :max.',
        ];
    }
}
