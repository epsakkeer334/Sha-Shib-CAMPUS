<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

/** One semester covered by a scholarship. */
class ScholarshipPeriod extends Model
{
    public $timestamps = false;

    protected $fillable = ['scholarship_id', 'period_no'];
}
