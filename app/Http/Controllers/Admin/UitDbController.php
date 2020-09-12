<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\UitDB\UitDatabank;
use Illuminate\Http\Request;

/**
 * Class UitDbAuthentication
 * @package App\Http\Controllers\Admin
 */
class UitDbController extends Controller
{
    /**
     * @var UitDatabank
     */
    private $client;

    /**
     * UitDbAuthentication constructor.
     */
    public function __construct()
    {
        if (config('services.uitdb.key')) {
            $this->client = new UitDatabank(
                config('services.uitdb.key'),
                config('services.uitdb.env')
            );
        }
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
            $this->client->setAuthentication($organisation->uitdb_jwt, $organisation->uitdb_refresh);
            $user = $this->client->getUser();
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
