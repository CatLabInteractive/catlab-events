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
 * Rich text of the CMS (a blog post's body): TinyMCE with image upload to
 * the organisation's assets and YouTube embeds, like the rich text inside
 * section blocks. The server sanitiser (App\Cms\HtmlSanitizer) stays the
 * authority on what is stored.
 *
 * @package App\Cms\Widgets
 */
class HtmlWidget extends BaseWidget
{
    protected $view = 'admin.cms.widgets.html';

    /**
     * @param FormField $field
     * @return array
     */
    protected function getViewData(FormField $field): array
    {
        $value = $field->old('', $field->getValue());

        return [
            'value' => is_scalar($value) ? (string) $value : '',
        ];
    }

    /**
     * @param Request $request
     * @param FormField $field
     * @return string
     */
    public function read(Request $request, FormField $field)
    {
        $value = $this->input($request, $field);

        return is_scalar($value) ? (string) $value : '';
    }
}
