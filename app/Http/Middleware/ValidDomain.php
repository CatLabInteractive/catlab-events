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

use Auth;
use Closure;

class ValidDomain
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Check if route is /status, and if so, ignore
        if ($request->is('status')) {
            return $next($request);
        }

        $validDomains = config('app.valid_domains');
        if ($validDomains === null || count($validDomains) === 0) {
            return $next($request);
        }

        if (isset($_SERVER['HTTP_HOST'])) {
            $rootUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];

            if (!in_array($rootUrl, $validDomains)) {

                // Is this a https "www" issue?
                $wwwRootUrl = 'https://' . $_SERVER['HTTP_HOST'];
                if (in_array($wwwRootUrl, $validDomains)) {
                    return $this->redirect($wwwRootUrl);
                }

                // Is this a "www" issue?
                $wwwRootUrl = $_SERVER['REQUEST_SCHEME'] . '://www.' . $_SERVER['HTTP_HOST'];
                if (in_array($wwwRootUrl, $validDomains)) {
                    return $this->redirect($wwwRootUrl);
                }

                // Is this a https "www" issue?
                $wwwRootUrl = 'https://www.' . $_SERVER['HTTP_HOST'];
                if (in_array($wwwRootUrl, $validDomains)) {
                    return $this->redirect($wwwRootUrl);
                }

                $redirectUrl = $validDomains[0] . $_SERVER['REQUEST_URI'];
                return $this->redirect($redirectUrl);
            }
        }

        return $next($request);
    }

    /**
     * @param $protocolDomain
     * @return mixed
     */
    protected function redirect($protocolDomain)
    {
        //$protocolDomain .= '/';
        if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] !== '/') {
            $protocolDomain .= $_SERVER['REQUEST_URI'];
        }

        if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
            $protocolDomain .= '?' . $_SERVER['QUERY_STRING'];
        }

        return redirect($protocolDomain);
    }
}
