<?php

namespace App\Models\Admin;

/**
 * Course eligibility (Module 2 step 2.7): a course accepts a 12th subject group (for one board or any
 * board) with a minimum % per required subject and an optional minimum aggregate.
 */
class CourseEligibilityCriterion extends BaseModel
{
    protected $table = 'course_eligibility_criteria';

    protected $fillable = [
        'course_id', 'higher_secondary_board_id', 'qualifying_subject_group_id', 'min_aggregate', 'remarks', 'status',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'status' => 'boolean',
        'min_aggregate' => 'decimal:2',
    ];

    public function scopeActive($query)
    {
        return $query->where($this->qualifyColumn('status'), true);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function board()
    {
        return $this->belongsTo(HigherSecondaryBoard::class, 'higher_secondary_board_id');
    }

    public function group()
    {
        return $this->belongsTo(QualifyingSubjectGroup::class, 'qualifying_subject_group_id');
    }

    public function subjects()
    {
        return $this->hasMany(CourseEligibilitySubject::class)->with('subject');
    }

    public function getBoardLabelAttribute(): string
    {
        return $this->higher_secondary_board_id ? (string) optional($this->board)->name : 'Any board';
    }
}
