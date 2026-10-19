<?php

namespace App\Models\Admin;

use App\Traits\IsMasterData;

/**
 * Master Data — Higher Secondary (12th) subject used by course eligibility (Module 2 step 2.7),
 * e.g. Physics, Accountancy. Not the Module 3 course `subjects`.
 */
class QualifyingSubject extends BaseModel
{
    use IsMasterData;

    protected $table = 'qualifying_subjects';

    protected $fillable = ['name', 'code', 'status', 'created_by', 'updated_by'];

    public function groups()
    {
        return $this->belongsToMany(QualifyingSubjectGroup::class, 'qualifying_group_subjects')->withPivot('sort_order')->withTimestamps();
    }

    public function criterionSubjects()
    {
        return $this->hasMany(CourseEligibilitySubject::class);
    }

    public function studentMarks()
    {
        return $this->hasMany(StudentQualifyingMark::class);
    }

    protected function usageRelations(): array
    {
        return ['groups', 'criterionSubjects', 'studentMarks'];
    }
}
