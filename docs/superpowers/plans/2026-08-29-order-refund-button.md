# Order Refund Button Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give events admins a refund button for a paid order, behind a typed-reference confirmation, that really refunds the money through CatLab Accounts.

**Architecture:** Accounts already refunds for real via `Payment::refund()` (Omnipay → PSP → credit note → `STATUS_REFUNDED`), and events already reacts to a `REFUNDED` status through its existing order sync. The only missing piece is the API surface between them. We add a refund endpoint to accounts, guarded by a **new per-order refund token** that events stores in its own database, so leaked client credentials alone cannot refund anything.

**Tech Stack:** PHP. `catlab-accounts` is a custom framework (Neuron: `Query`, `Response`, mappers, `MapperFactory`). `laravel-catlab-accounts` is a plain Guzzle client. `catlab-events` is Laravel 5-era with Charon admin CRUD and PHPUnit 8.

**Spec:** `docs/superpowers/specs/2026-08-29-order-refund-button-design.md`

## Global Constraints

- **Rollout order is strict:** accounts (Phase 1) must be deployed before the client is tagged (Phase 2), which must be tagged before events is updated (Phase 3). Do not start a phase before the previous one is committed.
- **Wrong refund token and unknown order return the identical `404`.** The endpoint must never reveal which orders exist or that a token guess was close.
- **Money amounts compare in cents:** `(int) round($value * 100)`. Never compare floats directly.
- **The order token (`order_token`) is NOT the refund token.** `getReceiptUrl()` leaks `order_token` through `GET orders/{id}?expanded=1`, so it cannot guard anything. Always use the new `order_refund_token`.
- **A timeout is not a failure.** Events must never report a refund as failed just because the HTTP call timed out — the money may well have moved.
- Refund throttle: **10 refunds per product per hour**, HTTP `429` beyond.
- Accounts SQL migrations: `db/upgrade-YYYYMMDD.sql`, **no semicolons inside comments** (`commands/migrate.php` splits on `;`).
- Accounts tests run with `vendor/bin/phpunit -c phpunit.integration.xml`; events tests run inside the `catlab-events` docker container: `docker exec catlab-events vendor/bin/phpunit -c phpunit.integration.xml`.
- Copy shown to admins is Dutch.

---

# Phase 1 — catlab-accounts

Work in `~/Workbench/catlab-accounts` on a new branch off `develop`:
`git checkout develop && git pull && git checkout -b feature/order-refund-api`

---

### Task 1: Mint a refund token on every new order

**Files:**
- Create: `db/upgrade-20260829.sql`
- Modify: `src/Accounts/Models/Order.php` (add field + accessors; `create()` at line ~36)
- Modify: `src/Accounts/Mappers/OrderMapper.php` (`getObjectFromData()` ~line 454, `getDataToSet()` ~line 556)
- Modify: `src/Accounts/Controllers/API/Orders.php` (`create()` response, ~line 86)
- Test: `tests/integration/Api/OrdersRefundTokenTest.php`

**Interfaces:**
- Consumes: nothing (first task).
- Produces: `Order::getRefundToken(): ?string`, `Order::setRefundToken($token): void`, DB column `orders.order_refund_token`, and a `refundToken` key in the `POST api/1.0/users/{id}/orders` JSON response.

- [ ] **Step 1: Write the failing test**

Create `tests/integration/Api/OrdersRefundTokenTest.php`:

```php
<?php

namespace Tests\Integration\Api;

use Accounts\MapperFactory;
use Neuron\DB\Query;
use Tests\Integration\Harness\Fixtures;
use Tests\Integration\Harness\IntegrationRequestCase;

/**
 * Refunding through the API needs a second factor that leaked client
 * credentials do not yield. order_token cannot be it: getReceiptUrl()
 * hands it to anyone who can read the expanded order. So every order gets
 * a separate refund token, returned once at creation and never by a GET.
 */
class OrdersRefundTokenTest extends IntegrationRequestCase
{
    /** @var int */
    private $productId;
    /** @var string */
    private $clientId;
    /** @var int */
    private $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productId = Fixtures::product();
        $this->clientId = Fixtures::oauthClient($this->productId);
        $this->userId = Fixtures::user();
        Fixtures::personalProfile($this->userId);
    }

    private function createOrder(): \Neuron\Net\Response
    {
        // IntegrationRequestCase::request() has no 'json' option: build the
        // body and content-type explicitly.
        return $this->request('POST', '/api/1.0/users/' . $this->userId . '/orders', [
            'body' => json_encode([
                'items' => [
                    [ 'name' => 'Ticket', 'amount' => 1, 'price' => 10.0, 'vat' => 0.0 ],
                ],
            ]),
            'headers' => [
                'Content-type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($this->clientId . ':test-secret'),
            ],
        ]);
    }

    public function testCreateReturnsARefundToken()
    {
        $data = $this->assertJsonOk($this->createOrder());

        $this->assertArrayHasKey('refundToken', $data);
        $this->assertNotEmpty($data['refundToken']);
        $this->assertSame(24, strlen($data['refundToken']));
    }

    public function testTheRefundTokenIsStoredAndDiffersFromTheOrderToken()
    {
        $data = $this->assertJsonOk($this->createOrder());

        $row = Query::select('orders', ['order_token', 'order_refund_token'], ['order_id' => $data['id']])
            ->execute();

        $this->assertSame($data['refundToken'], $row[0]['order_refund_token']);
        $this->assertNotSame($row[0]['order_token'], $row[0]['order_refund_token']);
    }

    public function testTheRefundTokenSurvivesAReload()
    {
        $data = $this->assertJsonOk($this->createOrder());

        $order = MapperFactory::getOrderMapper()->getFromId(intval($data['id']));

        $this->assertSame($data['refundToken'], $order->getRefundToken());
    }

    public function testTheRefundTokenIsNotExposedByGet()
    {
        $data = $this->assertJsonOk($this->createOrder());

        $response = $this->request('GET', '/api/1.0/orders/' . $data['id'], [
            'query' => [ 'expanded' => 1 ],
            'headers' => [ 'Authorization' => 'Basic ' . base64_encode($this->clientId . ':test-secret') ],
        ]);

        $body = (string) $response->getBody();
        $this->assertStringNotContainsString($data['refundToken'], $body);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd ~/Workbench/catlab-accounts && vendor/bin/phpunit -c phpunit.integration.xml --filter OrdersRefundTokenTest`
Expected: FAIL — no `refundToken` key in the response, and `order_refund_token` is an unknown column.

- [ ] **Step 3: Add the database column**

Create `db/upgrade-20260829.sql`:

```sql
-- Refunding an order through the API (POST api/1.0/orders/{id}/refund)
-- requires this per-order token on top of the product client credentials.
-- It is deliberately NOT order_token: getReceiptUrl() embeds order_token in
-- the receipt url that GET orders/{id}?expanded=1 returns, so the same
-- credentials already yield it. NULL for orders created before this column
-- existed -- those cannot be refunded through the API.
-- (No semicolons in comments -- commands/migrate.php splits on them.)
ALTER TABLE orders ADD order_refund_token varchar(32) NULL AFTER order_token;
```

- [ ] **Step 4: Add the field and accessors to the Order model**

In `src/Accounts/Models/Order.php`, beside the existing `private $token;` (~line 102) add:

