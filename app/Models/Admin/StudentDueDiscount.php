<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

/** The discount one scholarship assignment gives on one due (Module 2C). */
class StudentDueDiscount extends Model
{
    protected $fillable = ['student_due_id', 'student_scholarship_id', 'amount'];

    protected $casts = ['amount' => 'decimal:2'];

    public function due()
    {
        return $this->belongsTo(StudentDue::class, 'student_due_id');
    }

    public function assignment()
    {
        return $this->belongsTo(StudentScholarship::class, 'student_scholarship_id');
    }
}
