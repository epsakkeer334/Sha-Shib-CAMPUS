<?php

namespace App\Models\Admin;

use App\Traits\BelongsToInstitute;

/**
 * One academic period (semester / term / module / year) of an institute course for an intake
 * (the academic year of period 1), optionally for one batch. Generated from the course's period
 * structure; dates editable by Super Admin / Institute Admin (Module 2B).
 */
class CoursePeriod extends BaseModel
{
    use BelongsToInstitute;

    const STATUSES = ['planned' => 'Planned', 'ongoing' => 'Ongoing', 'completed' => 'Completed'];

    protected $fillable = [
        'institute_id', 'course_id', 'batch_id', 'intake_academic_year_id', 'academic_year_id', 'period_no', 'label', 'year_of_study',
        'start_date', 'end_date', 'status', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'period_no' => 'integer',
        'year_of_study' => 'integer',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function intakeYear()
    {
        return $this->belongsTo(AcademicYear::class, 'intake_academic_year_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function studentPeriods()
    {
        return $this->hasMany(StudentPeriod::class);
    }

    /** Status shown to users: stored status, or "ongoing" while today is inside the dates. */
    public function getDisplayStatusAttribute(): string
    {
        if ($this->status === 'completed') {
            return 'completed';
        }

        return $this->start_date && now()->between($this->start_date->copy()->startOfDay(), $this->end_date->copy()->endOfDay()) ? 'ongoing' : $this->status;
    }
}
