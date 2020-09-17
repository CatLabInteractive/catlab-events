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

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\UitDB\UitDatabankService;
use Illuminate\Http\Request;

/**
 * Class UitDbAuthentication
 * @package App\Http\Controllers\Admin
 */
class UitDbController extends Controller
{
    /**
     * @var UitDatabankService
     */
    private $client;

    /**
     * UitDbAuthentication constructor.
     */
    public function __construct()
    {
        $this->client = UitDatabankService::fromConfig();
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        // check if we have uitdb configured
        if (!$this->client) {
            return view('admin.uitdb-unavailable');
        }

        $organisation = organisation();

        $user = null;
        if ($organisation->uitdb_jwt) {
            $this->client->setConnectAuthentication($organisation->uitdb_jwt, $organisation->uitdb_refresh);
            //$user = $this->client->getUser();
        }

        return view('admin.uitdb', [
            'user' => $user
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function link(Request $request)
    {
        $url = $this->client->getConnectUrl(\Request::root() . '/admin/uitdb/connect/next');
        return redirect($url);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function afterLink(Request $request)
    {
        $jwt = $request->query('jwt');
        $refresh = $request->query('refresh');

        $organisation = organisation();
        $organisation->uitdb_jwt = $jwt;
        $organisation->uitdb_refresh = $refresh;
        $organisation->save();

        return redirect(action('Admin\\UitDbController@index'));
    }
}