```php
    /**
     * Second factor for the refund API, on top of the product client
     * credentials. Never returned by any GET endpoint.
     * @var string|null
     */
    private $refundToken;
```

Beside `getToken()`/`setToken()` (~line 583) add:

```php
    /**
     * @return string|null
     */
    public function getRefundToken()
    {
        return $this->refundToken;
    }

    /**
     * @param string|null $refundToken
     */
    public function setRefundToken($refundToken)
    {
        $this->refundToken = $refundToken;
    }
```

In `create()` (~line 36), directly after the existing `setToken()` line:

```php
        $order->setToken(SecureToken::getToken(12));
        $order->setRefundToken(SecureToken::getToken(24));
```

- [ ] **Step 5: Map the column**

In `src/Accounts/Mappers/OrderMapper.php`, in `getObjectFromData()` after `$order->setToken($data['order_token']);`:

```php
        $order->setRefundToken(isset($data['order_refund_token']) ? $data['order_refund_token'] : null);
```

In `getDataToSet()` after `$out['order_token'] = $order->getToken();`:

```php
        $out['order_refund_token'] = $order->getRefundToken();
```

- [ ] **Step 6: Return it from create**

In `src/Accounts/Controllers/API/Orders.php`, in the `create()` response (~line 86):

```php
        return Response::json(array(
            'id' => $order->getId(),
            'payUrl' => URLBuilder::getURL('pay/' . $order->getId() . '/' . $order->getToken()),
            'refundToken' => $order->getRefundToken()
        ));
```

- [ ] **Step 7: Run the migration and the tests**

Run: `cd ~/Workbench/catlab-accounts && php commands/migrate.php && vendor/bin/phpunit -c phpunit.integration.xml --filter OrdersRefundTokenTest`
Expected: PASS (4 tests)

- [ ] **Step 8: Run the full accounts suite**

Run: `cd ~/Workbench/catlab-accounts && vendor/bin/phpunit -c phpunit.integration.xml && vendor/bin/phpunit`
Expected: PASS, no regressions.

- [ ] **Step 9: Commit**

```bash
cd ~/Workbench/catlab-accounts
git add db/upgrade-20260829.sql src/Accounts/Models/Order.php src/Accounts/Mappers/OrderMapper.php src/Accounts/Controllers/API/Orders.php tests/integration/Api/OrdersRefundTokenTest.php
git commit -m "Mint a per-order refund token and return it at creation"
```

---

### Task 2: The refund endpoint

**Files:**
- Modify: `src/Accounts/Module.php:590` (route block)
- Modify: `src/Accounts/Controllers/API/Orders.php` (new `refund()` method)
- Test: `tests/integration/Api/OrdersRefundApiTest.php`

