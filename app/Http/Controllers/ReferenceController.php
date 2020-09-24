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

namespace App\Http\Controllers;

use App\Models\Series;

/**
 * Class ReferenceController
 * @package App\Http\Controllers
 */
class ReferenceController
{
    /**
     *
     */
    public static function routes()
    {
        $routes = self::getReferences();

        foreach ($routes as $k => $v) {
            \Route::get($k, 'ReferenceController@redirect');
        }
    }

    /**
     * @return array
     */
    public static function getReferences()
    {
        return [
            'palaver' => Series::find(1)->getUrl()
        ];
    }

    /**
     *
     */
    public function redirect()
    {
        $path = \Route::getCurrentRoute()->uri;

        $routes = self::getReferences();
        if (isset($routes[$path])) {
            $url = $routes[$path];
        } else {
            $url = Series::find(1)->getUrl();
        }

        $url = $this->getTaggedUrl($url, $path);
        return redirect($url);
    }

    /**
     * @param $url
     * @param $name
     * @return string
     */
    private function getTaggedUrl($url, $name)
    {
        $url .= '?' . http_build_query
            ([
                'utm_source' => 'offline',
                'utm_medium' => 'podcast',
                'utm_term' => $name
            ]);

        return $url;
    }
}
