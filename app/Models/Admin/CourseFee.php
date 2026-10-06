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

    protected $fillable = ['institute_id', 'course_id', 'fee_head', 'amount', 'due_type', 'due_date', 'due_days', 'sort_order', 'status', 'created_by', 'updated_by'];

    protected $casts = [
        'amount' => 'decimal:2',
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
