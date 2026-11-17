<?php

namespace App\Http\Controllers\Auth;

use CatLab\Accounts\Client\Controllers\LoginController;
use Laravel\Socialite\Two\InvalidStateException;
use Socialite;

/**
 * Class CatLabLoginController
 *
 * The CatLab accounts login, with "remember me" always on: being logged in
 * on the tickets site gives no access to anything sensitive (payments,
 * billing and the account itself stay behind the accounts login), so
 * buyers stay logged in here for a long time (config auth.guards.web.remember)
 * while the accounts session stays short.
 *
 * @package App\Http\Controllers\Auth
 */
class CatLabLoginController extends LoginController
{
    /**
     * After the user came back from accounts.
     */
    public function postLogin()
    {
        try {
            $socialiteUser = Socialite::driver('catlab')->user();

            $user = $this->getUserFromSocialite($socialiteUser);
            \Auth::login($user, true);

            return redirect()->intended($this->redirectPath());
        } catch (InvalidStateException $e) {
            return '<p>Invalid state.</p>';
        }
    }
}
