<?php

namespace App\Models\Admin;

use App\Traits\BelongsToInstitute;
use Illuminate\Database\Eloquent\Model;

/**
 * A bulk student upload (Module 2 step 2.9): the validated preview (rows, changes, errors) and, once
 * applied, the result. Institute users see their own institute's imports only.
 */
class StudentImport extends Model
{
    use BelongsToInstitute;

    protected $fillable = [
        'institute_id', 'user_id', 'file_name', 'file_path', 'status',
        'rows_total', 'rows_new', 'rows_updated', 'rows_unchanged', 'rows_failed', 'preview', 'result', 'applied_at',
    ];

    protected $casts = [
        'preview' => 'array',
        'result' => 'array',
        'applied_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /** Previews can be applied for 24 hours. */
    public function getExpiredAttribute(): bool
    {
        return $this->status === 'previewed' && $this->created_at && $this->created_at->lt(now()->subDay());
    }
}
