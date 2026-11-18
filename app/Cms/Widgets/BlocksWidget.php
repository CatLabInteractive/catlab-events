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

use App\Cms\Blocks\BlockRegistry;
use CatLab\CharonFrontend\Widgets\BaseWidget;
use CatLab\CharonFrontend\Widgets\FormField;
use Illuminate\Http\Request;

/**
 * The section blocks of a page translation on a Charon admin form: one card
 * per block with the block type's own fields, add / move / remove
 * (resources/assets/js/cms-editor.js). Reads back the list of
 * { id, type, data } the API takes.
 *
 * @package App\Cms\Widgets
 */
class BlocksWidget extends BaseWidget
{
    protected $view = 'admin.cms.widgets.blocks';

    /**
     * @param FormField $field
     * @return array
     */
    protected function getViewData(FormField $field): array
    {
        $blocks = $field->old('', $field->getValue());

        // Charon hands objects back as it got them (arrays or objects).
        $blocks = json_decode(json_encode($blocks ?: []), true);

        return [
            'blocks' => is_array($blocks) ? array_values(array_filter($blocks, 'is_array')) : [],
            'blockTypes' => app(BlockRegistry::class)->all(),
            'blocksName' => $field->getInputName(),
        ];
    }

    /**
     * @param Request $request
     * @param FormField $field
     * @return array
     */
    public function read(Request $request, FormField $field)
    {
        $blocks = $this->input($request, $field);

        return is_array($blocks) ? array_values(array_filter($blocks, 'is_array')) : [];
    }
}
