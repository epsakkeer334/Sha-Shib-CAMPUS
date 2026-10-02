<?php

namespace App\Models\Admin;

use App\Models\User;
use App\Traits\BelongsToInstitute;

/**
 * One gate of the dual gate: admin_doc_verification (Gate 1) or accounts_fee_verification (Gate 2).
 */
class EnrollmentApproval extends BaseModel
{
    use BelongsToInstitute;

    const DOCUMENTS = 'admin_doc_verification';
    const FEES = 'accounts_fee_verification';

    protected $fillable = ['institute_id', 'student_id', 'gate', 'status', 'approved_by', 'approved_at', 'remarks', 'created_by', 'updated_by'];

    protected $casts = ['approved_at' => 'datetime'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getLabelAttribute()
    {
        return config("camp.enrollment_gates.{$this->gate}.0", $this->gate);
    }
}
