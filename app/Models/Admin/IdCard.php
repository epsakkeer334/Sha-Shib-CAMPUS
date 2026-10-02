<?php

namespace App\Models\Admin;

use App\Models\User;
use App\Traits\BelongsToInstitute;

/**
 * Student ID card. Valid only after the Training Manager signs it by hand (tm_signature_status).
 */
class IdCard extends BaseModel
{
    use BelongsToInstitute;

    protected $fillable = [
        'institute_id', 'student_id', 'issue_date', 'tm_signature_status', 'signed_by', 'file_path', 'print_count', 'status',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'print_count' => 'integer',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function signer()
    {
        return $this->belongsTo(User::class, 'signed_by');
    }
}
