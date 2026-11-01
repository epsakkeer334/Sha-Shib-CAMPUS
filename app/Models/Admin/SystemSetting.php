<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

/**
 * One system setting (key → value). Read through App\Support\Settings, which caches them and
 * applies the defaults. Does not extend BaseModel: settings are global and never soft-deleted.
 */
class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'updated_by'];
}
