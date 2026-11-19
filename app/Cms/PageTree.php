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
use Illuminate\Support\Collection;

/**
 * The pages of an organisation as a tree: depth first, siblings by sort
 * order. For the admin (the page list, the "parent page" select).
 *
 * @package App\Cms
 */
class PageTree
{
    const MAX_DEPTH = 20;

    /**
     * @param Organisation $organisation
     * @return array[] see rows()
     */
    public function forOrganisation(Organisation $organisation): array
    {
        return $this->rows(
            Page::forOrganisation($organisation)
                ->with('translations')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
        );
    }

    /**
     * Depth-first list of the pages with their depth. Pages whose parent is
     * not in the list (deleted, or on another page of results) are shown at
     * the top level.
     * @param Collection|Page[] $pages
     * @return array[] [ 'page' => Page, 'depth' => int ], keyed by page id
     */
    public function rows($pages): array
    {
        $pages = Collection::make($pages)->sortBy(function (Page $page) {
            return [ (int) $page->sort_order, (int) $page->id ];
        })->values();

        $ids = $pages->pluck('id')->all();
        $byParent = $pages->groupBy(function (Page $page) use ($ids) {
            return $page->parent_id && in_array($page->parent_id, $ids) ? $page->parent_id : 0;
        });

        $rows = [];
        $walk = function ($parentId, $depth) use (&$walk, &$rows, $byParent) {
            foreach ($byParent->get($parentId, []) as $page) {
                if (isset($rows[$page->id])) {
                    continue;
                }
                $rows[$page->id] = [ 'page' => $page, 'depth' => $depth ];
                if ($depth < self::MAX_DEPTH) {
                    $walk($page->id, $depth + 1);
                }
            }
        };
        $walk(0, 0);

        // Anything a cycle kept out of reach.
        foreach ($pages as $page) {
            if (!isset($rows[$page->id])) {
                $rows[$page->id] = [ 'page' => $page, 'depth' => 0 ];
            }
        }

        return $rows;
    }

    /**
     * The rows without one page and everything below it: the pages that can
     * be that page's parent.
     * @param array[] $rows
     * @param mixed $pageId
     * @return array[]
     */
    public function withoutBranch(array $rows, $pageId): array
    {
        $out = [];
        $skipBelowDepth = null;
        foreach ($rows as $id => $row) {
            if ($skipBelowDepth !== null) {
                if ($row['depth'] > $skipBelowDepth) {
                    continue;
                }
                $skipBelowDepth = null;
            }

            if ((int) $id === (int) $pageId) {
                $skipBelowDepth = $row['depth'];
                continue;
            }

            $out[$id] = $row;
        }

        return $out;
    }

    /**
     * @param Page $page
     * @param int $depth
     * @return string
     */
    public function label(Page $page, int $depth = 0): string
    {
        return str_repeat('— ', $depth) . ($page->title ?? ('Pagina #' . $page->id . ' (zonder vertaling)'));
    }
}