**Interfaces:**
- Consumes: `Order::getRefundToken()` from Task 1.
- Produces: `POST api/1.0/orders/{id}/refund`. Request JSON: `refundToken` (string), `amount` (number, the order's `getPriceToPay()`), `reason` (string). Response `200`: `{"id": int, "status": "REFUNDED"}`. Errors per the table below.

| Condition | Status |
|---|---|
| Missing/invalid client credentials | 401 |
| Order missing, product mismatch, or refund token mismatch | 404 |
| Order not `ACCEPTED`, amount mismatch, or no payment to refund | 409 |
| Success | 200 |

- [ ] **Step 1: Write the failing test**

Create `tests/integration/Api/OrdersRefundApiTest.php`:

```php
<?php

namespace Tests\Integration\Api;

use Accounts\MapperFactory;
use Accounts\OAuth2\Scopes;
use Neuron\DB\Query;
use Tests\Integration\Harness\Fixtures;
use Tests\Integration\Harness\IntegrationRequestCase;

/**
 * POST api/1.0/orders/{id}/refund exposes the refund that until now only
 * the accounts admin panel could trigger. Client credentials alone are not
 * enough: the caller must also present the order's refund token, which no
 * GET endpoint returns. A wrong token is indistinguishable from an unknown
 * order, so the endpoint cannot be used to probe.
 */
class OrdersRefundApiTest extends IntegrationRequestCase
{
    /** @var int */
    private $productId;
    /** @var string */
    private $clientId;
    /** @var int */
    private $orderId;
    /** @var string */
    private $refundToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productId = Fixtures::product();
        $this->clientId = Fixtures::oauthClient($this->productId);

        $userId = Fixtures::user();
        $profileId = Fixtures::personalProfile($userId);

        $this->refundToken = 'refundtoken0123456789ab';
        $this->orderId = Fixtures::order($profileId, [
            'order_product_id' => $this->productId,
            'order_status' => 'ACCEPTED',
            'order_price' => 25.0,
            'order_discount' => 0.0,
            'order_currency' => 'EUR',
            'order_net_value_eur' => 2500,
            'order_refund_token' => $this->refundToken,
        ]);

        Fixtures::payment($this->orderId, [
            'pay_type' => 'PAYMENT',
            'pay_status' => 'ACCEPTED',
            'pay_gateway' => 'Accounts\\Models\\PaymentGateways\\InvoicePaymentGateway',
            'pay_tx_ref' => 'TX-' . $this->orderId,
        ]);
    }

    private function refund(array $body, ?string $clientId = null, ?string $orderId = null): \Neuron\Net\Response
    {
        $clientId = $clientId ?: $this->clientId;
        $orderId = $orderId ?: (string) $this->orderId;

        // IntegrationRequestCase::request() has no 'json' option: build the
        // body and content-type explicitly.
        return $this->request('POST', '/api/1.0/orders/' . $orderId . '/refund', [
            'body' => json_encode($body),
            'headers' => [
                'Content-type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($clientId . ':test-secret'),
            ],
        ]);
    }

    private function validBody(): array
    {
        return [ 'refundToken' => $this->refundToken, 'amount' => 25.0, 'reason' => 'test' ];
    }

    private function orderStatus(): string
    {
        $row = Query::select('orders', ['order_status'], ['order_id' => $this->orderId])->execute();
        return $row[0]['order_status'];
    }

    public function testAnonymousIs401()
    {
        $this->bearerToken = null;
        $response = $this->postJson('/api/1.0/orders/' . $this->orderId . '/refund', $this->validBody());

        $this->assertSame(401, $response->getStatus() ?: 200);
        $this->assertSame('ACCEPTED', $this->orderStatus());
    }

    public function testAUserTokenIs401()
    {
        $userId = Fixtures::user();
        $token = Fixtures::accessToken($userId, $this->clientId, implode(' ', Scopes::ALL), Fixtures::personalProfile($userId));

        $response = $this->withToken($token)->postJson('/api/1.0/orders/' . $this->orderId . '/refund', $this->validBody());

        $this->assertSame(401, $response->getStatus() ?: 200);
        $this->assertSame('ACCEPTED', $this->orderStatus());
    }

    public function testAnotherProductIs404()
    {
        $otherClient = Fixtures::oauthClient(Fixtures::product());
        $response = $this->refund($this->validBody(), $otherClient);

        $this->assertSame(404, $response->getStatus() ?: 200);
        $this->assertSame('ACCEPTED', $this->orderStatus());
    }

    public function testUnknownOrderIs404()
    {
        $response = $this->refund($this->validBody(), null, '999999999');

        $this->assertSame(404, $response->getStatus() ?: 200);
    }

    public function testAWrongRefundTokenIs404AndLooksLikeAnUnknownOrder()
    {
        $wrong = $this->refund([ 'refundToken' => 'nope', 'amount' => 25.0, 'reason' => 'test' ]);
        $unknown = $this->refund($this->validBody(), null, '999999999');

        $this->assertSame(404, $wrong->getStatus() ?: 200);
        $this->assertSame((string) $unknown->getBody(), (string) $wrong->getBody());
        $this->assertSame('ACCEPTED', $this->orderStatus());
    }

    public function testAMissingRefundTokenIs404()
    {
        $response = $this->refund([ 'amount' => 25.0, 'reason' => 'test' ]);

        $this->assertSame(404, $response->getStatus() ?: 200);
        $this->assertSame('ACCEPTED', $this->orderStatus());
    }

    public function testAMismatchedAmountIs409()
    {
        $response = $this->refund([ 'refundToken' => $this->refundToken, 'amount' => 20.0, 'reason' => 'test' ]);

        $this->assertSame(409, $response->getStatus() ?: 200);
        $this->assertSame('ACCEPTED', $this->orderStatus());
    }

    public function testANonAcceptedOrderIs409()
    {
        Query::update('orders', ['order_status' => 'PENDING'], ['order_id' => $this->orderId])->execute();

        $response = $this->refund($this->validBody());

        $this->assertSame(409, $response->getStatus() ?: 200);
    }

    public function testTheOwningProductRefundsTheOrder()
    {
        $data = $this->assertJsonOk($this->refund($this->validBody()));

        $this->assertSame('REFUNDED', $data['status']);
        $this->assertSame('REFUNDED', $this->orderStatus());

        $refunds = Query::select('payments', ['pay_id'], [
            'order_id' => $this->orderId,
            'pay_type' => 'REFUND',
        ])->execute();
        $this->assertCount(1, $refunds);
    }

    public function testRefundingTwiceIsHarmless()
    {
        $this->assertJsonOk($this->refund($this->validBody()));

        // Second call: the order is no longer ACCEPTED, so it is refused
        // before Payment::refund() is reached. No second refund payment.
        $response = $this->refund($this->validBody());
        $this->assertSame(409, $response->getStatus() ?: 200);

        $refunds = Query::select('payments', ['pay_id'], [
            'order_id' => $this->orderId,
            'pay_type' => 'REFUND',
        ])->execute();
        $this->assertCount(1, $refunds);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd ~/Workbench/catlab-accounts && vendor/bin/phpunit -c phpunit.integration.xml --filter OrdersRefundApiTest`
Expected: FAIL — the route does not exist (404 on every case, so the 401/409/200 assertions fail).

- [ ] **Step 3: Register the route**

In `src/Accounts/Module.php`, directly after the `POST api/1.0/users/{id}/orders` route (~line 595):

```php
        // Refunding is a PRODUCT action, and client credentials alone are
        // deliberately not enough: the body must carry the order's refund
        // token, which no GET endpoint returns. Money moves here.
        $router->post('api/1.0/orders/{id}/refund', '\Accounts\Controllers\API\Orders@refund')->filter('client-credentials');
```

- [ ] **Step 4: Implement the endpoint**

In `src/Accounts/Controllers/API/Orders.php`, add after `get()`:

```php
    /**
     * Refund an order. A PRODUCT action behind client credentials, plus the
     * order's refund token as a second factor: the credentials live in the
     * product's environment, the token in its database, so a leak of either
     * one on its own refunds nothing.
     *
     * Money moves here (Payment::refund() calls the gateway), so every
     * guard fails closed. A wrong token answers exactly like an unknown
     * order: the endpoint must not confirm which orders exist.
     *
     * @param $orderId
     * @return Response
     */
    public function refund($orderId)
    {
        $order = MapperFactory::getOrderMapper()->getFromId(intval($orderId));

        if (!ClientCredentials::verify($this->request)) {
            return Response::json([ 'error' => 'unauthorized' ])->setStatus(401);
        }

        $notFound = Response::json([ 'error' => 'Order not found.' ])->setStatus(404);

        $allowed = $order && $order->getProduct()
            && intval($order->getProduct()->getId()) === ClientCredentials::getProductId();

        if (!$allowed) {
            return $notFound;
        }

        $json = $this->request->getData();
        $refundToken = isset($json['refundToken']) ? (string) $json['refundToken'] : '';

        if (!$order->getRefundToken() || !hash_equals($order->getRefundToken(), $refundToken)) {
            return $notFound;
        }

        if ($order->getStatus() !== Order::STATUS_ACCEPTED) {
            return Response::json([ 'error' => 'Order is not refundable.' ])->setStatus(409);
        }

        // Bind the amount the caller believed it was refunding, so a stale
        // confirmation screen cannot refund a different sum than it showed.
        $amount = isset($json['amount']) ? $json['amount'] : null;
        if (!is_numeric($amount)
            || (int) round($amount * 100) !== (int) round($order->getPriceToPay() * 100)) {
            return Response::json([ 'error' => 'Amount does not match the order.' ])->setStatus(409);
        }

        $payment = $order->getPayment();
        if (!$payment) {
            return Response::json([ 'error' => 'Order has no payment to refund.' ])->setStatus(409);
        }

        $reason = isset($json['reason']) ? mb_substr((string) $json['reason'], 0, 255) : 'api';
        $payment->refund([ 'reason' => $reason ]);

        $refunded = MapperFactory::getOrderMapper()->getFromId(intval($orderId));

        return Response::json([
            'id' => $refunded->getId(),
            'status' => $refunded->getStatus()
        ]);
    }
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `cd ~/Workbench/catlab-accounts && vendor/bin/phpunit -c phpunit.integration.xml --filter OrdersRefundApiTest`
Expected: PASS (10 tests)

- [ ] **Step 6: Run the full accounts suite**

Run: `cd ~/Workbench/catlab-accounts && vendor/bin/phpunit -c phpunit.integration.xml && vendor/bin/phpunit`
Expected: PASS, no regressions.

- [ ] **Step 7: Commit**

```bash
cd ~/Workbench/catlab-accounts
git add src/Accounts/Module.php src/Accounts/Controllers/API/Orders.php tests/integration/Api/OrdersRefundApiTest.php
git commit -m "Expose order refunds on the product API behind a refund token"
```

---

### Task 3: Throttle refunds per product

**Files:**
- Modify: `src/Accounts/Controllers/API/Orders.php` (`refund()`, add throttle check)
- Test: `tests/integration/Api/OrdersRefundThrottleTest.php`

**Interfaces:**
- Consumes: the `refund()` endpoint from Task 2.
- Produces: a `429` response when a product exceeds 10 refunds in an hour. No new public methods.

- [ ] **Step 1: Write the failing test**

Create `tests/integration/Api/OrdersRefundThrottleTest.php`:

```php
<?php

namespace Tests\Integration\Api;

use Neuron\DB\Query;
use Tests\Integration\Harness\Fixtures;
use Tests\Integration\Harness\IntegrationRequestCase;

/**
 * Even with both the client credentials and the refund tokens, nobody
 * should be able to empty the account in one script. Ten refunds per
 * product per hour, then 429 -- enough for a busy box office, slow enough
 * to notice.
 */
class OrdersRefundThrottleTest extends IntegrationRequestCase
{
    /** @var int */
    private $productId;
    /** @var string */
    private $clientId;
    /** @var int */
    private $profileId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productId = Fixtures::product();
        $this->clientId = Fixtures::oauthClient($this->productId);

        $userId = Fixtures::user();
        $this->profileId = Fixtures::personalProfile($userId);
        // The InvoicePaymentGateway used by Fixtures::payment() below is only
        // offered to vat-deductible, invoice-capable profiles (see
        // PaymentGateways::getGateways); without this, Payment::refund()
        // cannot resolve the gateway by class name and throws.
        Fixtures::billingDetails($this->profileId);
    }

    /**
     * @return array [orderId, refundToken]
     */
    private function refundableOrder(int $index): array
    {
        $token = str_pad('tok' . $index, 24, 'x');

        $orderId = Fixtures::order($this->profileId, [
            'order_product_id' => $this->productId,
            'order_status' => 'ACCEPTED',
            'order_price' => 5.0,
            'order_discount' => 0.0,
            'order_currency' => 'EUR',
            'order_net_value_eur' => 500,
            'order_refund_token' => $token,
        ]);

        Fixtures::payment($orderId, [
            'pay_type' => 'PAYMENT',
            'pay_status' => 'ACCEPTED',
            'pay_gateway' => 'Accounts\\Models\\PaymentGateways\\InvoicePaymentGateway',
            'pay_tx_ref' => 'TX-' . $orderId,
        ]);

        return [ $orderId, $token ];
    }

    private function refund(int $orderId, string $token): \Neuron\Net\Response
    {
        return $this->request('POST', '/api/1.0/orders/' . $orderId . '/refund', [
            'body' => json_encode([ 'refundToken' => $token, 'amount' => 5.0, 'reason' => 'test' ]),
            'headers' => [
                'Content-type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($this->clientId . ':test-secret'),
            ],
        ]);
    }

    public function testTheEleventhRefundInAnHourIs429()
    {
        for ($i = 0; $i < 10; $i ++) {
            list ($orderId, $token) = $this->refundableOrder($i);
            $response = $this->refund($orderId, $token);
            $this->assertSame(200, $response->getStatus() ?: 200, 'refund ' . $i . ' should succeed');
        }

        list ($orderId, $token) = $this->refundableOrder(10);
        $response = $this->refund($orderId, $token);

        $this->assertSame(429, $response->getStatus() ?: 200);

        $row = Query::select('orders', ['order_status'], ['order_id' => $orderId])->execute();
        $this->assertSame('ACCEPTED', $row[0]['order_status']);
    }

    public function testOlderRefundsDoNotCountTowardsTheHour()
    {
        for ($i = 0; $i < 10; $i ++) {
            list ($orderId, $token) = $this->refundableOrder($i);
            $this->refund($orderId, $token);
        }

        // Age the refund payments out of the window.
        $ageOut = new Query('UPDATE payments SET created_at = DATE_SUB(NOW(), INTERVAL 3 HOUR) WHERE pay_type = ?');
        $ageOut->bindValue(1, 'REFUND');
        $ageOut->execute();

        list ($orderId, $token) = $this->refundableOrder(11);
        $response = $this->refund($orderId, $token);

        $this->assertSame(200, $response->getStatus() ?: 200);
    }

    public function testAnotherProductHasItsOwnBudget()
    {
        for ($i = 0; $i < 10; $i ++) {
            list ($orderId, $token) = $this->refundableOrder($i);
            $this->refund($orderId, $token);
        }

        $otherProduct = Fixtures::product();
        $otherClient = Fixtures::oauthClient($otherProduct);
        $otherOrderId = Fixtures::order($this->profileId, [
            'order_product_id' => $otherProduct,
            'order_status' => 'ACCEPTED',
            'order_price' => 5.0,
            'order_discount' => 0.0,
            'order_currency' => 'EUR',
            'order_net_value_eur' => 500,
            'order_refund_token' => 'othertoken0123456789abcd',
        ]);
        Fixtures::payment($otherOrderId, [
            'pay_type' => 'PAYMENT',
            'pay_status' => 'ACCEPTED',
            'pay_gateway' => 'Accounts\\Models\\PaymentGateways\\InvoicePaymentGateway',
            'pay_tx_ref' => 'TX-' . $otherOrderId,
        ]);

        $response = $this->request('POST', '/api/1.0/orders/' . $otherOrderId . '/refund', [
            'body' => json_encode([ 'refundToken' => 'othertoken0123456789abcd', 'amount' => 5.0, 'reason' => 'test' ]),
            'headers' => [
                'Content-type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($otherClient . ':test-secret'),
            ],
        ]);

        $this->assertSame(200, $response->getStatus() ?: 200);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd ~/Workbench/catlab-accounts && vendor/bin/phpunit -c phpunit.integration.xml --filter OrdersRefundThrottleTest`
Expected: FAIL on `testTheEleventhRefundInAnHourIs429` — the eleventh refund returns 200.

- [ ] **Step 3: Add the throttle**

In `src/Accounts/Controllers/API/Orders.php`, add the constant at the top of the class:

```php
    /** Refunds one product may make per hour before the API says 429. */
    const REFUNDS_PER_HOUR = 10;
```

Add the counting helper at the bottom of the class:

```php
    /**
     * Accepted refunds this product made in the last hour. Counted from the
     * payments themselves rather than a counter table, so it stays true
     * even when a refund is made from the admin panel.
     *
     * @param int $productId
     * @return int
     */
    private function countRecentRefunds($productId)
    {
        $query = new Query("
            SELECT
                COUNT(*) AS total
            FROM
                payments
            INNER JOIN
                orders ON orders.order_id = payments.order_id
            WHERE
                payments.pay_type = ?
                AND orders.order_product_id = ?
                AND payments.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");

        $query->bindValue(1, Payment::TYPE_REFUND);
        $query->bindValue(2, $productId);

        $result = $query->execute();

        return count($result) === 0 ? 0 : intval($result[0]['total']);
    }
```

Add the imports at the top of the file:

```php
use Accounts\Models\Payment;
use Neuron\DB\Query;
```

In `refund()`, directly **after** the `$payment = $order->getPayment();` guard and **before** `$payment->refund(...)`:

```php
        if ($this->countRecentRefunds(ClientCredentials::getProductId()) >= self::REFUNDS_PER_HOUR) {
            return Response::json([ 'error' => 'Too many refunds, try again later.' ])->setStatus(429);
        }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `cd ~/Workbench/catlab-accounts && vendor/bin/phpunit -c phpunit.integration.xml --filter OrdersRefundThrottleTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Run the full accounts suite**

Run: `cd ~/Workbench/catlab-accounts && vendor/bin/phpunit -c phpunit.integration.xml && vendor/bin/phpunit`
Expected: PASS, including `OrdersRefundApiTest`.

- [ ] **Step 6: Commit and open the PR**

```bash
cd ~/Workbench/catlab-accounts
git add src/Accounts/Controllers/API/Orders.php tests/integration/Api/OrdersRefundThrottleTest.php
git commit -m "Throttle API refunds to 10 per product per hour"
git push -u origin feature/order-refund-api
gh pr create --base develop --title "Order refund API" --body "Exposes the existing Payment::refund() on the product API, guarded by a new per-order refund token plus a 10/hour throttle. See catlab-events docs/superpowers/specs/2026-08-29-order-refund-button-design.md."
```

**STOP: this must be merged and deployed before Phase 2.** Events cannot get refund tokens until accounts is live.

---

# Phase 2 — laravel-catlab-accounts

Work in `~/Workbench/laravel-catlab-accounts`.

---

### Task 4: `refundOrder()` on the API client

**Files:**
- Modify: `src/ApiClient.php` (add after `getOrder()`, ~line 176)

**Interfaces:**
- Consumes: `POST api/1.0/orders/{id}/refund` from Task 2.
- Produces: `ApiClient::refundOrder($orderId, $refundToken, $amount, $reason = 'api'): array` returning the decoded `['id' => int, 'status' => string]`. Throws `GuzzleHttp\Exception\GuzzleException` on any non-2xx (the caller maps 404/409/429), and `\LogicException` on an undecodable body.

- [ ] **Step 1: Add the method**

This package has no test suite; it is exercised through events in Phase 3. In `src/ApiClient.php`, after `getOrder()`:

```php
    /**
     * Refund an order.
     *
     * Authenticates as the product (client credentials) AND carries the
     * order's refund token, which the create call returned once and no GET
     * returns: the product's environment and its database must both be
     * intact for a refund to be possible.
     *
     * Money moves on the other side of this call, so a timeout is NOT a
     * failure -- the caller must re-read the order rather than assume.
     *
     * @param int|string $orderId
     * @param string $refundToken as returned in `refundToken` by createOrder()
     * @param float $amount the order total the caller believes it is refunding
     * @param string $reason recorded on accounts' payment log
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException on any non-2xx response
     */
    public function refundOrder($orderId, $refundToken, $amount, $reason = 'api')
    {
        $client = $this->getHttpClient();

        $url = $this->getUrl('orders/' . $orderId . '/refund');

        $headers = $this->getProductAuthorizationHeaders();

        $res = $client->post(
            $url,
            [
                'headers' => $headers,
                'json' => [
                    'refundToken' => $refundToken,
                    'amount' => $amount,
                    'reason' => $reason
                ]
            ]
        );

        $data = json_decode($res->getBody(), true);
        if (!$data) {
            throw new \LogicException("Could not decode refund order json api request: " . $res->getBody());
        }

        return $data;
    }
```

- [ ] **Step 2: Verify it parses**

Run: `cd ~/Workbench/laravel-catlab-accounts && php -l src/ApiClient.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit and tag**

```bash
cd ~/Workbench/laravel-catlab-accounts
git add src/ApiClient.php
git commit -m "Add refundOrder() for the accounts order refund endpoint"
git push origin HEAD
git tag v4.2.0
git push origin v4.2.0
```

**STOP: the tag must be pushed before Phase 3** — events resolves this package from GitHub tags (`composer.lock` currently pins `v4.1.0`).

---

# Phase 3 — catlab-events

Work in `~/Workbench/catlab-events` on the branch created for this plan:
`git checkout feature/order-refund-button`

---

### Task 5: Store the refund token on new orders

**Files:**
- Modify: `composer.json` (bump the client constraint)
- Create: `database/migrations/2026_08_29_120000_add_refund_token_to_orders.php`
- Modify: `app/Http/Controllers/EventController.php:820-823` (capture `refundToken`)
- Modify: `tests/Integration/Fakes/FakeCatLabApiClient.php` (return a token from `createOrder`, add `refundOrder`)
- Test: `tests/Integration/OrderRefundTest.php`

**Interfaces:**
- Consumes: `refundToken` from the accounts create response (Task 1); `ApiClient::refundOrder()` (Task 4).
- Produces: `orders.refund_token` column on events orders; `FakeCatLabApiClient::$refundOrderCalls` (array of `['orderId', 'refundToken', 'amount', 'reason']`), `FakeCatLabApiClient::$refundOrderException` (`\Throwable|null`), `FakeCatLabApiClient::$refundStatus` (string, default `'REFUNDED'`).

- [ ] **Step 1: Bump the dependency**

In `composer.json`, change the accounts client constraint:

```json
        "catlabinteractive/laravel-catlab-accounts": "~4.2",
```

Run: `docker exec catlab-events composer update catlabinteractive/laravel-catlab-accounts --no-interaction`
Expected: resolves `v4.2.0`.

- [ ] **Step 2: Write the failing test**

Create `tests/Integration/OrderRefundTest.php`:

```php
<?php

namespace Tests\Integration;

use App\Models\Order;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * Refunding an order needs the refund token accounts hands out once, at
 * order creation. Without it stored here, the refund endpoint answers 404
 * -- that is the whole point of the second factor.
 */
class OrderRefundTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    public function testTheRefundTokenIsStoredWhenAPaidOrderIsCreated()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 10.0);
        $user = $this->createUser();

        $this->actingAs($user)
            ->post("/events/{$event->id}/register/{$category->id}/process");

        $order = Order::query()->first();

        $this->assertNotNull($order, 'a paid order should have been created');
        $this->assertSame($this->catlabApi->nextRefundToken, $order->refund_token);
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `docker exec catlab-events vendor/bin/phpunit -c phpunit.integration.xml --filter OrderRefundTest`
Expected: FAIL — `nextRefundToken` is undefined and `refund_token` is not a column.

- [ ] **Step 4: Add the migration**

Create `database/migrations/2026_08_29_120000_add_refund_token_to_orders.php`:

```php
<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRefundTokenToOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {

            $table->string('refund_token')->after('catlab_order_id')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('refund_token');
        });
    }
}
```

- [ ] **Step 5: Extend the fake client**

In `tests/Integration/Fakes/FakeCatLabApiClient.php`, add the properties beside the existing ones:

```php
    public $refundOrderCalls = [];
    public $nextRefundToken = 'faketoken0123456789abcd';
    public $refundStatus = 'REFUNDED';

    /** @var \Throwable|null thrown by refundOrder() to simulate accounts failing (409, 429, timeout, ...) */
    public $refundOrderException = null;
