<?php

namespace App\Models;

use CatLab\CentralStorage\Client\Models\Asset;
use Illuminate\Database\Eloquent\Model;

/**
 * A photo in the hero slideshow of a series.
 *
 * @property int $id
 * @property int $sort
 * @property Series $series
 * @property Asset|null $photo
 */
class SeriesPhoto extends Model
{
    protected $table = 'series_photos';

    protected $fillable = [ 'sort' ];

    public function series()
    {
        return $this->belongsTo(Series::class);
    }

    public function photo()
    {
        return $this->belongsTo(Asset::class, 'photo_asset_id');
    }
}
