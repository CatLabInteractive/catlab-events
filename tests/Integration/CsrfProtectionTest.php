<?php

namespace Tests\Integration;

use App\Http\Kernel;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\TokenMismatchException;

/**
 * VerifyCsrfToken had been commented out of the `web` group (security
 * audit 2026-08-27): any site could auto-POST ticket purchases, group
 * merges or admin price changes in a logged-in victim's name. It is back
 * on; only the Pay.nl exchange callback (server-to-server) is excluded.
 *
 * Laravel's middleware short-circuits while running unit tests, so the
 * behaviour is exercised through a subclass that disables that guard.
 */
class CsrfProtectionTest extends IntegrationTestCase
{
    private function enforcingMiddleware(): VerifyCsrfToken
    {
        return new class($this->app, $this->app['encrypter']) extends VerifyCsrfToken {
            protected function runningUnitTests()
            {
                return false;
            }
        };
    }

    private function postRequest(string $uri, array $params = []): Request
    {
        $request = Request::create($uri, 'POST', $params);
        $request->setLaravelSession($this->app['session.store']);
        return $request;
    }

    private function runThrough(Request $request): Response
    {
        return $this->enforcingMiddleware()->handle($request, function () {
            return new Response('reached the controller');
        });
    }

    public function testWebGroupCarriesTheCsrfMiddleware()
    {
        $groups = $this->app->make(Kernel::class)->getMiddlewareGroups();
        $this->assertContains(VerifyCsrfToken::class, $groups['web']);
    }

    public function testPostWithoutTokenIsRejected()
    {
        $this->expectException(TokenMismatchException::class);
        $this->runThrough($this->postRequest('/events/1/register/1/process'));
    }

    public function testPostWithMatchingTokenProceeds()
    {
        $request = $this->postRequest('/events/1/register/1/process', ['_token' => 'test-token']);
        $request->session()->put('_token', 'test-token');

        $this->assertSame('reached the controller', $this->runThrough($request)->getContent());
    }

    public function testPaynlExchangeCallbackIsExcluded()
    {
        $response = $this->runThrough($this->postRequest('/donate/callback'));
        $this->assertSame('reached the controller', $response->getContent(), 'the Pay.nl exchange is server-to-server and carries no token');
    }

    public function testNothingElseIsExcluded()
    {
        $this->expectException(TokenMismatchException::class);
        $this->runThrough($this->postRequest('/groups/1/merge'));
    }
}
