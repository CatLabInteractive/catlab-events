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

use App\Models\CmsRedirect;
use App\Models\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Resolves a request path that matched no page against the organisation's
 * cms_redirects (exact from_path match, query string carried over).
 *
 * Class Redirects
 * @package App\Cms
 */
class Redirects
{
    /**
     * @param Organisation $organisation
     * @param Request $request
     * @return RedirectResponse|null
     */
    public function resolve(Organisation $organisation, Request $request): ?RedirectResponse
    {
        $path = trim($request->path(), '/');
        if ($path === '' || strlen($path) > 191) {
            return null;
        }

        /** @var CmsRedirect $redirect */
        $redirect = $organisation->cmsRedirects()->where('from_path', '=', $path)->first();
        if (!$redirect) {
            return null;
        }

        $target = $redirect->to_url;

        $query = $request->getQueryString();
        if ($query !== null && $query !== '') {
            $target .= (strpos($target, '?') === false ? '?' : '&') . $query;
        }

        $status = in_array($redirect->status_code, [ 301, 302 ], true) ? $redirect->status_code : 301;

        return redirect()->to($target, $status);
    }
}
