<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

/**
 * Module 2.10 — an add-on program chosen by a student. A paid add-on links to the due it created.
 */
class StudentAddonProgram extends Model
{
    protected $fillable = ['student_id', 'addon_program_id', 'student_due_id', 'selected_at', 'selected_by'];

    protected $casts = ['selected_at' => 'datetime'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function program()
    {
        return $this->belongsTo(AddonProgram::class, 'addon_program_id')->withoutGlobalScopes()->withTrashed();
    }

    public function due()
    {
        return $this->belongsTo(StudentDue::class, 'student_due_id');
    }
}
