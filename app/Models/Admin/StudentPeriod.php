<?php

namespace App\Models\Admin;

use App\Traits\BelongsToInstitute;

/**
 * A student's academic period history: one "current" row; completed / detained rows are kept.
 * Period 1 starts when the ER number is issued; promotion to the next period is Module 5.
 */
class StudentPeriod extends BaseModel
{
    use BelongsToInstitute;

    protected $fillable = ['institute_id', 'student_id', 'course_period_id', 'period_no', 'status', 'started_on', 'completed_on', 'promotion_id', 'remarks', 'created_by', 'updated_by'];

    protected $casts = [
        'started_on' => 'date',
        'completed_on' => 'date',
        'period_no' => 'integer',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function coursePeriod()
    {
        return $this->belongsTo(CoursePeriod::class);
    }
}
