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

use App\Services\CatLabApiClientFactory;
use Request;

/**
 * Class CatLabAccountController
 * @package App\Http\Controllers
 */
class CatLabAccountController extends Controller
{
    /**
     * Redirect user to catlab accounts
     * @param $path
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function redirect($path)
    {
        $user = \Auth::getUser();

        $client = app(CatLabApiClientFactory::class)->forUser($user);

        $parameters = Request::query();
        if (!isset($parameters['return'])) {
            $parameters['return'] = action('EventController@index', [], true);
        }

        if (!isset($parameters['lang'])) {
            $parameters['lang'] = mb_substr(\App::getLocale(), 0, 2);
        }

        // The link carries a single-use login token minted at accounts
        // (laravel-catlab-accounts >= 4.1, accounts issue #100); when that
        // cannot be minted there is no safe link to send the user to.
        try {
            $url = $client->getAccountLink('/' . $path, $parameters);
        } catch (\RuntimeException $e) {
            \Log::error($e);
            abort(503, 'CatLab Accounts is momenteel niet bereikbaar. Probeer het zo meteen opnieuw.');
        }
        return redirect($url);
    }
}
