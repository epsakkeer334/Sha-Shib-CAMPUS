<?php

namespace App\Models\Admin;

use App\Traits\BelongsToInstitute;

/**
 * Fee structure line for a course at an institute. Student dues are generated from these.
 */
class CourseFee extends BaseModel
{
    use BelongsToInstitute;

    const DUE_JOINING = 'joining'; // due N days after the student's joining date
    const DUE_FIXED = 'fixed';     // due on a fixed calendar date
    const DUE_PERIOD_START = 'period_start'; // due on the start date of the student's academic period (period fees)

    protected $fillable = ['institute_id', 'course_id', 'academic_year_id', 'period_no', 'fee_head', 'amount', 'admission_fee', 'due_type', 'due_date', 'due_days', 'sort_order', 'status', 'created_by', 'updated_by'];

    protected $casts = [
        'amount' => 'decimal:2',
        'admission_fee' => 'boolean',
        'period_no' => 'integer',
        'due_date' => 'date',
        'due_days' => 'integer',
        'sort_order' => 'integer',
        'status' => 'boolean',
    ];

    protected $appends = ['amount_label'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function isOneTime(): bool
    {
        return $this->period_no === null;
    }

    public function dues()
    {
        return $this->hasMany(StudentDue::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /** Admission fee line(s): the only fees charged and paid during registration. */
    public function scopeAdmissionFee($query)
    {
        return $query->where('admission_fee', true);
    }

    /**
     * Does this institute course have an (active) admission fee? When it has none, all fees are
     * charged at registration (the behaviour before the registration-fee flag existed).
     */
    public static function courseHasAdmissionFee(int $instituteId, int $courseId): bool
    {
        return static::active()->admissionFee()->where('institute_id', $instituteId)->where('course_id', $courseId)->exists();
    }

    public function getAmountLabelAttribute()
    {
        return money_inr($this->amount);
    }

    public function isFixedDue(): bool
    {
        return $this->due_type === self::DUE_FIXED && $this->due_date;
    }

    /**
     * Due date of this fee for a student: the fixed date, or joining date + due_days.
     */
    public function dueDateFor(Student $student): \Carbon\Carbon
    {
        if ($this->due_type === self::DUE_PERIOD_START && $this->period_no) {
            // the start of that period in the student's calendar (joining date until periods are set up)
            $start = optional(app(\App\Services\PeriodService::class)->periodFor($student, $this->period_no))->start_date;

            return ($start ?: $student->joining_date)->copy();
        }

        return $this->isFixedDue()
            ? $this->due_date->copy()
            : $student->joining_date->copy()->addDays((int) $this->due_days);
    }

    /** Human label: "On joining", "14 days after joining" or "Due 15 Jan 2027". */
    public function getDueLabelAttribute(): string
    {
        if ($this->due_type === self::DUE_PERIOD_START && $this->period_no) {
            return 'On the period start date';
        }
        if ($this->isFixedDue()) {
            return 'Due ' . $this->due_date->format('d M Y');
        }

        return $this->due_days ? $this->due_days . ' ' . ($this->due_days === 1 ? 'day' : 'days') . ' after joining' : 'On joining';
    }
}
