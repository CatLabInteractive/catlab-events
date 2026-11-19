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

use CatLab\CharonFrontend\Widgets\BaseWidget;
use CatLab\CharonFrontend\Widgets\FormField;
use Illuminate\Http\Request;

/**
 * An image of the active organisation for a field that holds an asset id
 * (og_image_id, featured_image_id): thumbnail, and the CMS image picker to
 * choose or upload one, the same picker the section blocks use.
 *
 * @package App\Cms\Widgets
 */
class ImageWidget extends BaseWidget
{
    protected $view = 'admin.cms.widgets.image';

    /**
     * @param FormField $field
     * @return array
     */
    protected function getViewData(FormField $field): array
    {
        return [
            'value' => $field->old('', $field->getValue()),
        ];
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
