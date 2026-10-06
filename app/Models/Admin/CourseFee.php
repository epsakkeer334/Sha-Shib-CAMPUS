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

    protected $fillable = ['institute_id', 'course_id', 'fee_head', 'amount', 'admission_fee', 'due_type', 'due_date', 'due_days', 'sort_order', 'status', 'created_by', 'updated_by'];

    protected $casts = [
        'amount' => 'decimal:2',
        'admission_fee' => 'boolean',
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
        return $this->isFixedDue()
            ? $this->due_date->copy()
            : $student->joining_date->copy()->addDays((int) $this->due_days);
    }

    /** Human label: "On joining", "14 days after joining" or "Due 15 Jan 2027". */
    public function getDueLabelAttribute(): string
    {
        if ($this->isFixedDue()) {
            return 'Due ' . $this->due_date->format('d M Y');
        }

        return $this->due_days ? $this->due_days . ' ' . ($this->due_days === 1 ? 'day' : 'days') . ' after joining' : 'On joining';
    }
}
