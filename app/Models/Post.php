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
use Carbon\Carbon;
use CatLab\CentralStorage\Client\Models\Asset;
use CatLab\Charon\Laravel\Database\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A blog post. The publication date is shared by every translation and
 * drives the /YYYY/MM/DD/slug URL.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int|null $featured_image_id
 * @property Carbon|null $published_at
 * @property int|null $wp_post_id
 *
 * Class Post
 * @package App\Models
 */
class Post extends Model
{
    use SoftDeletes;

    /**
     * @var string[]
     */
    protected $casts = [
        'published_at' => 'datetime',
    ];

    /**
     *
     */
    protected static function booted()
    {
        // The cached post lists (latest_posts, navigation) must see the change.
        $forget = function (Post $post) {
            if ($post->organisation_id) {
                app(Blog::class)->forgetCaches((int) $post->organisation_id);
            }
        };
        static::saved($forget);
        static::deleted($forget);
        static::restored($forget);
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
    public function featuredImage()
    {
        return $this->belongsTo(Asset::class, 'featured_image_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function translations()
    {
        return $this->hasMany(PostTranslation::class);
    }

    /**
     * @param string $locale
     * @return PostTranslation|null
     */
    public function translation(string $locale): ?PostTranslation
    {
        if ($this->relationLoaded('translations')) {
            return $this->translations->firstWhere('locale', $locale);
        }

        return $this->translations()->where('locale', '=', $locale)->first();
    }

    /**
     * Posts whose publication date has passed.
     * @param Builder $query
     * @return Builder
     */
    public function scopePublished(Builder $query)
    {
        return $query
            ->whereNotNull('posts.published_at')
            ->where('posts.published_at', '<=', Carbon::now());
    }

    /**
     * @param Builder $query
     * @param Organisation $organisation
     * @return Builder
     */
    public function scopeForOrganisation(Builder $query, Organisation $organisation)
    {
        return $query->where('posts.organisation_id', '=', $organisation->id);
    }
}
