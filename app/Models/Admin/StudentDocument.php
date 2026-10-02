<?php

namespace App\Models\Admin;

use App\Traits\BelongsToInstitute;

/**
 * KYC document of a student. Files live on the private "local" disk and are only
 * served through the authorized download route.
 */
class StudentDocument extends BaseModel
{
    use BelongsToInstitute;

    protected $fillable = [
        'student_id', 'institute_id', 'document_type', 'file_path', 'original_name', 'mime_type', 'size',
        'uploaded_at', 'verified_by', 'verified_at', 'verification_status', 'remarks', 'previous_rejection', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'verified_at' => 'datetime',
        'size' => 'integer',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function verifier()
    {
        return $this->belongsTo(\App\Models\User::class, 'verified_by');
    }

    public function getTypeLabelAttribute()
    {
        return config("camp.student_document_types.{$this->document_type}.0", $this->document_type);
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function getSizeLabelAttribute(): string
    {
        return $this->size >= 1048576
            ? round($this->size / 1048576, 1) . ' MB'
            : max(1, round($this->size / 1024)) . ' KB';
    }
}
