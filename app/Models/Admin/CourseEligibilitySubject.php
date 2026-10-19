<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

/**
 * Minimum % of one required subject in a course eligibility criterion.
 */
class CourseEligibilitySubject extends Model
{
    protected $table = 'course_eligibility_subjects';

    protected $fillable = ['course_eligibility_criterion_id', 'qualifying_subject_id', 'min_percent'];

    protected $casts = ['min_percent' => 'decimal:2'];

    public function criterion()
    {
        return $this->belongsTo(CourseEligibilityCriterion::class, 'course_eligibility_criterion_id');
    }

    public function subject()
    {
        return $this->belongsTo(QualifyingSubject::class, 'qualifying_subject_id');
    }
}
