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

use CatLab\CentralStorage\Client\Models\Asset;
use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Competition
 * @package App\Models
 */
class Series extends Model
{
    use SoftDeletes;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * @var Event
     */
    private $nextEvent = false; // false means it is not loaded yet

    /**
     * @return Series|Model|null
     */
    public static function getRandom()
    {
        return self::orderBy(DB::raw('RAND()'))->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function events()
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeActive($query)
    {
        return $query->where('active', '=', 1);
    }

    public function getUrl(Event $focusEvent = null)
    {
        $parameters = [ $this->id, $this->slug ];
        if ($focusEvent) {
            $parameters['event'] = $focusEvent->id;
        }

        return action('SeriesController@view', $parameters);
    }

    /**
     * @return Event|null
     */
    public function getNextEvent()
    {
        if ($this->nextEvent === false) {
            $this->nextEvent = $this->events()
                ->upcoming()
                ->published()
                ->orderByStartDate()
                ->first();;
        }

        return $this->nextEvent;
    }

    /**
     * @return bool
     */
    public function hasNextEvent()
    {
        return $this->getNextEvent() !== null;
    }

    /**
     * Get the event that will go out for sale in the near future (or is on sale already)
     */
    public function getNextSellingEvent()
    {
        $upcomingEvents = $this->events()
            ->upcoming()
            ->published()
            ->orderByStartDate()
            ->get();

        foreach ($upcomingEvents as $upcomingEvent) {
            /** @var Event $upcomingEvent */
            if ($upcomingEvent->isSelling() || !$upcomingEvent->isSoldOut()) {
                return $upcomingEvent;
            }
        }

        return $upcomingEvents->first();
    }

    /**
     * @return int
     */
    public function countUpcomingEvents()
    {
        return $this->events()
            ->upcoming()
            ->published()
            ->count();
    }

    public function youtubeThumbnail()
    {
        return $this->belongsTo(Asset::class, 'youtube_thumbnail_asset_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function header()
    {
        return $this->belongsTo(Asset::class, 'header_asset_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function logo()
    {
        return $this->belongsTo(Asset::class, 'logo_asset_id');
    }

    /**
     * @param array $options
     * @return string|null
     */
    public function getImage($options = [])
    {
        if ($this->logo) {
            return $this->logo->getUrl($options);
        }

        if ($this->header) {
            return $this->header->getUrl($options);
        }

        return null;
    }

    /**
     *
     */
    public function hasVideo()
    {
        return isset($this->youtube_url);
    }

    /**
     * @return string
     */
    public function getVideoUrl()
    {
        return $this->youtube_url;
    }

    /**
     * @param int $width
     * @return string
     */
    public function getVideoThumbnail($width = 555)
    {
        /*
        if ($this->hasVideo()) {
            $videoId = explode('/', $this->youtube_url);
            $videoId = $videoId[count($videoId) - 1];

            return 'https://img.youtube.com/vi/' . $videoId . '/0.jpg';
        }
        */
        if ($this->youtubeThumbnail) {
            return $this->youtubeThumbnail->getUrl([ 'width' => $width ]);
        }

        return '/images/video.png';
    }

    /**
     * @return bool
     */
    public function hasFaq()
    {
        // Look for id="faq" in description
        return strpos($this->description, 'id="faq"') !== false;
    }

    /**
     * @return array
     */
    public function getJsonLD()
    {
        return [
            '@type' => 'EventSeries',
            'name' => $this->name,
            'url' => $this->getUrl(),
            'image' => $this->getImage([ 'width' => 300, 'height' => 300 ]),
            'organizer' => $this->organisation->getJsonLD()
        ];
    }
}
