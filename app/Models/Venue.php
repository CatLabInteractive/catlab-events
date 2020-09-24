<?php
/**
 * CatLab Events - Event ticketing system
 * Copyright (C) 2017 Thijs Van der Schaeghe
 * CatLab Interactive bvba, Gent, Belgium
 * http://www.catlab.eu/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

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