```

Add `refundToken` to the `createOrder()` return:

```php
        return [
            'id' => $this->nextOrderId,
            'payUrl' => 'https://pay.example.com/order/' . $this->nextOrderId,
            'refundToken' => $this->nextRefundToken,
        ];
```

And add the method:

```php
    public function refundOrder($orderId, $refundToken, $amount, $reason = 'api')
    {
        if ($this->refundOrderException) {
            throw $this->refundOrderException;
        }

        $this->refundOrderCalls[] = [
            'orderId' => $orderId,
            'refundToken' => $refundToken,
            'amount' => $amount,
            'reason' => $reason,
        ];

        $this->orderStatus = $this->refundStatus;

        return [ 'id' => $orderId, 'status' => $this->refundStatus ];
    }
```

- [ ] **Step 6: Capture the token on order creation**

In `app/Http/Controllers/EventController.php`, in the block that stores the accounts order (~line 820):

```php
            $order->catlab_order_id = $orderData['id'];
            $order->pay_url = $orderData['payUrl'];

            // Second factor for refunding this order later. Accounts returns
            // it once, here: no GET endpoint hands it back.
            if (isset($orderData['refundToken'])) {
                $order->refund_token = $orderData['refundToken'];
            }

            $order->save();
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `docker exec catlab-events vendor/bin/phpunit -c phpunit.integration.xml --filter OrderRefundTest`
Expected: PASS (1 test)

