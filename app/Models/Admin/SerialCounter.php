<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class SerialCounter extends Model
{
    protected $fillable = [
        'series_key',
        'institute_id',
        'year',
        'last_value',
        'prefix_format',
        'pad_length',
    ];

    protected $casts = [
        'year' => 'integer',
        'last_value' => 'integer',
        'pad_length' => 'integer',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }
}
