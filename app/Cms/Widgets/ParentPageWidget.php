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

namespace App\Cms\Widgets;

use App\Cms\PageTree;
use CatLab\CharonFrontend\Widgets\FormField;
use CatLab\CharonFrontend\Widgets\ProvidesOptions;
use CatLab\CharonFrontend\Widgets\SelectWidget;
use Illuminate\Http\Request;

/**
 * The parent of a page: the active organisation's pages as a tree, without
 * the page itself and the pages below it.
 *
 * @package App\Cms\Widgets
 */
class ParentPageWidget extends SelectWidget implements ProvidesOptions
{
    /**
     * @param Request $request
     * @param FormField $field
     * @return string[]
     */
    public function getOptions(Request $request, FormField $field): array
    {
        $options = [ '' => '(geen: hoofdniveau)' ];

        $organisation = $request->user() ? $request->user()->getActiveOrganisation() : null;
        if (!$organisation) {
            return $options;
        }

        $tree = app(PageTree::class);
        $rows = $tree->forOrganisation($organisation);

        $resource = $field->getResource();
        if ($resource) {
            $identifiers = $resource->getIdentifiers()->getValues();
            if (count($identifiers)) {
                $rows = $tree->withoutBranch($rows, $identifiers[0]->getValue());
            }
        }

        foreach ($rows as $id => $row) {
            $options[$id] = $tree->label($row['page'], $row['depth']);
        }

        return $options;
    }

    /**
     * @param Request $request
     * @param FormField $field
     * @return int|null
     */
    public function read(Request $request, FormField $field)
    {
        $value = $this->input($request, $field);

        return is_numeric($value) ? (int) $value : null;
    }
}
