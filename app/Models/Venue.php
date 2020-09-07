<?php

namespace App\Models;

use CatLab\Charon\Laravel\Database\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Venue
 * @package App\Models
 */
class Venue extends Model
{
    use SoftDeletes;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array
     */
    public function getGeo()
    {
        return [ $this->lat, $this->long ];
    }

    /**
     * @return string
     */
    public function getShortLocation()
    {
        return $this->name . ', ' . $this->city;
    }

    /**
     * @param string $separator
     * @return string
     */
    public function getAddressFull($separator = ', ')
    {
        return $this->name . $separator . $this->address . $separator . $this->city . $separator . $this->country;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function events()
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @return string
     */
    public function getLocalUrl()
    {
        return action('EventController@fromVenue', $this->id);
    }

    /**
     * @return array
     */
    public function getJsonLD()
    {
        $out = [
            "@type" => "Place",
            "name" => $this->name,
            "address" => [
                "@type" => "PostalAddress",
                "streetAddress" => $this->address,
                "addressLocality" => $this->city,
                "postalCode" => $this->postalCode,
                "addressCountry" => $this->country
            ]
        ];

        if (isset($this->url)) {
            $out['url'] = $this->url;
            $out['sameAs'] = $this->url;
        }

        if (isset($this->lat) && isset($this->long)) {
            $out['geo'] = [
                '@type' => 'GeoCoordinates',
                'address' => $this->getAddressFull(),
                'addressCountry' => $this->country,
                'latitude' => $this->lat,
                'longitude' => $this->long,
                'postalCode' => $this->postalCode
            ];
        }

        return $out;
    }
}