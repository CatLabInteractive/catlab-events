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

    protected static function booted()
    {
        // The admin form sends an empty sort as null. Such a photo goes after the others.
        static::saving(function (SeriesPhoto $photo) {
            if ($photo->sort === null) {
                $last = static::where('series_id', $photo->series_id)
                    ->where('id', '!=', $photo->id ?? 0)
                    ->max('sort');
                $photo->sort = $last === null ? 0 : $last + 1;
            }
        });
    }

    public function series()
    {
        return $this->belongsTo(Series::class);
    }

    public function photo()
    {
        return $this->belongsTo(Asset::class, 'photo_asset_id');
    }
}
