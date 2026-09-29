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
use CatLab\CentralStorage\Client\Models\Asset;
use CatLab\Charon\Laravel\Database\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * One language version of a Page: its own slug, materialised path, title,
 * blocks (JSON) and SEO meta. The organisation is copied from the page so the
 * path can be unique per organisation and locale.
 *
 * @property int $id
 * @property int $page_id
 * @property int $organisation_id
 * @property string $locale
 * @property string $slug
 * @property string $path
 * @property string $title
 * @property array $blocks
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property int|null $og_image_id
 * @property bool $is_published
 * @property Carbon|null $published_at
 * @property Page $page
 *
 * Class PageTranslation
 * @package App\Models
 */
class PageTranslation extends Model
{
    /**
     * @var string[]
     */
    protected $casts = [
        'blocks' => 'array',
        'is_published' => 'bool',
        'published_at' => 'datetime',
    ];

    /**
     * Set while saving: the children's paths need rebuilding afterwards.
     * @var bool
     */
    protected $pathNeedsCascade = false;

    /**
     *
     */
    protected static function booted()
    {
        static::saving(function (PageTranslation $translation) {

            $page = $translation->page;
            if ($page) {
                $translation->organisation_id = $page->organisation_id;
                $translation->rebuildPath();
            }

            if ($translation->blocks === null) {
                $translation->blocks = [];
            }

            if ($translation->is_published && !$translation->published_at) {
                $translation->published_at = Carbon::now();
            }

            $translation->pathNeedsCascade = !$translation->exists || $translation->isDirty('path');
        });

        static::saved(function (PageTranslation $translation) {
            if ($translation->pathNeedsCascade) {
                $translation->pathNeedsCascade = false;
                $translation->rebuildChildPaths();
            }
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function page()
    {
        return $this->belongsTo(Page::class);
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
    public function ogImage()
    {
        return $this->belongsTo(Asset::class, 'og_image_id');
    }

    /**
     * @param Builder $query
     * @return Builder
     */
    public function scopePublished(Builder $query)
    {
        return $query->where('page_translations.is_published', '=', true);
    }

    /**
     * @param Builder $query
     * @param Organisation $organisation
     * @return Builder
     */
    public function scopeForOrganisation(Builder $query, Organisation $organisation)
    {
        return $query->where('page_translations.organisation_id', '=', $organisation->id);
    }

    /**
     * parent path + '/' + slug, using the parent's translation in the same
     * locale (just the slug when there is no parent, or the parent does not
     * exist in this locale).
     */
    public function rebuildPath(): void
    {
        $parentPage = $this->page ? $this->page->parent : null;
        $parent = $parentPage ? $parentPage->translation($this->locale) : null;

        $this->path = trim(($parent ? $parent->path . '/' : '') . $this->slug, '/');
    }

    /**
     * Absolute, locale-prefixed URL without trailing slash.
     * @return string
     */
    public function getUrl(): string
    {
        $prefix = $this->locale === config('cms.default_locale') ? '' : '/' . $this->locale;
        return rtrim(url($prefix . '/' . $this->path), '/');
    }

    /**
     * Re-save the same-locale translation of every child page; each of those
     * cascades further through its own saved hook.
     */
    protected function rebuildChildPaths()
    {
        $page = $this->page;
        if (!$page || !$page->exists) {
            return;
        }

        // Children must see this translation's new path.
        $page->unsetRelation('translations');

        foreach ($page->children()->get() as $child) {
            $child->setRelation('parent', $page);

            /** @var PageTranslation $childTranslation */
            $childTranslation = $child->translations()->where('locale', '=', $this->locale)->first();
            if ($childTranslation) {
                $childTranslation->setRelation('page', $child);
                $childTranslation->save();
            }
        }
    }
}
