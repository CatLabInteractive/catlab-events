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
use Illuminate\Support\Str;

/**
 * Class LiveStream
 * @package App\Models
 */
class LiveStream extends Model
{
    protected $table = 'livestreams';

    public static function boot()
    {
        parent::boot();

        self::creating(function(LiveStream $model) {

            $tries = 10;
            while ($tries > 0) {
                $tries --;

                $model->token = Str::lower(Str::random(8));

                // check for duplicate
                if (!LiveStream::where('token', '=', $model->token)->exists()) {
                    return;
                }

                throw new \LogicException('Failed generating a unique key.');
            }

        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @param array $additionalParameters
     * @return string
     */
    public function getLivestreamUrl($additionalParameters = [])
    {
        $organisation = $this->organisation;

        if (count($organisation->domains) > 0) {
            $domain = $organisation->domains->first()->domain;
        } else {
            $domain = \Request::getHttpHost();
            if (Str::startsWith($domain, 'live.')) {
                $domain = 'live.' . $domain;
            }
        }

        $baseUrl = (\Request::secure() ? 'https' : 'http' ) . '://live.' . $domain . '/' . $this->token;

        if (count($additionalParameters) > 0) {
            $baseUrl .= '?' . http_build_query($additionalParameters);
        }

        return $baseUrl;
    }

    /**
     * @return mixed
     */
    public function getAttribute($key)
    {
        if ($key === 'name') {
            return $this->title;
        }

        return parent::getAttribute($key);
    }
}
