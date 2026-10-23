<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

/**
 * History of a student's ER number (Module 2 step 2.8): issued by the gates, assigned (existing / legacy
 * number), generated from the series, changed or cleared — with who and why. Numbers in this history
 * are never given to another student.
 */
class StudentErNumber extends Model
{
    const UPDATED_AT = null;

    protected $table = 'student_er_numbers';

    protected $fillable = ['student_id', 'institute_id', 'er_number', 'previous_er_number', 'action', 'reason', 'changed_by'];

    const ACTIONS = [
        'issued' => ['Issued after Gate 1 & 2', 'success'],
        'assigned' => ['Existing number entered', 'info'],
        'generated' => ['Generated from the series', 'primary'],
        'changed' => ['Changed', 'warning'],
        'cleared' => ['Cleared', 'danger'],
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function changer()
    {
        return $this->belongsTo(\App\Models\User::class, 'changed_by');
    }

    public function getActionLabelAttribute(): string
    {
        return self::ACTIONS[$this->action][0] ?? ucfirst($this->action);
    }
}
