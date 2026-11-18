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

use App\Cms\Blog;
use App\Cms\HtmlSanitizer;
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

            if ($translation->body === null) {
                $translation->body = '';
            }

            // Defence in depth: App\Cms\PostWriter sanitises the body, but
            // whatever the write path, it is never stored unsanitised.
            if ($translation->isDirty('body')) {
                $translation->body = app(HtmlSanitizer::class)->sanitize($translation->body);
            }
        });

        // The cached post lists (latest_posts, navigation) must see the change.
        $forget = function (PostTranslation $translation) {
            if ($translation->organisation_id) {
                app(Blog::class)->forgetCaches((int) $translation->organisation_id);
            }
        };
        static::saved($forget);
        static::deleted($forget);
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
