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
 * @property int|null $wp_post_id
 *
 * Class Page
 * @package App\Models
 */
class Page extends Model
{
    use SoftDeletes;

    /**
     * @var string[]
     */
    protected $casts = [
        'sort_order' => 'int',
        'show_in_menu' => 'bool',
    ];

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
