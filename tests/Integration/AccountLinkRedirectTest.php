<?php

namespace Tests\Integration;

use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * GET /catlabaccount/{path} sends the logged-in user to their CatLab
 * Accounts page through ApiClient::getAccountLink(). Since
 * laravel-catlab-accounts 4.1 that link carries a single-use login token
 * minted at accounts (accounts issue #100) and the client throws when it
 * cannot mint one -- which must be a 503, not an unhandled 500.
 */
class AccountLinkRedirectTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    public function testRedirectsToTheAccountLinkForTheLoggedInUser()
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get('/catlabaccount/myaccount?lang=nl');

        $response->assertStatus(302);
        $this->assertCount(1, $this->catlabApi->accountLinkCalls);
        $this->assertSame('/myaccount', $this->catlabApi->accountLinkCalls[0]['path']);
        $this->assertSame('nl', $this->catlabApi->accountLinkCalls[0]['parameters']['lang']);
        $this->assertStringStartsWith('https://accounts.example.test/myaccount?', $response->headers->get('Location'));
    }

    public function testAnUnmintableLoginTokenIsA503()
    {
        $user = $this->createUser();
        $this->catlabApi->accountLinkException = new \RuntimeException('login-token: HTTP 503');

        $this->actingAs($user)->get('/catlabaccount/myaccount')->assertStatus(503);
    }
}
