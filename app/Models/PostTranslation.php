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

use Carbon\Carbon;
use CatLab\Charon\Laravel\Database\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * One language version of a Post. `body` is sanitised HTML; `author` is a
 * free-text byline (the organisation name is shown when it is empty).
 *
 * @property int $id
 * @property int $post_id
 * @property int $organisation_id
 * @property string $locale
 * @property string $slug
 * @property string $title
 * @property string|null $excerpt
 * @property string|null $author
 * @property string $body
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property bool $is_published
 * @property Post $post
 *
 * Class PostTranslation
 * @package App\Models
 */
class PostTranslation extends Model
{
    const URL_TIMEZONE = 'Europe/Brussels';

    /**
     * @var string[]
     */
    protected $casts = [
        'is_published' => 'bool',
    ];

    /**
     *
     */
    protected static function booted()
    {
        static::saving(function (PostTranslation $translation) {
            if ($translation->post) {
                $translation->organisation_id = $translation->post->organisation_id;
            }
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Translations that are published (the post's own date is checked by
     * Post::published()).
     * @param Builder $query
     * @return Builder
     */
    public function scopePublished(Builder $query)
    {
        return $query->where('post_translations.is_published', '=', true);
    }

    /**
     * Absolute URL: /{locale/}YYYY/MM/DD/slug, date in Europe/Brussels.
     * @return string
     */
    public function getUrl(): string
    {
        $prefix = $this->locale === config('cms.default_locale') ? '' : '/' . $this->locale;

        $date = $this->post && $this->post->published_at ? $this->post->published_at : Carbon::now();
        $date = $date->copy()->setTimezone(self::URL_TIMEZONE)->format('Y/m/d');

        return url($prefix . '/' . $date . '/' . $this->slug);
    }
}
