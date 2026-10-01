<?php

namespace App\Traits;

use App\Models\Admin\Institute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * For tenant-scoped models (table has institute_id):
 *  - every query is limited to the logged-in user's institute (Super Admin sees all)
 *  - institute_id is filled automatically on create for institute users
 *
 * Do NOT use on the User model (the scope reads Auth::user(), which loads a User).
 */
trait BelongsToInstitute
{
    public static function bootBelongsToInstitute(): void
    {
        static::addGlobalScope('institute', function (Builder $query) {
            $user = Auth::user();

            if ($user && !$user->isSuperAdmin()) {
                $query->where($query->getModel()->qualifyColumn('institute_id'), $user->institute_id);
            }
        });

        static::creating(function ($model) {
            $user = Auth::user();

            if ($user && !$user->isSuperAdmin() && empty($model->institute_id)) {
                $model->institute_id = $user->institute_id;
            }
        });
    }

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }
}
