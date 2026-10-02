<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\IsMasterData;

class Country extends Model
{
    use SoftDeletes, IsMasterData;

    protected $fillable = [
        'name',
        'code',
        'phone_code',
        'status'
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Get the states for the country.
     */
    public function states()
    {
        return $this->hasMany(State::class);
    }

    public function institutes()
    {
        return $this->hasMany(Institute::class, 'country_id');
    }

    protected function usageRelations(): array
    {
        return ['states', 'institutes', 'students'];
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }
}
