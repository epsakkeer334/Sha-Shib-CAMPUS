<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\IsMasterData;

class State extends Model
{
    use SoftDeletes, IsMasterData;

    protected $fillable = [
        'country_id',
        'name',
        'code',
        'status'
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Get the country that owns the state.
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function institutes()
    {
        return $this->hasMany(Institute::class, 'state_id');
    }

    protected function usageRelations(): array
    {
        return ['institutes', 'students'];
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }
}
