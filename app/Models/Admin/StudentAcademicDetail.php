<?php

namespace App\Models\Admin;

/**
 * Student Academic Information (Module 2) — one row per student.
 */
class StudentAcademicDetail extends BaseModel
{
    protected $fillable = [
        'student_id',
        'matriculation_board_id', 'matriculation_mark_type', 'matriculation_mark',
        'higher_secondary_board_id', 'higher_secondary_subject', 'higher_secondary_mark_type', 'higher_secondary_mark',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'matriculation_mark' => 'decimal:2',
        'higher_secondary_mark' => 'decimal:2',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function matriculationBoard()
    {
        return $this->belongsTo(MatriculationBoard::class);
    }

    public function higherSecondaryBoard()
    {
        return $this->belongsTo(HigherSecondaryBoard::class);
    }

    /**
     * Mark followed by its unit, e.g. "92.50 %" or "9.10 CGPA".
     */
    public static function formatMark($mark, ?string $type): string
    {
        if ($mark === null) {
            return '-';
        }

        return $type === 'cgpa' ? "{$mark} CGPA" : "{$mark} %";
    }
}
