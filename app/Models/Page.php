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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A CMS page. Holds identity and hierarchy only; everything that depends on
 * the language lives on PageTranslation.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int|null $parent_id
 * @property int $sort_order
 * @property bool $show_in_menu
 * @property bool $show_in_footer
 * @property string|null $link a menu link instead of content: @series, @calendar, @blog or a URL
 * @property int|null $wp_post_id
 *
 * Class Page
 * @package App\Models
 */
class Page extends Model
{
    use SoftDeletes;

    /**
     * Menu links to the site's fixed parts (Page::$link).
     */
    const LINK_SERIES = '@series';
    const LINK_CALENDAR = '@calendar';
    const LINK_BLOG = '@blog';

    const FIXED_LINKS = [ self::LINK_SERIES, self::LINK_CALENDAR, self::LINK_BLOG ];

    /**
     * Where the fixed parts sit in the menu as long as no link page manages
     * them (as a sort_order).
     */
    const FIXED_LINK_POSITIONS = [ self::LINK_SERIES => -20, self::LINK_CALENDAR => -10, self::LINK_BLOG => 1000 ];

    /**
     * Default titles of the fixed links, per locale.
     */
    const FIXED_LINK_TITLES = [
        self::LINK_SERIES => [ 'nl' => 'Quizreeksen', 'en' => 'Quiz series', 'fr' => 'Séries de quiz' ],
        self::LINK_CALENDAR => [ 'nl' => 'Kalender', 'en' => 'Calendar', 'fr' => 'Calendrier' ],
        self::LINK_BLOG => [ 'nl' => 'Blog', 'en' => 'Blog', 'fr' => 'Blog' ],
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'sort_order' => 'int',
        'show_in_menu' => 'bool',
        'show_in_footer' => 'bool',
    ];

    /**
     * @return bool whether the page is a menu link instead of content
     */
    public function isLink(): bool
    {
        return is_string($this->link) && $this->link !== '';
    }

    /**
     * The URL a link page points to; null for @series (several links, see
     * the menu) and for content pages.
     * @param string|null $blogUrl
     * @return string|null
     */
    public static function linkUrl(?string $link, ?string $blogUrl = null): ?string
    {
        switch ($link) {
            case null:
            case '':
            case self::LINK_SERIES:
                return null;
            case self::LINK_CALENDAR:
                return action('EventController@calendar');
            case self::LINK_BLOG:
                return $blogUrl;
            default:
                return $link;
        }
    }

    /**
     * Keep materialised paths and the organisation's home page in sync.
     */
    protected static function booted()
    {
        static::saved(function (Page $page) {
            if ($page->wasChanged('parent_id')) {
                $page->rebuildTranslationPaths();
            }
        });

        // The cached site menu must see the change.
        $forgetMenu = function (Page $page) {
            if ($page->organisation_id) {
                app(\App\Cms\Menu::class)->forget((int) $page->organisation_id);
            }
        };
        static::saved($forgetMenu);
        static::deleted($forgetMenu);

        // Pages are soft deleted, so the FK's ON DELETE SET NULL never fires.
        static::deleted(function (Page $page) {
            Organisation::where('home_page_id', '=', $page->id)
                ->update([ 'home_page_id' => null ]);
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
    public function parent()
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(Page::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function translations()
    {
        return $this->hasMany(PageTranslation::class);
    }

    /**
     * The translation in $locale, or null when the page does not exist in
     * that language. Uses the loaded relation when there is one.
     * @param string $locale
     * @return PageTranslation|null
     */
    public function translation(string $locale): ?PageTranslation
    {
        if ($this->relationLoaded('translations')) {
            return $this->translations->firstWhere('locale', $locale);
        }

        return $this->translations()->where('locale', '=', $locale)->first();
    }

    /**
     * @param Builder $query
     * @param Organisation $organisation
     * @return Builder
     */
    public function scopeForOrganisation(Builder $query, Organisation $organisation)
    {
        return $query->where('pages.organisation_id', '=', $organisation->id);
    }

    /**
     * Re-save every translation so its path follows a new parent (the
     * translations cascade to their children themselves).
     */
    public function rebuildTranslationPaths()
    {
        $this->unsetRelation('parent');

        foreach ($this->translations()->get() as $translation) {
            $translation->setRelation('page', $this);
            $translation->save();
        }

        $this->unsetRelation('translations');
    }
}
