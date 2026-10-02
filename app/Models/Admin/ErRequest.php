<?php

namespace App\Models\Admin;

use App\Models\User;
use App\Traits\BelongsToInstitute;

/**
 * ER request form of a student: generated → printed → physically signed by the TM → archived.
 */
class ErRequest extends BaseModel
{
    use BelongsToInstitute;

    protected $fillable = [
        'institute_id', 'student_id', 'request_form_path', 'generated_at', 'printed_at',
        'tm_signature_status', 'tm_signed_by', 'tm_signed_at', 'archived_at', 'archived_by', 'status',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'printed_at' => 'datetime',
        'tm_signed_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function signer()
    {
        return $this->belongsTo(User::class, 'tm_signed_by');
    }

    public function archiver()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }
}
