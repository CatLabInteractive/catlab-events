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

namespace App\Cms;

use App\Models\Organisation;
use App\Models\PostTranslation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Read side of the blog: the visible posts of an organisation in a locale,
 * and the small cached summaries the latest_posts block, the series page's
 * blog block and the navigation use.
 *
 * A post is visible in a locale when posts.published_at has passed and the
 * translation in that locale is published.
 *
 * Class Blog
 * @package App\Cms
 */
class Blog
{
    /**
     * How long the summaries are cached (they are also forgotten whenever a
     * post or post translation of the organisation is saved or deleted).
     */
    const CACHE_TTL = 300;

    /**
     * Most posts any summary list shows (latest_posts allows 1-6).
     */
    const LATEST_MAX = 6;

    /**
     * Visible translations of $organisation in $locale, newest first.
     * @param Organisation $organisation
     * @param string $locale
     * @return Builder
     */
    public function visible(Organisation $organisation, string $locale): Builder
    {
        return PostTranslation::query()
            ->select('post_translations.*')
            ->join('posts', 'posts.id', '=', 'post_translations.post_id')
            ->where('post_translations.organisation_id', '=', $organisation->id)
            ->where('posts.organisation_id', '=', $organisation->id)
            ->where('post_translations.locale', '=', $locale)
            ->where('post_translations.is_published', '=', true)
            ->whereNull('posts.deleted_at')
            ->whereNotNull('posts.published_at')
            ->where('posts.published_at', '<=', Carbon::now())
            ->orderBy('posts.published_at', 'desc')
            ->orderBy('posts.id', 'desc')
            ->with('post.featuredImage');
    }

    /**
     * Up to $limit of the newest visible posts, as plain arrays (cached):
     * title, url, excerpt, author (byline), published_at, image (url|null).
     * @param Organisation $organisation
     * @param string $locale
     * @param int $limit
     * @return array[]
     */
    public function latest(Organisation $organisation, string $locale, int $limit = 3): array
    {
        $posts = Cache::remember(
            $this->latestCacheKey($organisation, $locale),
            self::CACHE_TTL,
            function () use ($organisation, $locale) {
                return $this->visible($organisation, $locale)
                    ->limit(self::LATEST_MAX)
                    ->get()
                    ->map(function (PostTranslation $translation) use ($organisation) {
                        return $this->summary($translation, $organisation);
                    })
                    ->all();
            }
        );

        return array_slice($posts, 0, max(0, $limit));
    }

    /**
     * Does $organisation have a visible post in $locale? (cached)
     * @param Organisation $organisation
     * @param string $locale
     * @return bool
     */
    public function hasPosts(Organisation $organisation, string $locale): bool
    {
        return count($this->latest($organisation, $locale, 1)) > 0;
    }

    /**
     * Where the "Blog" item of the navigation points: the local blog index
     * of $locale when that locale has posts, else the default locale's when
     * that one has, else null (the caller falls back to organisations.blog_url).
     * @param Organisation $organisation
     * @param string|null $locale
     * @return string|null
     */
    public function navigationUrl(Organisation $organisation, ?string $locale = null): ?string
    {
        $default = config('cms.default_locale');
        $locale = in_array($locale, config('cms.locales'), true) ? $locale : $default;

        foreach (array_unique([ $locale, $default ]) as $candidate) {
            if ($this->hasPosts($organisation, $candidate)) {
                return self::indexUrl($candidate);
            }
        }

        return null;
    }

    /**
     * Absolute URL of the blog index in $locale.
     * @param string $locale
     * @return string
     */
    public static function indexUrl(string $locale): string
    {
        return url(($locale === config('cms.default_locale') ? '' : '/' . $locale) . '/blog');
    }

    /**
     * The byline: the translation's author, else the organisation's name.
     * @param PostTranslation $translation
     * @param Organisation|null $organisation
     * @return string
     */
    public static function byline(PostTranslation $translation, ?Organisation $organisation = null): string
    {
        if ($translation->author !== null && trim($translation->author) !== '') {
            return $translation->author;
        }

        $organisation = $organisation ?: $translation->organisation;

        return $organisation ? (string) $organisation->name : '';
    }

    /**
     * @param PostTranslation $translation
     * @param Organisation $organisation
     * @return array
     */
    public function summary(PostTranslation $translation, Organisation $organisation): array
    {
        $post = $translation->post;
        $image = $post ? $post->featuredImage : null;

        return [
            'title' => $translation->title,
            'url' => $translation->getUrl(),
            'excerpt' => $translation->excerpt,
            'author' => self::byline($translation, $organisation),
            'published_at' => $post && $post->published_at
                ? $post->published_at->copy()->setTimezone(PostTranslation::URL_TIMEZONE)
                : null,
            'image' => $image ? $image->getUrl([ 'width' => 720 ]) : null,
        ];
    }

    /**
     * Forget every cached summary of $organisation.
     * @param int $organisationId
     */
    public function forgetCaches(int $organisationId): void
    {
        foreach (config('cms.locales') as $locale) {
            Cache::forget(self::latestCacheKeyFor($organisationId, $locale));
        }
    }

    /**
     * @param Organisation $organisation
     * @param string $locale
     * @return string
     */
    public function latestCacheKey(Organisation $organisation, string $locale): string
    {
        return self::latestCacheKeyFor($organisation->id, $locale);
    }

    /**
     * @param int $organisationId
     * @param string $locale
     * @return string
     */
    protected static function latestCacheKeyFor(int $organisationId, string $locale): string
    {
        return 'cms.latest_posts:' . $organisationId . ':' . $locale;
    }
}
