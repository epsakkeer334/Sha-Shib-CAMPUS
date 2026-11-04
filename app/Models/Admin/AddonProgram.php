<?php

namespace App\Models\Admin;

use App\Traits\BelongsToInstitute;

/**
 * Module 2.10 — an optional add-on program of an institute course (e.g. "Aviation English"), with an optional
 * fee and seats. Applicants choose add-ons on the portal (Step 1) or the office does it in onboarding.
 */
class AddonProgram extends BaseModel
{
    use BelongsToInstitute;

    protected $fillable = ['institute_id', 'course_id', 'name', 'code', 'description', 'fee_amount', 'fee_due_days', 'capacity', 'sort_order', 'status', 'created_by', 'updated_by'];

    protected $casts = [
        'fee_amount' => 'decimal:2',
        'fee_due_days' => 'integer',
        'capacity' => 'integer',
        'sort_order' => 'integer',
        'status' => 'boolean',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function choices()
    {
        return $this->hasMany(StudentAddonProgram::class);
    }

    public function scopeActive($query)
    {
        return $query->where($this->qualifyColumn('status'), true);
    }

    public function scopeOfCourse($query, $instituteId, $courseId)
    {
        return $query->where($this->qualifyColumn('institute_id'), $instituteId)->where($this->qualifyColumn('course_id'), $courseId);
    }

    public function isFree(): bool
    {
        return (float) $this->fee_amount <= 0;
    }

    /** Seats taken: choices of students whose application is not rejected. */
    public function seatsTaken(): int
    {
        return $this->choices()->whereHas('student', fn ($q) => $q->withoutGlobalScopes()->where('status', '!=', 'rejected'))->count();
    }

    public function seatsLeft(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->seatsTaken());
    }

    public function feeLabel(): string
    {
        return $this->isFree() ? 'Free' : money_inr($this->fee_amount, false);
    }
}
