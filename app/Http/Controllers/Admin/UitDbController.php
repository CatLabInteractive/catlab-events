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
 * Class UitDbController
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
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function index()
    {
        // check if we have uitdb configured
        if (!$this->client) {
            return view('admin.uitdb-unavailable');
        }

        $organisation = organisation();

        $user = null;
        if ($organisation->uitdb_identifier) {

            $this->client->setOrganisation($organisation);
            $user = $this->client->getUser();
        }

        return view('admin.uitdb', [
            'organisation' => $organisation,
            'user' => $user
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \League\OAuth1\Client\Credentials\CredentialsException
     */
    public function link(Request $request)
    {
        /*
        $url = $this->client->getConnectUrl(\Request::root() . '/admin/uitdb/connect/next');
        return redirect($url);
        */

        // Use oauth1 to authenticate a user.
        $server = $this->client->getOAuth1Authenticator(
            \Request::root() . '/admin/uitdb/connect/next'
        );

        $temporaryCredentials = $server->getTemporaryCredentials();
        $request->session()->put('oauth_temp_key', serialize($temporaryCredentials));

        return redirect($server->getAuthorizationUrl($temporaryCredentials));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|string
     * @throws \League\OAuth1\Client\Credentials\CredentialsException
     */
    public function afterLink(Request $request)
    {
        $oauthToken = $request->get('oauth_token');
        $oauthVerifier = $request->get('oauth_verifier');

        if (!$oauthToken ||
            !$oauthVerifier
        ) {
            return '<p>No oauth_token found.</p>';
        }

        $server = $this->client->getOAuth1Authenticator(
            \Request::root() . '/admin/uitdb/connect/next'
        );

        $temporaryCredentials = unserialize($request->session()->get('oauth_temp_key'));

        $tokenCredentials = $server->getTokenCredentials($temporaryCredentials, $oauthToken, $oauthVerifier);

        $organisation = organisation();
        $organisation->uitdb_identifier = $tokenCredentials->getIdentifier();
        $organisation->uitdb_secret = $tokenCredentials->getSecret();
        $organisation->save();

        return redirect(action('Admin\\UitDbController@index'));
    }

    /**
     * Remove oauth authentication from current organiser.
     */
    public function unlink()
    {
        $organisation = organisation();
        $organisation->uitdb_identifier = null;
        $organisation->uitdb_secret = null;
        $organisation->save();

        return redirect(action('Admin\\UitDbController@index'));
    }
}
