<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

/**
 * A student's 12th mark in one subject (% or CGPA, plus the converted %), checked against the
 * course eligibility criteria (Module 2 step 2.7).
 */
class StudentQualifyingMark extends Model
{
    protected $table = 'student_qualifying_marks';

    protected $fillable = ['student_id', 'qualifying_subject_id', 'mark_type', 'mark', 'percent'];

    protected $casts = ['mark' => 'decimal:2', 'percent' => 'decimal:2'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function subject()
    {
        return $this->belongsTo(QualifyingSubject::class, 'qualifying_subject_id');
    }
}
