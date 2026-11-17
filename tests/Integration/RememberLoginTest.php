<?php

namespace Tests\Integration;

use App\Http\Controllers\Auth\CatLabLoginController;
use App\Models\User;
use Illuminate\Support\Facades\Cookie;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Buyers stay logged in on the tickets site for a long time: the accounts
 * login callback always sets the remember-me cookie.
 */
class RememberLoginTest extends IntegrationTestCase
{
    public function testAccountsLoginSetsALongLivedRememberCookie()
    {
        $socialiteUser = (new SocialiteUser())->map([
            'id' => 4711,
            'nickname' => 'ada',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
        ]);
        $socialiteUser->token = 'access-token';

        $provider = \Mockery::mock();
        $provider->shouldReceive('user')->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->with('catlab')->andReturn($provider);

        $this->app->make(CatLabLoginController::class)->postLogin();

        $user = User::query()->where('catlab_id', '=', 4711)->firstOrFail();
        $this->assertTrue(\Auth::check());
        $this->assertSame($user->id, \Auth::id());
        $this->assertNotEmpty($user->fresh()->remember_token);

        $cookie = Cookie::queued(\Auth::guard()->getRecallerName());
        $this->assertNotNull($cookie, 'remember-me cookie is set');
        $this->assertGreaterThan(time() + 365 * 24 * 3600, $cookie->getExpiresTime(), 'lasts well over a year');
    }
}
