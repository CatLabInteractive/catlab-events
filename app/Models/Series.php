<?php

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
        return $this->events()
            ->upcoming()
            ->published()
            ->orderBy('startDate', 'asc')
            ->first();
    }

    /**
     * Get the event that will go out for sale in the near future (or is on sale already)
     */
    public function getNextSellingEvent()
    {
        $upcomingEvents = $this->events()
            ->upcoming()
            ->published()
            ->orderBy('startDate', 'asc')
            ->get();

        foreach ($upcomingEvents as $upcomingEvent) {
            /** @var Event $upcomingEvent */
            if ($upcomingEvent->isSelling() || !$upcomingEvent->isSoldOut()) {
                return $upcomingEvent;
            }
        }

        return $upcomingEvents->first();
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
