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
use App\Models\PageTranslation;
use Illuminate\Support\Facades\Cache;

/**
 * The CMS pages in the site menu: top-level pages marked show_in_menu with
 * a published translation in the locale, by sort_order, each with its
 * subpages that are marked show_in_menu too (shown as a dropdown). The home page is
 * left out (the logo links there). Cached per organisation and locale, and
 * forgotten whenever a page or page translation of the organisation changes.
 *
 * Class Menu
 * @package App\Cms
 */
class Menu
{
    const CACHE_TTL = 300;

    /**
     * @param Organisation $organisation
     * @param string|null $locale
     * @return array list of [ title, url, children => list of [ title, url ] ]
     */
    public function items(Organisation $organisation, ?string $locale = null): array
    {
        $locale = in_array($locale, config('cms.locales'), true) ? $locale : config('cms.default_locale');

        return Cache::remember(self::cacheKey($organisation->id, $locale), self::CACHE_TTL, function () use ($organisation, $locale) {
            $translations = PageTranslation::query()
                ->select('page_translations.*', 'pages.parent_id as menu_parent_id')
                ->join('pages', 'pages.id', '=', 'page_translations.page_id')
                ->where('page_translations.organisation_id', '=', $organisation->id)
                ->where('pages.organisation_id', '=', $organisation->id)
                ->where('page_translations.locale', '=', $locale)
                ->where('page_translations.is_published', '=', true)
                ->where('pages.show_in_menu', '=', true)
                ->whereNull('pages.deleted_at')
                // Read from the table: the organisation model may be an older copy.
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('organisations')
                        ->whereColumn('organisations.home_page_id', 'pages.id');
                })
                ->orderBy('pages.sort_order')
                ->orderBy('pages.id')
                ->get();

            // Top-level pages, each with its menu subpages (one level).
            $children = [];
            foreach ($translations as $translation) {
                if ($translation->menu_parent_id !== null) {
                    $children[(int) $translation->menu_parent_id][] = [ 'title' => $translation->title, 'url' => $translation->getUrl() ];
                }
            }

            return $translations
                ->filter(function (PageTranslation $translation) {
                    return $translation->menu_parent_id === null;
                })
                ->map(function (PageTranslation $translation) use ($children) {
                    return [
                        'title' => $translation->title,
                        'url' => $translation->getUrl(),
                        'children' => $children[(int) $translation->page_id] ?? [],
                    ];
                })
                ->values()
                ->all();
        });
    }

    /**
     * @param int $organisationId
     */
    public function forget(int $organisationId): void
    {
        foreach (config('cms.locales') as $locale) {
            Cache::forget(self::cacheKey($organisationId, $locale));
        }
    }

    /**
     * @param int $organisationId
     * @param string $locale
     * @return string
     */
    public static function cacheKey(int $organisationId, string $locale): string
    {
        return 'cms:menu:' . $organisationId . ':' . $locale;
    }
}