- [ ] **Step 8: Commit**

```bash
cd ~/Workbench/catlab-events
git add composer.json composer.lock database/migrations/2026_08_29_120000_add_refund_token_to_orders.php app/Http/Controllers/EventController.php tests/Integration/Fakes/FakeCatLabApiClient.php tests/Integration/OrderRefundTest.php
git commit -m "Store the accounts refund token when a paid order is created"
```

---

### Task 6: The refund confirm page

**Files:**
- Create: `app/Http/Controllers/Admin/RefundController.php`
- Create: `resources/views/admin/orders/refund.blade.php`
- Modify: `routes/web.php` (admin group, beside the waiting list routes)
- Modify: `app/Http/Controllers/Admin/OrderController.php` (add `getTableForResourceCollection()`)
- Test: `tests/Integration/OrderRefundTest.php` (extend)

**Interfaces:**
- Consumes: `orders.refund_token` (Task 5).
- Produces: `GET admin/orders/{order}/refund` → the confirm page, which
  renders either the confirmation form or an explanation of why this order
  cannot be refunded here. `Admin\RefundController::isRefundable(Order $order): bool`
  (public static) and `Admin\RefundController::getOrderInOrganisation($orderId): Order`
  (protected, 404s outside the active organisation).

- [ ] **Step 1: Write the failing test**

