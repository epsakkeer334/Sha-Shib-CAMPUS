<?php

namespace App\Models\Admin;

use App\Traits\IsMasterData;

/**
 * Master Data — Higher Secondary (12th) subject group / stream, e.g. PCM = Physics, Chemistry,
 * Mathematics (Module 2 step 2.7). The code is what `student_academic_details.higher_secondary_subject` stores.
 */
class QualifyingSubjectGroup extends BaseModel
{
    use IsMasterData;

    protected $table = 'qualifying_subject_groups';

    protected $fillable = ['code', 'name', 'status', 'created_by', 'updated_by'];

    public function subjects()
    {
        return $this->belongsToMany(QualifyingSubject::class, 'qualifying_group_subjects')
            ->withPivot('sort_order')->withTimestamps()->orderBy('qualifying_group_subjects.sort_order');
    }

    public function criteria()
    {
        return $this->hasMany(CourseEligibilityCriterion::class);
    }

    public function academicDetails()
    {
        return $this->hasMany(StudentAcademicDetail::class);
    }

    protected function usageRelations(): array
    {
        return ['criteria', 'academicDetails'];
    }

    /** "PCM — Physics, Chemistry, Mathematics" style label of the subjects. */
    public function getSubjectListAttribute(): string
    {
        return $this->subjects->pluck('name')->implode(', ');
    }
}
