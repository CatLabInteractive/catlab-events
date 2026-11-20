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
use App\Models\Page;
use App\Models\PageTranslation;
use App\Services\HostIndependentCache;
use Illuminate\Support\Facades\Cache;

/**
 * The site menu: pages marked show_in_menu with a published translation in
 * the locale, by sort_order, each with its subpages that are marked
 * show_in_menu too (shown as a dropdown). A page can be a link (to the quiz
 * series, the calendar, the blog or any URL) instead of content, so the
 * whole menu is managed as pages; fixed parts without such a page keep a
 * default position. The home page is
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
     * The menu pages: top-level pages with their subpages (one level). A
     * link page (Page::$link) carries its link; its url is resolved by
     * entries().
     * @param Organisation $organisation
     * @param string|null $locale
     * @return array list of [ title, url, link, sort_order, children => list of [ title, url, link ] ]
     */
    public function items(Organisation $organisation, ?string $locale = null): array
    {
        $locale = in_array($locale, config('cms.locales'), true) ? $locale : config('cms.default_locale');

        return HostIndependentCache::remember(self::cacheKey($organisation->id, $locale), self::CACHE_TTL, function () use ($organisation, $locale) {
            $translations = PageTranslation::query()
                ->select('page_translations.*', 'pages.parent_id as menu_parent_id', 'pages.sort_order as menu_sort_order', 'pages.link as menu_link')
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

            $item = function (PageTranslation $translation) {
                $link = $translation->menu_link ?: null;
                return [
                    'title' => $translation->title,
                    'url' => $link === null ? $translation->getUrl() : null,
                    'link' => $link,
                ];
            };

            $children = [];
            foreach ($translations as $translation) {
                if ($translation->menu_parent_id !== null) {
                    $children[(int) $translation->menu_parent_id][] = $item($translation);
                }
            }

            return $translations
                ->filter(function (PageTranslation $translation) {
                    return $translation->menu_parent_id === null;
                })
                ->map(function (PageTranslation $translation) use ($children, $item) {
                    return $item($translation) + [
                        'sort_order' => (int) $translation->menu_sort_order,
                        'children' => $children[(int) $translation->page_id] ?? [],
                    ];
                })
                ->values()
                ->all();
        });
    }

    /**
     * The fixed links (Page::FIXED_LINKS) that a page of the organisation
     * manages, whether it is in the menu or not.
     * @param Organisation $organisation
     * @return string[]
     */
    public function managedLinks(Organisation $organisation): array
    {
        return Cache::remember('cms:menu:' . $organisation->id . ':managed', self::CACHE_TTL, function () use ($organisation) {
            return Page::forOrganisation($organisation)
                ->whereIn('link', Page::FIXED_LINKS)
                ->distinct()
                ->pluck('link')
                ->all();
        });
    }

    /**
     * The whole menu in order: the menu pages, and the fixed parts (series,
     * calendar, blog) that no link page manages at their default position
     * (Page::FIXED_LINK_POSITIONS). On a tie the fixed part goes first.
     * Link pages get their URL; a link to the blog only shows when there is
     * one ($blogUrl), a link to the series becomes the series themselves.
     * @param Organisation $organisation
     * @param string|null $locale
     * @param string|null $blogUrl the blog link, or null when there is none
     * @return array list of [ type => series|page, page => ?[ title, url, children ] ]
     */
    public function entries(Organisation $organisation, ?string $locale, ?string $blogUrl): array
    {
        $entries = [];
        $managed = $this->managedLinks($organisation);
        foreach (Page::FIXED_LINK_POSITIONS as $link => $position) {
            if (in_array($link, $managed, true)) {
                continue;
            }
            $entry = $this->linkEntry([ 'title' => Page::FIXED_LINK_TITLES[$link][$locale ?? 'nl'] ?? Page::FIXED_LINK_TITLES[$link]['nl'], 'url' => null, 'link' => $link, 'children' => [] ], $blogUrl);
            if ($entry) {
                $entries[] = $entry + [ 'sort' => $position, 'tie' => 0 ];
            }
        }

        foreach ($this->items($organisation, $locale) as $i => $item) {
            $entry = $this->linkEntry($item, $blogUrl);
            if ($entry) {
                $entries[] = $entry + [ 'sort' => (int) ($item['sort_order'] ?? 0), 'tie' => 1 + $i ];
            }
        }

        usort($entries, function (array $a, array $b) {
            return [ $a['sort'], $a['tie'] ] <=> [ $b['sort'], $b['tie'] ];
        });

        return $entries;
    }

    /**
     * @param array $item [ title, url, link, children ]
     * @param string|null $blogUrl
     * @return array|null [ type, page ]
     */
    private function linkEntry(array $item, ?string $blogUrl): ?array
    {
        if (($item['link'] ?? null) === Page::LINK_SERIES) {
            return [ 'type' => 'series', 'page' => null ];
        }

        $item['url'] = $item['link'] ? Page::linkUrl($item['link'], $blogUrl) : $item['url'];
        if (!$item['url']) {
            return null;
        }

        $item['children'] = array_values(array_filter(array_map(function (array $child) use ($blogUrl) {
            $child['url'] = $child['link'] ? Page::linkUrl($child['link'], $blogUrl) : $child['url'];
            return $child['url'] ? $child : null;
        }, $item['children'] ?? [])));

        return [ 'type' => 'page', 'page' => $item ];
    }

    /**
     * The pages marked show_in_footer (any level) with a published
     * translation in the locale, by sort_order. The home page is left out.
     * @param Organisation $organisation
     * @param string|null $locale
     * @return array list of [ title, url ]
     */
    public function footerItems(Organisation $organisation, ?string $locale = null, ?string $blogUrl = null): array
    {
        // Link pages get their URL; the series (already in the footer) and a
        // blog without posts are left out.
        return array_values(array_filter(array_map(function (array $item) use ($blogUrl) {
            $item['url'] = $item['link'] ? Page::linkUrl($item['link'], $blogUrl) : $item['url'];
            return $item['url'] ? $item : null;
        }, $this->footerPages($organisation, $locale))));
    }

    /**
     * @param Organisation $organisation
     * @param string|null $locale
     * @return array list of [ title, url, link ]
     */
    private function footerPages(Organisation $organisation, ?string $locale): array
    {
        $locale = in_array($locale, config('cms.locales'), true) ? $locale : config('cms.default_locale');

        return HostIndependentCache::remember(self::cacheKey($organisation->id, $locale) . ':footer', self::CACHE_TTL, function () use ($organisation, $locale) {
            return PageTranslation::query()
                ->select('page_translations.*', 'pages.link as menu_link')
                ->join('pages', 'pages.id', '=', 'page_translations.page_id')
                ->where('page_translations.organisation_id', '=', $organisation->id)
                ->where('pages.organisation_id', '=', $organisation->id)
                ->where('page_translations.locale', '=', $locale)
                ->where('page_translations.is_published', '=', true)
                ->where('pages.show_in_footer', '=', true)
                ->whereNull('pages.deleted_at')
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('organisations')
                        ->whereColumn('organisations.home_page_id', 'pages.id');
                })
                ->orderBy('pages.sort_order')
                ->orderBy('pages.id')
                ->get()
                ->map(function (PageTranslation $translation) {
                    $link = $translation->menu_link ?: null;
                    return [
                        'title' => $translation->title,
                        'url' => $link === null ? $translation->getUrl() : null,
                        'link' => $link,
                    ];
                })
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
            Cache::forget(self::cacheKey($organisationId, $locale) . ':footer');
        }
        Cache::forget('cms:menu:' . $organisationId . ':managed');
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