Append to `tests/Integration/OrderRefundTest.php` (inside the class):

```php
    /**
     * @return \App\Models\User
     */
    private function createAdmin()
    {
        $admin = $this->createUser();
        $admin->admin = 1;
        $admin->save();

        return $admin;
    }

    /**
     * @param float $price
     * @return Order
     */
    private function createRefundableOrder($price = 25.0)
    {
        $organisation = $this->createOrganisation();
        $event = $this->createEvent($organisation);
        $category = $this->createTicketCategory($event, $price);
        $user = $this->createUser();

        $order = new Order();
        $order->event()->associate($event);
        $order->user()->associate($user);
        $order->ticketCategory()->associate($category);
        $order->state = Order::STATE_ACCEPTED;
        $order->catlab_order_id = 4242;
        $order->refund_token = 'faketoken0123456789abcd';
        $order->save();

        return $order;
    }

    public function testTheConfirmPageShowsTheLiveAmountAndSendsNothing()
    {
        $order = $this->createRefundableOrder();
        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id);

        $response = $this->actingAs($admin)->get("/admin/orders/{$order->id}/refund");

        $response->assertStatus(200);
        // Price comes from accounts (FakeCatLabApiClient::getOrder), never
        // from the local ticket price.
        $response->assertSee('10,00');
        $response->assertSee('TEST-4242');
        $this->assertCount(0, $this->catlabApi->refundOrderCalls);
    }

    public function testAnOrderWithoutARefundTokenExplainsWhereToRefundIt()
    {
        $order = $this->createRefundableOrder();
        $order->refund_token = null;
        $order->save();

        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id);

        $response = $this->actingAs($admin)->get("/admin/orders/{$order->id}/refund");

        // The table action renders on every row, so this page has to explain
        // itself rather than 404: these orders predate the refund token and
        // are refunded from the accounts admin panel.
        $response->assertStatus(200);
        $response->assertSee('accounts');
        $response->assertDontSee('Terugbetalen</button>', false);
    }

    public function testACancelledOrderCannotBeRefunded()
    {
        $order = $this->createRefundableOrder();
        $order->state = Order::STATE_CANCELLED;
        $order->save();

        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id);

        $response = $this->actingAs($admin)->get("/admin/orders/{$order->id}/refund");

        $response->assertStatus(200);
        $response->assertDontSee('Terugbetalen</button>', false);
    }

    public function testAnAdminOfAnotherOrganisationGets404()
    {
        $order = $this->createRefundableOrder();

        // Global `admin` flag, but the active organisation is a different one.
        $admin = $this->createAdmin();
        $admin->organisations()->attach($this->createOrganisation()->id);

        $this->actingAs($admin)->get("/admin/orders/{$order->id}/refund")->assertStatus(404);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker exec catlab-events vendor/bin/phpunit -c phpunit.integration.xml --filter OrderRefundTest`
Expected: FAIL — the route does not exist (404 everywhere, so the 200 case fails).

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Admin/RefundController.php`:

```php
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
use App\Models\Order;

/**
 * Class RefundController
 *
 * Refunding a paid order through accounts. The money really moves, so the
 * admin passes a confirmation page that shows the live amount and asks
 * them to type the order reference first.
 *
 * @package App\Http\Controllers\Admin
 */
class RefundController extends Controller
{
    /**
     * Whether this order can be refunded through the API at all.
     *
     * Orders created before the refund token existed cannot: accounts
     * answers 404 without one. Those are refunded from the accounts admin
     * panel instead.
     *
     * The catlab_order_id check also covers free tickets: they never get an
     * accounts order, so there is no payment to refund and no separate
     * price check is needed.
     *
     * @param Order $order
     * @return bool
     */
    public static function isRefundable(Order $order)
    {
        return $order->state === Order::STATE_ACCEPTED
            && $order->catlab_order_id
            && $order->refund_token;
    }

    /**
     * Show the confirmation page. Sends nothing.
     *
     * The Charon table action renders on every order row, so an order that
     * cannot be refunded here gets an explanation rather than a 404 -- a
     * dead link would just look broken.
     *
     * @param $orderId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function refund($orderId)
    {
        $order = $this->getOrderInOrganisation($orderId);

        if (!self::isRefundable($order)) {
            return view('admin.orders.refund', [
                'order' => $order,
                'refundable' => false,
                'reference' => null,
                'amount' => null
            ]);
        }

        $orderData = $order->getOrderData(true);

        return view('admin.orders.refund', [
            'order' => $order,
            'refundable' => true,
            'reference' => isset($orderData['reference']) ? $orderData['reference'] : null,
            'amount' => isset($orderData['price']) ? $orderData['price'] : null
        ]);
    }

    /**
     * The order, or 404. Scoped to the acting admin's active organisation:
     * `admin` is a global flag, so without this an admin of one
     * organisation could refund another's order. This one really is a 404 --
     * the order is none of this admin's business.
     *
     * @param $orderId
     * @return Order
     */
    protected function getOrderInOrganisation($orderId)
    {
        /** @var Order $order */
        $order = Order::findOrFail($orderId);

        $organisation = \Auth::user()->getActiveOrganisation();
        if (!$order->event || !$organisation || $order->event->organisation_id !== $organisation->id) {
            abort(404);
        }

        return $order;
    }
}
```

- [ ] **Step 4: Create the view**

Create `resources/views/admin/orders/refund.blade.php`:

```blade
@extends('layouts/admin')

