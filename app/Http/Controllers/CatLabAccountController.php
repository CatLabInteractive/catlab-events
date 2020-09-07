<?php

namespace App\Http\Controllers;

use CatLab\Accounts\Client\ApiClient;
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

        $client = new ApiClient($user);

        $url = $client->getAccountLink('/' . $path, Request::query());
        return redirect($url);
    }
}