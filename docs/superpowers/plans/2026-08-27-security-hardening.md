# Security hardening (CSRF, order access, signed sync, state bug) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the CatLab Events findings from the 2026-08-27 cross-repo security audit (accounts / QuizWitz / events): CSRF protection disabled for every web route (HIGH), `orders/{id}` readable by any logged-in user (MEDIUM), `orders/{id}/thanks` and `orders/{id}/sync` public (MEDIUM), `Order::synchronize()` reading a non-existent `status` attribute (LOW).

**Architecture:** Re-enable `VerifyCsrfToken` in the `web` group with `donate/callback` (Pay.nl exchange, server-to-server POST) excluded; every form already carries a token (Collective `Form::open` injects `_token`, raw `<form>`s use `{{ csrf_field() }}`, the only `$.post` in `custom.js` targets a contact route that no longer exists). `orders/{id}` checks ownership (`Order::isViewableBy(User)`: buyer, member of the order's group, or admin). Two URLs cannot rely on a session and get a per-order, per-purpose HMAC keyed with `APP_KEY` instead: `orders/{id}/sync` (the callback accounts GETs; `Order::syncSignature()` embedded by `EventController::processRegister`) and `orders/{id}/thanks` (the payment return URL, frequently opened on ANOTHER device after a QR-code scan; `Order::thanksSignature()` embedded by `Order::getThanksUrl()` / `getPayUrl()`, with ownership as the alternative for logged-in visitors). `synchronize()` compares `state`.

**Tech Stack:** Laravel 9, PHP ≥ 8.0 (project image `catlab-events-php85-test`), PHPUnit 8, integration harness `tests/Integration` (`RefreshDatabase`, `FakeCatLabApiClient`).

**Spec:** accounts repo `docs/superpowers/specs/2026-08-27-security-audit-findings.md`, section "catlab-events".

## Global Constraints

- Branch `security/audit-2026-08-27`, based on `feature/integration-tests` (the harness lives there); PR targets that branch.
- Run: `docker run --rm -v $PWD:/var/www/html -w /var/www/html --entrypoint php -e DB_HOST=<mysql-ip> -e DB_PASSWORD=root catlab-events-php85-test:latest vendor/bin/phpunit -c phpunit.integration.xml <args>` (mirrors `bin/integration-tests.sh`; `.env` copied from the main checkout for `APP_KEY`).
- Integration tests post without CSRF tokens by design: `IntegrationTestCase::setUp` disables `VerifyCsrfToken` for the suite; `CsrfProtectionTest` re-enables it explicitly.

---

### Task 1: CSRF middleware back on

**Files:** `app/Http/Kernel.php:60`, `app/Http/Middleware/VerifyCsrfToken.php` (`$except`), `tests/Integration/IntegrationTestCase.php` (disable for the suite), `tests/Integration/CsrfProtectionTest.php` (create).

- [ ] Failing test:
```php
<?php

namespace Tests\Integration;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Order;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * VerifyCsrfToken had been commented out of the `web` group (security
 * audit 2026-08-27): any site could auto-POST ticket purchases, group
 * merges or admin price changes in a logged-in victim's name. It is back
 * on; only the Pay.nl exchange callback (server-to-server) is excluded.
 */
class CsrfProtectionTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // IntegrationTestCase disables the middleware for the rest of the
        // suite; this test is about the middleware.
        $this->withMiddleware(VerifyCsrfToken::class);
    }

    public function testPostWithoutTokenIsRejected()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);
        $user = $this->createUser();

        $this->actingAs($user)
            ->post("/events/{$event->id}/register/{$category->id}/process")
            ->assertStatus(419);

        $this->assertEquals(0, Order::query()->count(), 'a forged POST must not create an order');
    }

    public function testPostWithTokenProceeds()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);
        $user = $this->createUser();

        $response = $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->post("/events/{$event->id}/register/{$category->id}/process", ['_token' => 'test-token']);

        $this->assertNotEquals(419, $response->getStatusCode());
        $this->assertEquals(1, Order::query()->count());
    }

    public function testPaynlExchangeCallbackIsExcluded()
    {
        // No token, no session: must not be a 419 (the controller itself
        // will fail on the missing Pay.nl transaction, which is fine here).
        $response = $this->post('/donate/callback');
        $this->assertNotEquals(419, $response->getStatusCode());
    }
}
```
- [ ] RED: first test gets 302 (no CSRF check); third passes already.
- [ ] Implement: uncomment `\App\Http\Middleware\VerifyCsrfToken::class` in `Kernel::$middlewareGroups['web']`; `$except = ['donate/callback']` with a comment; in `IntegrationTestCase::setUp()` add `$this->withoutMiddleware(VerifyCsrfToken::class);` (import it) so the existing tests keep posting without tokens.
- [ ] GREEN + whole integration suite; commit `"Re-enable CSRF protection on web routes (Pay.nl exchange excluded)"`.

### Task 2: `Order::isViewableBy()`; `orders/{id}` requires it, `orders/{id}/thanks` requires it OR the signed return URL

(Revised during execution: the return URL is often opened on another device — QR-code payment on a phone — so `thanks` stays outside the `auth` group and accepts `Order::thanksSignature()` from the URL built by `Order::getThanksUrl()`.)

**Files:** `app/Models/Order.php` (add `isViewableBy(User $user): bool`), `app/Http/Controllers/OrderController.php` (`view()`, `thanks()`), `routes/web.php:133` (move `thanks` under the `auth` group), `tests/Integration/OrderAccessTest.php` (create).

- [ ] Failing tests: stranger `GET /orders/{id}` → 403; owner → 200; admin → 200; group member → 200 (create a second user, add to the order's group via `group->members()`/`GroupMember` — check `app/Models/Group.php` for the relation; skip this case if groups need more fixtures than the harness offers and note it). `GET /orders/{id}/thanks` anonymous → redirect to `/login`; stranger → 403; owner → 200.
- [ ] Implement:
```php
    /**
     * Who may read this order (audit: orders/{id} was readable by any
     * logged-in user, exposing team, items and the livestream link).
     */
    public function isViewableBy(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        if ($user->isAdmin() || (int)$this->user_id === (int)$user->id) {
            return true;
        }
        return $this->group && $this->group->isMember($user);
    }
```
(Check the actual group-membership helper name on `App\Models\Group`; if none, `$this->group->members()->where('user_id', $user->id)->exists()`.) In `view()` and `thanks()`: `abort_unless($order->isViewableBy(\Auth::user()), 403);` right after `findOrFail`. Route: move `Route::get('orders/{id}/thanks', …)` into the `auth` middleware group (the buyer returns from accounts in the same browser session; a lost session goes through login and lands back on the page).
- [ ] GREEN; commit `"Order pages require ownership (buyer, group member or admin)"`.

### Task 3: signed `orders/{id}/sync`

**Files:** `app/Models/Order.php` (`syncSignature()`, `verifySyncSignature()`), `app/Http/Controllers/EventController.php:812` (callback URL), `app/Http/Controllers/OrderController.php` (`sync()`), `tests/Integration/PaidTicketPurchaseTest.php` (use the signed URL), `tests/Integration/OrderAccessTest.php` (unsigned → 403 and no state change; signed → 200).

- [ ] Failing tests as described; `PaidTicketPurchaseTest` takes the callback from `$this->catlabApi->createOrderCalls[0]['callback']` and GETs its path (so it also proves the stored callback is the signed one).
- [ ] Implement:
```php
    public function syncSignature(): string
    {
        return hash_hmac('sha256', 'order-sync:' . $this->id, (string)config('app.key'));
    }

    public function verifySyncSignature(?string $signature): bool
    {
        return is_string($signature) && strlen($signature) === 64 && hash_equals($this->syncSignature(), $signature);
    }
```
`EventController`: `'callback' => action('OrderController@sync', [ $order->id, 'sig' => $order->syncSignature() ])`. `OrderController::sync()`: `abort_unless($order->verifySyncSignature(\Request::get('sig')), 403);` before `synchronize()`.
- [ ] GREEN; commit `"orders/{id}/sync requires the per-order signature embedded in the accounts callback"`.

### Task 4: `Order::synchronize()` compares `state`

**Files:** `app/Models/Order.php:178-186`, `tests/Integration/OrderAccessTest.php` (or `OrderSyncTest`).

- [ ] Failing test: order locally CANCELLED (`$order->state = Order::STATE_CANCELLED; save`), fake status `PENDING`, signed sync → state stays CANCELLED (today the dead guard lets it flip back to PENDING). And: locally ACCEPTED + remote ACCEPTED → no `changeState` (no duplicate confirmation email: `sendEmailCalls` count unchanged).
- [ ] Implement: replace the two `$this->status` reads with `$this->state`.
- [ ] GREEN; commit `"Order::synchronize compares the real state column"`.

### Task 5: PR
- `docs/superpowers/plans/…` committed; PR against `feature/integration-tests` describing the four fixes and the `.env`-free test runner line.