@section('content')

    <h2>Terugbetaling</h2>

    @if(!$refundable)

        <p class="alert alert-info">
            Deze order kan hier niet terugbetaald worden.
            @if($order->state !== \App\Models\Order::STATE_ACCEPTED)
                De order staat op <code>{{ $order->state }}</code>.
            @elseif(!$order->catlab_order_id)
                Er is geen betaling aan gekoppeld (gratis ticket).
            @else
                Ze dateert van voor de terugbetaalknop en moet in het
                accounts admin panel terugbetaald worden.
            @endif
        </p>

        <p>
            <a class="btn btn-secondary" href="{{ action('Admin\OrderController@index') }}">Terug</a>
        </p>

    @else

    <div class="alert alert-danger">
        <strong>Dit is definitief.</strong>
        Het geld gaat terug naar de koper en de transactiekosten krijgen we
        niet terug. Dit kan niet ongedaan gemaakt worden.
    </div>

    <table class="table" style="max-width: 600px;">
        <tr>
            <th style="width: 160px;">Koper</th>
            <td>{{ $order->user ? $order->user->email : '?' }}
                @if($order->group) ({{ $order->group->name }}) @endif
            </td>
        </tr>
        <tr>
            <th>Event</th>
            <td>{{ $order->event->name }}</td>
        </tr>
        <tr>
            <th>Referentie</th>
            <td><code>{{ $reference }}</code></td>
        </tr>
        <tr>
            <th>Bedrag</th>
            <td>&euro; {{ number_format($amount, 2, ',', '.') }}</td>
        </tr>
    </table>

    <form action="{{ action('Admin\RefundController@processRefund', [ $order->id ]) }}" method="post">
        {{ csrf_field() }}

        <div class="form-group" style="max-width: 400px;">
            <label for="reference">Typ <code>{{ $reference }}</code> om te bevestigen</label>
            <input type="text" class="form-control" id="reference" name="reference" autocomplete="off" />
        </div>

        <div class="form-group" style="max-width: 400px;">
            <label for="reason">Reden</label>
            <input type="text" class="form-control" id="reason" name="reason" maxlength="255" />
        </div>

        <p>
            <button type="submit" class="btn btn-danger" id="refund-submit" disabled>Terugbetalen</button>
            <a class="btn btn-secondary" href="{{ action('Admin\OrderController@index') }}">Annuleer</a>
        </p>
    </form>

    <script>
        (function () {
            var expected = {!! json_encode($reference) !!};
            var input = document.getElementById('reference');
            var button = document.getElementById('refund-submit');

            input.addEventListener('input', function () {
                button.disabled = input.value.trim() !== expected;
            });
        })();
    </script>

    @endif

@endsection
```

- [ ] **Step 5: Register the routes**

In `routes/web.php`, inside the admin group (after the `uitdb` routes, ~line 92):

```php
            Route::get('orders/{order}/refund', 'Admin\RefundController@refund');
            Route::post('orders/{order}/refund', 'Admin\RefundController@processRefund');
```

- [ ] **Step 6: Add the table action**

In `app/Http/Controllers/Admin/OrderController.php`, add the imports:

```php
use CatLab\CharonFrontend\Models\Table\ResourceAction;
use CatLab\Laravel\Table\Table;
use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Charon\Interfaces\Context as ContextContract;
```

and the method, after `index()`:

```php
    /**
     * @param Request $request
     * @param ResourceCollection $collection
     * @param ResourceDefinition $resourceDefinition
     * @param ContextContract $context
     * @return Table
     */
    public function getTableForResourceCollection(
        Request $request,
        ResourceCollection $collection,
        ResourceDefinition $resourceDefinition,
        ContextContract $context
    ): Table {
        $table = $this->traitGetTableForResourceCollection($request, $collection, $resourceDefinition, $context);

        $table->modelAction(
            (new ResourceAction('Admin\RefundController@refund', 'Terugbetalen'))
                ->setRouteParameters($this->getShowRouteParameters($request))
                ->setQueryParameters($this->getShowQueryParameters($request))
        );

        return $table;
    }
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `docker exec catlab-events vendor/bin/phpunit -c phpunit.integration.xml --filter OrderRefundTest`
Expected: PASS (5 tests)

- [ ] **Step 8: Commit**

```bash
cd ~/Workbench/catlab-events
git add app/Http/Controllers/Admin/RefundController.php app/Http/Controllers/Admin/OrderController.php resources/views/admin/orders/refund.blade.php routes/web.php tests/Integration/OrderRefundTest.php
git commit -m "Add the refund confirmation page to the admin panel"
```

---

### Task 7: Perform the refund

**Files:**
- Modify: `app/Http/Controllers/Admin/RefundController.php` (add `processRefund()`)
- Test: `tests/Integration/OrderRefundTest.php` (extend)

