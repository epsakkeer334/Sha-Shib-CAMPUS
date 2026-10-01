<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\SoftDeletes;

class Institute extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'address',
        'city',
        'state_id',      // Changed from 'state' to 'state_id'
        'country_id',    // Changed from 'country' to 'country_id'
        'postal_code',
        'contact_person',
        'email',
        'phone',
        'website',
        'logo',
        'banner',
        'about',
        'status',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    protected $appends = [
        'logo_url',
        'banner_url',
        'formatted_created_at',
        'status_badge',
        'country_name',
        'state_name'
    ];

    /**
     * Get the country that owns the institute
     */
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    /**
     * Get the state that owns the institute
     */
    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    /**
     * Get country name from relationship
     */
    public function getCountryNameAttribute()
    {
        return $this->country ? $this->country->name : null;
    }

    /**
     * Get state name from relationship
     */
    public function getStateNameAttribute()
    {
        return $this->state ? $this->state->name : null;
    }

    /**
     * Get the logo URL
     */
    public function getLogoUrlAttribute()
    {
        if ($this->logo) {
            return asset('storage/institutes/logos/' . $this->logo);
        }
        return asset('admin/assets/img/default-institute.png');
    }

    /**
     * Get the banner URL
     */
    public function getBannerUrlAttribute()
    {
        if ($this->banner) {
            return asset('storage/institutes/banners/' . $this->banner);
        }
        return asset('admin/assets/img/default-banner.png');
    }

    /**
     * Get formatted created at
     */
    public function getFormattedCreatedAtAttribute()
    {
        return $this->created_at ? $this->created_at->format('d M Y') : '-';
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeAttribute()
    {
        return $this->status ? 'bg-success' : 'bg-danger';
    }

    /**
     * Get status text
     */
    public function getStatusTextAttribute()
    {
        return $this->status ? 'Active' : 'Inactive';
    }

    /**
     * Scope to get active institutes only
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope to search institutes
     */
    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('contact_person', 'like', "%{$search}%")
                  ->orWhereHas('country', function ($countryQuery) use ($search) {
                      $countryQuery->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('state', function ($stateQuery) use ($search) {
                      $stateQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }
        return $query;
    }

    /**
     * Scope to filter by country
     */
    public function scopeByCountry($query, $countryId)
    {
        if ($countryId) {
            return $query->where('country_id', $countryId);
        }
        return $query;
    }

    /**
     * Scope to filter by state
     */
    public function scopeByState($query, $stateId)
    {
        if ($stateId) {
            return $query->where('state_id', $stateId);
        }
        return $query;
    }

    /**
     * Scope to filter by city
     */
    public function scopeByCity($query, $city)
    {
        if ($city) {
            return $query->where('city', 'like', "%{$city}%");
        }
        return $query;
    }

    /**
     * Scope to get institutes with full address details
     */
    public function scopeWithAddressDetails($query)
    {
        return $query->with(['country', 'state']);
    }
}
