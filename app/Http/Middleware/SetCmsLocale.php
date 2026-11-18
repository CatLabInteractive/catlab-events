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

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Sets the application locale for CMS routes from the {locale} route
 * parameter (absent: the default locale), then removes the parameter so
 * controllers do not receive it. Ticketing routes never pass through here
 * and keep app.locale.
 *
 * Class SetCmsLocale
 * @package App\Http\Middleware
 */
class SetCmsLocale
{
    /**
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $route = $request->route();

        $locale = $route ? $route->parameter('locale') : null;
        if ($locale === null) {
            $locale = config('cms.default_locale');
        }

        if (!in_array($locale, config('cms.locales', []), true)) {
            abort(404);
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        if ($route) {
            $route->forgetParameter('locale');
        }

        View::share('cmsLocale', $locale);

        return $next($request);
    }
}
