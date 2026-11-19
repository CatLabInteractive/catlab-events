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

use CatLab\CharonFrontend\Widgets\FormField;
use CatLab\CharonFrontend\Widgets\TextWidget;
use Illuminate\Http\Request;

/**
 * A slug: what is typed is lowercased and loses its surrounding spaces and
 * slashes, so "/Over-Ons " is not refused for a detail.
 *
 * @package App\Cms\Widgets
 */
class SlugWidget extends TextWidget
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes + [ 'maxlength' => 191 ]);
    }

    /**
     * @param Request $request
     * @param FormField $field
     * @return string|null
     */
    public function read(Request $request, FormField $field)
    {
        $value = $this->input($request, $field);
        if (!is_string($value)) {
            return null;
        }

        return $this->nullIfEmpty(strtolower(trim($value, " \t\n\r\0\x0B/")));
    }
}