**Interfaces:**
- Consumes: `RefundController::getOrderInOrganisation()` and `RefundController::isRefundable()` (Task 6), `ApiClient::refundOrder()` (Task 4).
- Produces: `POST admin/orders/{order}/refund` → redirect to the order list with a flash `message`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Integration/OrderRefundTest.php`:

```php
    public function testRefundingCallsAccountsAndSyncsTheOrder()
    {
        $order = $this->createRefundableOrder();
        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id);

        $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/refund", [
                'reference' => 'TEST-4242',
                'reason' => 'klant kon niet komen'
            ])
            ->assertRedirect('/admin/orders');

        $this->assertCount(1, $this->catlabApi->refundOrderCalls);
        $call = $this->catlabApi->refundOrderCalls[0];
        $this->assertSame(4242, $call['orderId']);
        $this->assertSame('faketoken0123456789abcd', $call['refundToken']);
        $this->assertSame(10.0, $call['amount']);
        $this->assertSame('klant kon niet komen', $call['reason']);

        // State is read back from accounts, not assumed.
        $this->assertSame(Order::STATE_REFUNDED, $order->fresh()->state);
    }

    public function testAWrongTypedReferenceRefundsNothing()
    {
        $order = $this->createRefundableOrder();
        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id);

        $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/refund", [
                'reference' => 'TEST-9999',
                'reason' => 'oeps'
            ]);

        $this->assertCount(0, $this->catlabApi->refundOrderCalls);
        $this->assertSame(Order::STATE_ACCEPTED, $order->fresh()->state);
    }

    public function testRefundingFreesTheSeat()
    {
        $order = $this->createRefundableOrder();
        $eventDate = $order->event->eventDates()->first();
        $eventDate->max_tickets = 1;
        $eventDate->save();

        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id);

        $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/refund", [
                'reference' => 'TEST-4242',
                'reason' => 'test'
            ]);

        $this->assertSame(1, $order->event->fresh()->countAvailableTickets(true));
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker exec catlab-events vendor/bin/phpunit -c phpunit.integration.xml --filter OrderRefundTest`
Expected: FAIL — `processRefund` does not exist (500 / method not found).

- [ ] **Step 3: Implement `processRefund()`**

In `app/Http/Controllers/Admin/RefundController.php`, add the imports:

```php
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
```

and the method after `refund()`:

```php
    /**
     * Actually refund. Money moves on the accounts side.
     *
     * @param Request $request
     * @param $orderId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processRefund(Request $request, $orderId)
    {
        $order = $this->getOrderInOrganisation($orderId);

        $back = redirect(action('Admin\OrderController@index'));

        if (!self::isRefundable($order)) {
            return $back->with('message', 'Deze order kan hier niet terugbetaald worden.');
        }

        $orderData = $order->getOrderData(true);

        $reference = isset($orderData['reference']) ? $orderData['reference'] : null;

        // Re-checked here, not just in the browser: disabling the button in
        // JS is a convenience, never the control.
        if (!$reference || trim((string) $request->input('reference')) !== $reference) {
            return $back->with('message', 'De referentie klopte niet. Er is niets terugbetaald.');
        }

        $amount = isset($orderData['price']) ? $orderData['price'] : null;
        $reason = mb_substr((string) $request->input('reason'), 0, 255);

        $apiClient = app(\App\Services\CatLabApiClientFactory::class)->forUser($order->user);

        try {
            $apiClient->refundOrder($order->catlab_order_id, $order->refund_token, $amount, $reason ?: 'events admin');
        } catch (GuzzleException $e) {
            return $back->with('message', $this->describeFailure($order, $e));
        }

        // Read the state back from accounts rather than assuming it.
        $order->synchronize();

        return $back->with('message', 'Order ' . $reference . ' is terugbetaald.');
    }

    /**
     * Turn a failed refund call into something the admin can act on.
     *
     * A timeout is the dangerous one: the refund may well have gone
     * through, so we must not report failure and invite a second click. We
     * re-sync and report whatever accounts actually says.
     *
     * @param Order $order
     * @param GuzzleException $e
     * @return string
     */
    private function describeFailure(Order $order, GuzzleException $e)
    {
        $status = $e instanceof BadResponseException ? $e->getResponse()->getStatusCode() : null;

        \Log::warning('Order refund failed', [
            'order' => $order->id,
            'status' => $status,
            'error' => $e->getMessage()
        ]);

        if ($status === 429) {
            return 'Er zijn te veel terugbetalingen gebeurd in korte tijd. Probeer het over een uur opnieuw.';
        }

        if ($status === 409) {
            $order->synchronize();
            return 'Deze order kon niet meer terugbetaald worden. De status is opnieuw opgehaald.';
        }

        if ($status === 404 || $status === 401) {
            return 'De terugbetaling werd geweigerd. Controleer de order in accounts.';
        }

        // No response at all: timeout, connection error. The refund may have
        // happened. Never report failure here.
        $order->synchronize();

        return 'Onbekend resultaat: de terugbetaling is mogelijk wel doorgegaan. '
            . 'De status is opnieuw opgehaald; controleer de order in accounts als ze nog op '
            . $order->fresh()->state . ' staat.';
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `docker exec catlab-events vendor/bin/phpunit -c phpunit.integration.xml --filter OrderRefundTest`
Expected: PASS (8 tests)

- [ ] **Step 5: Commit**

```bash
cd ~/Workbench/catlab-events
git add app/Http/Controllers/Admin/RefundController.php tests/Integration/OrderRefundTest.php
git commit -m "Perform the refund and read the resulting state back from accounts"
```

---

### Task 8: Failure paths

**Files:**
- Test: `tests/Integration/OrderRefundTest.php` (extend)
- Modify: `app/Http/Controllers/Admin/RefundController.php` only if a test exposes a defect.

**Interfaces:**
- Consumes: `processRefund()` (Task 7), `FakeCatLabApiClient::$refundOrderException` (Task 5).
- Produces: nothing new; this task proves the error handling behaves.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Integration/OrderRefundTest.php`, and add these imports at the top of the file:

```php
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
```

```php
    public function testAThrottledRefundSaysToTryAgainLater()
    {
        $order = $this->createRefundableOrder();
        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id);

        $this->catlabApi->refundOrderException = new BadResponseException(
            'Too many refunds',
            new GuzzleRequest('POST', 'orders/4242/refund'),
            new GuzzleResponse(429, [], '{"error":"Too many refunds, try again later."}')
        );

        $response = $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/refund", [
                'reference' => 'TEST-4242',
                'reason' => 'test'
            ]);

        $response->assertRedirect('/admin/orders');
        $this->assertStringContainsString('te veel terugbetalingen', session('message'));
        $this->assertSame(Order::STATE_ACCEPTED, $order->fresh()->state);
    }

    public function testANoLongerRefundableOrderReportsAndResyncs()
    {
        $order = $this->createRefundableOrder();
        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id);

        $this->catlabApi->refundOrderException = new BadResponseException(
            'Order is not refundable.',
            new GuzzleRequest('POST', 'orders/4242/refund'),
            new GuzzleResponse(409, [], '{"error":"Order is not refundable."}')
        );
        // Accounts already considers it refunded; the re-sync must pick that up.
        $this->catlabApi->orderStatus = 'REFUNDED';

        $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/refund", [
                'reference' => 'TEST-4242',
                'reason' => 'test'
            ]);

        $this->assertStringContainsString('niet meer terugbetaald', session('message'));
        $this->assertSame(Order::STATE_REFUNDED, $order->fresh()->state);
    }

    public function testATimeoutIsNotReportedAsAFailure()
    {
        $order = $this->createRefundableOrder();
        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id);

        $this->catlabApi->refundOrderException = new ConnectException(
            'Connection timed out',
            new GuzzleRequest('POST', 'orders/4242/refund')
        );
        // The refund did go through on the other side.
        $this->catlabApi->orderStatus = 'REFUNDED';

        $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/refund", [
                'reference' => 'TEST-4242',
                'reason' => 'test'
            ]);

        $message = session('message');
        $this->assertStringContainsString('mogelijk wel doorgegaan', $message);
        $this->assertStringNotContainsString('mislukt', $message);
        // The re-sync found the truth.
        $this->assertSame(Order::STATE_REFUNDED, $order->fresh()->state);
    }
```

- [ ] **Step 2: Run the tests**

Run: `docker exec catlab-events vendor/bin/phpunit -c phpunit.integration.xml --filter OrderRefundTest`
Expected: PASS (11 tests). If any fail, fix `RefundController::describeFailure()` — the tests define the required behaviour, not the other way round.

- [ ] **Step 3: Run both full suites**

Run: `docker exec catlab-events vendor/bin/phpunit -c phpunit.integration.xml && docker exec catlab-events vendor/bin/phpunit`
Expected: PASS, no regressions.

- [ ] **Step 4: Check for dangling references**

Run: `grep -rn "RefundController" --include="*.php" --include="*.blade.php" app/ resources/ routes/ tests/`
Expected: only the controller, the two routes, the view's form action, the order table action, and the tests.

- [ ] **Step 5: Commit, push and open the PR**

```bash
cd ~/Workbench/catlab-events
git add tests/Integration/OrderRefundTest.php app/Http/Controllers/Admin/RefundController.php
git commit -m "Cover the refund failure paths, including that a timeout is not a failure"
git push -u origin feature/order-refund-button
gh pr create --base master --title "Order refund button" --body "Adds a refund button to the events admin panel, backed by the new accounts refund endpoint. Requires accounts deployed and laravel-catlab-accounts v4.2.0. See docs/superpowers/specs/2026-08-29-order-refund-button-design.md. Needs php artisan migrate."
```

---

## Deployment checklist

1. `catlab-accounts` merged and deployed, `php commands/migrate.php` run.
2. `laravel-catlab-accounts` `v4.2.0` tagged and pushed.
3. `catlab-events` merged, `php artisan migrate` run.
4. Verify on production with one real low-value order before announcing the button: it should refund, free the seat, and mail the buyer the cancellation.

Only orders created **after** step 1 carry a refund token. Older orders show no button by design and are refunded from the accounts admin panel.
