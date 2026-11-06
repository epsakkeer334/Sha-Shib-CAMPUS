<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-institute email details: the sender name students see, the reply-to address and a signature
 * added under every email of the institute. The sender address itself is fixed (SMTP2GO verified domain).
 */
class InstituteEmailSetting extends Model
{
    protected $fillable = ['institute_id', 'sender_name', 'reply_to', 'signature', 'updated_by'];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }
}
