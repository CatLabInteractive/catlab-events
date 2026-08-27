# Integration Tests Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** HTTP-level integration tests proving the public site renders and the full ticket purchase flow (free + paid) works against real MySQL, with all external services faked.

**Architecture:** A separate `Integration` phpunit suite runs inside the docker-compose `webserver` container against a dedicated `catlab_events_test` database on the `mysql-db` service. The only production change is a `CatLabApiClientFactory` seam replacing three direct `new ApiClient(...)` calls, so tests can swap in a fake. Eukles tracking is faked by rebinding its container interface.

**Tech Stack:** Laravel 9, PHPUnit 8.5, MySQL 8 (docker-compose), PHP 8.5 container.

## Global Constraints

- Branch: `feature/integration-tests` (based on `upgrade/php-8.5` — the docker image needs PHP 8.5).
- Spec: `docs/superpowers/specs/2026-07-24-integration-tests-design.md`.
- Integration tests live in `tests/Integration/` and run ONLY via `phpunit.integration.xml`; the default `phpunit.xml` (Unit/Feature suites) must stay DB-free and green when run on the host: `vendor/bin/phpunit --testsuite Unit`.
- Integration runs happen inside docker: `bin/integration-tests.sh` (never against the dev database `catlab-events`; always `catlab_events_test`).
- No test may perform external HTTP. Fakes are hand-written classes (the bundled mockery 0.9 is too old — do not use it; PHPUnit `createMock` is fine for unit tests).
- PHPUnit 8 syntax: no attributes, docblock annotations only.
- Production code changes are limited to: `App\Services\CatLabApiClientFactory` (new), the three `new ApiClient` call sites, and — only if a test exposes a blocking bug — a minimal fix agreed with the user (STOP and report first).
- Commit after each task with the trailer:
  `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>` and
  `Claude-Session: https://claude.ai/code/session_013HqprN6hPwwHLXeCRbGdgo`

---

### Task 1: Integration harness (config, runner script, base test case)

**Files:**
- Create: `phpunit.integration.xml`
- Create: `bin/integration-tests.sh` (chmod +x)
- Create: `tests/Integration/IntegrationTestCase.php`
- Test: `tests/Integration/HarnessTest.php`

**Interfaces:**
- Produces: `Tests\Integration\IntegrationTestCase` — abstract, `use RefreshDatabase`; all integration tests extend it. Runner: `bin/integration-tests.sh [phpunit args]`.

- [ ] **Step 1: Write the failing test**

`tests/Integration/HarnessTest.php`:

```php
<?php

namespace Tests\Integration;

class HarnessTest extends IntegrationTestCase
{
    public function testRunsAgainstDedicatedTestDatabase()
    {
        $this->assertEquals('catlab_events_test', \DB::connection()->getDatabaseName());
    }

    public function testStatusEndpointResponds()
    {
        $this->get('/status')->assertStatus(200);
    }
}
```

- [ ] **Step 2: Write the harness files**

`tests/Integration/IntegrationTestCase.php`:

```php
<?php

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    use RefreshDatabase;
}
```

`phpunit.integration.xml` (bootstrap and converters copied from `phpunit.xml`):

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit backupGlobals="false"
         backupStaticAttributes="false"
         bootstrap="bootstrap/autoload.php"
         colors="true"
         convertErrorsToExceptions="true"
         convertNoticesToExceptions="true"
         convertWarningsToExceptions="true"
         processIsolation="false"
         stopOnFailure="false">
    <testsuites>
        <testsuite name="Integration">
            <directory suffix="Test.php">./tests/Integration</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="QUEUE_DRIVER" value="sync"/>
        <!-- Dedicated test database on the docker-compose mysql service.
             DB_USERNAME=root because the runner creates the schema; the root
             password equals DB_PASSWORD from .env (see docker-compose.yml). -->
        <env name="DB_HOST" value="mysql-db"/>
        <env name="DB_PORT" value="3306"/>
        <env name="DB_DATABASE" value="catlab_events_test"/>
        <env name="DB_USERNAME" value="root"/>
        <!-- Keep auth local (actingAs works; no OAuth redirect) -->
        <env name="CATLAB_CLIENT_ID" value=""/>
        <!-- No external services -->
        <env name="ERRBIT_ENABLED" value="false"/>
        <env name="VALID_DOMAINS" value=""/>
        <env name="EUKLES_SERVER" value=""/>
    </php>
</phpunit>
```

`bin/integration-tests.sh`:

```bash
#!/usr/bin/env bash
# Runs the Integration testsuite inside the docker-compose webserver container
# against a dedicated catlab_events_test database (dev data is never touched).
set -euo pipefail
cd "$(dirname "$0")/.."

docker compose up -d mysql-db webserver

# Wait for MySQL to accept connections, then ensure the test db exists.
docker compose exec -T mysql-db bash -c \
  'for i in $(seq 1 30); do mysqladmin ping -uroot -p"$MYSQL_ROOT_PASSWORD" --silent && break; sleep 1; done'
docker compose exec -T mysql-db bash -c \
  'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS catlab_events_test"'

docker compose exec -T webserver php vendor/bin/phpunit -c phpunit.integration.xml "$@"
```

Then: `chmod +x bin/integration-tests.sh`

- [ ] **Step 3: Run and verify**

Run: `bin/integration-tests.sh`
Expected: `RefreshDatabase` runs `migrate:fresh` on `catlab_events_test`, then both tests PASS.

If a migration fails on the clean MySQL 8 database, STOP: report the failing migration and error to the user before changing any migration file (fixing it is in scope only after agreement — the spec counts "migrations run from scratch" as part of what we're proving).

- [ ] **Step 4: Verify the host unit suite is untouched**

Run (on host): `vendor/bin/phpunit --testsuite Unit`
Expected: OK (7 tests) — no DB required.

- [ ] **Step 5: Commit**

```bash
git add phpunit.integration.xml bin/integration-tests.sh tests/Integration/
git commit -m "Add integration test harness running in docker against a test database"
```

---

### Task 2: Event fixtures + smoke tests

**Files:**
- Create: `tests/Integration/Concerns/CreatesEventFixtures.php`
- Test: `tests/Integration/SmokeTest.php`

**Interfaces:**
- Consumes: `IntegrationTestCase` (Task 1).
- Produces: trait `Tests\Integration\Concerns\CreatesEventFixtures` with:
  - `createOrganisation(): \App\Models\Organisation`
  - `createEvent(\App\Models\Organisation $organisation): \App\Models\Event` — published, public, open registration, no team requirement, no campaign, starts in 30 days
  - `createTicketCategory(\App\Models\Event $event, float $price): \App\Models\TicketCategory`
  - `createUser(): \App\Models\User`

- [ ] **Step 1: Write the failing test**

`tests/Integration/SmokeTest.php`:

```php
<?php

namespace Tests\Integration;

use Tests\Integration\Concerns\CreatesEventFixtures;

class SmokeTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    public function testHomepageRenders()
    {
        $this->get('/')->assertStatus(200);
    }

    public function testEventPageRenders()
    {
        $event = $this->createEvent($this->createOrganisation());
        $this->createTicketCategory($event, 10.0);

        $response = $this->get('/events/' . $event->id);

        $response->assertStatus(200);
        $response->assertSee($event->name);
    }

    public function testTicketSelectionPageRendersForGuests()
    {
        $event = $this->createEvent($this->createOrganisation());
        $this->createTicketCategory($event, 10.0);

        // Guests get the "log in to register" explanation page.
        $this->get('/events/' . $event->id . '/register')->assertStatus(200);
    }
}
```

- [ ] **Step 2: Write the fixtures trait**

`tests/Integration/Concerns/CreatesEventFixtures.php`:

```php
<?php

namespace Tests\Integration\Concerns;

use App\Models\Event;
use App\Models\Organisation;
use App\Models\TicketCategory;
use App\Models\User;
use Carbon\Carbon;

trait CreatesEventFixtures
{
    protected function createOrganisation(): Organisation
    {
        $organisation = new Organisation();
        $organisation->name = 'Test organisation';
        $organisation->save();

        return $organisation;
    }

    protected function createEvent(Organisation $organisation): Event
    {
        $event = new Event();
        $event->organisation()->associate($organisation);
        $event->name = 'Test quiz night';
        $event->is_published = true;
        $event->startDate = Carbon::now()->addDays(30);
        $event->endDate = Carbon::now()->addDays(30)->addHours(4);
        $event->visbility = 'public'; // (sic) column name has a typo in the schema
        $event->registration = 'open';
        $event->save();

        return $event;
    }

    protected function createTicketCategory(Event $event, float $price): TicketCategory
    {
        $category = new TicketCategory();
        $category->event()->associate($event);
        $category->name = $price > 0 ? 'Paid ticket' : 'Free ticket';
        $category->price = $price;
        $category->save();

        return $category;
    }

    protected function createUser(): User
    {
        $user = new User();
        $user->name = 'Test User';
        $user->email = uniqid('test', true) . '@example.com';
        $user->password = bcrypt('secret');
        $user->save();

        return $user;
    }
}
```

- [ ] **Step 3: Run and verify**

Run: `bin/integration-tests.sh --filter SmokeTest`
Expected: PASS (3 tests).

If a NOT NULL column or a blade view demands an attribute the fixture doesn't set, add that column to the fixture with an obviously-fake value — keep the trait the single place fixtures are defined. If a view itself crashes (not a fixture gap), STOP and report: that's a real rendering bug the smoke test just caught.

- [ ] **Step 4: Commit**

```bash
git add tests/Integration/
git commit -m "Add event fixtures and public-page smoke tests"
```

---

### Task 3: CatLabApiClientFactory seam (only production change)

**Files:**
- Create: `app/Services/CatLabApiClientFactory.php`
- Modify: `app/Http/Controllers/EventController.php:748` (`$client = new ApiClient($user);`)
- Modify: `app/Models/Order.php:192-206` (`getOrderData`)
- Modify: `app/Listeners/SendEmail.php` (two sites: `sendConfirmationEmail`, `sendCancellationEmail` — both do `new ApiClient($user)`)
- Test: `tests/Unit/Services/CatLabApiClientFactoryTest.php`

**Interfaces:**
- Produces: `App\Services\CatLabApiClientFactory::forUser(?\App\Models\User $user = null): \CatLab\Accounts\Client\ApiClient`, resolved from the container (`app(CatLabApiClientFactory::class)`), no explicit provider binding needed (auto-resolvable, zero constructor args). Tasks 4-5 swap it via `$this->app->instance(CatLabApiClientFactory::class, $fake)`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Services/CatLabApiClientFactoryTest.php` (runs on the host, no DB):

```php
<?php

namespace Tests\Unit\Services;

use App\Services\CatLabApiClientFactory;
use CatLab\Accounts\Client\ApiClient;
use Tests\TestCase;

class CatLabApiClientFactoryTest extends TestCase
{
    public function testCreatesApiClient()
    {
        $factory = $this->app->make(CatLabApiClientFactory::class);

        $this->assertInstanceOf(ApiClient::class, $factory->forUser(null));
    }

    public function testContainerBindingCanBeSwapped()
    {
        $fake = new class extends CatLabApiClientFactory {
        };
        $this->app->instance(CatLabApiClientFactory::class, $fake);

        $this->assertSame($fake, $this->app->make(CatLabApiClientFactory::class));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter CatLabApiClientFactoryTest`
Expected: ERROR — `Class "App\Services\CatLabApiClientFactory" not found`.

- [ ] **Step 3: Write the factory**

`app/Services/CatLabApiClientFactory.php` (include the standard GPL header used by all app files):

```php
<?php
/* [GPL header identical to app/Providers/ErrbitServiceProvider.php] */

namespace App\Services;

use App\Models\User;
use CatLab\Accounts\Client\ApiClient;

/**
 * Class CatLabApiClientFactory
 *
 * Single construction point for the CatLab Accounts API client so that
 * tests can swap the container binding for a fake.
 *
 * @package App\Services
 */
class CatLabApiClientFactory
{
    /**
     * @param User|null $user
     * @return ApiClient
     */
    public function forUser(User $user = null): ApiClient
    {
        return new ApiClient($user);
    }
}
```

(Note: the GPL header comment is required — copy it verbatim from an existing app file; do not literally write the placeholder line above.)

- [ ] **Step 4: Replace the three call sites**

`app/Http/Controllers/EventController.php` — line 748:

```php
// before
$client = new ApiClient($user);
// after
$client = app(\App\Services\CatLabApiClientFactory::class)->forUser($user);
```

`app/Models/Order.php` — `getOrderData()`:

```php
// before
if ($expanded) {
    $client = new ApiClient($this->user);
} else {
    $client = new ApiClient(null);
}
// after
$factory = app(\App\Services\CatLabApiClientFactory::class);
$client = $factory->forUser($expanded ? $this->user : null);
```

`app/Listeners/SendEmail.php` — in `sendConfirmationEmail` and `sendCancellationEmail`:

```php
// before (both methods)
$apiClient = new ApiClient($user);
// after
$apiClient = app(\App\Services\CatLabApiClientFactory::class)->forUser($user);
```

Leave the now-unused `use CatLab\Accounts\Client\ApiClient;` imports only if still referenced (Order.php keeps none after the change — remove dead imports flagged by inspection, don't touch anything else).

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit --testsuite Unit` (host) — expected OK (9 tests).
Run: `bin/integration-tests.sh` — expected all integration tests still PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/CatLabApiClientFactory.php app/Http/Controllers/EventController.php app/Models/Order.php app/Listeners/SendEmail.php tests/Unit/Services/
git commit -m "Route CatLab Accounts ApiClient construction through a factory seam"
```

---

### Task 4: Test doubles + free ticket purchase flow

**Files:**
- Create: `tests/Integration/Fakes/FakeCatLabApiClient.php`
- Create: `tests/Integration/Fakes/FakeCatLabApiClientFactory.php`
- Create: `tests/Integration/Fakes/FakeEuklesClient.php`
- Modify: `tests/Integration/IntegrationTestCase.php` (bind fakes in `setUp`)
- Test: `tests/Integration/FreeTicketPurchaseTest.php`

**Interfaces:**
- Consumes: `CatLabApiClientFactory` (Task 3), `CreatesEventFixtures` (Task 2).
- Produces: `IntegrationTestCase->catlabApi` (public `FakeCatLabApiClient`) with:
  - `$createOrderCalls` (array of payloads), `$sendEmailCalls` (array of `['subject','target']`), `$orderStatus` (string, default `'PENDING'`, returned by `getOrder`), `$nextOrderId` (int, default `4242`)
  - `createOrder($data)` returns `['id' => $nextOrderId, 'payUrl' => 'https://pay.example.com/order/{id}']`

- [ ] **Step 1: Write the failing test**

`tests/Integration/FreeTicketPurchaseTest.php`:

```php
<?php

namespace Tests\Integration;

use App\Models\Order;
use Tests\Integration\Concerns\CreatesEventFixtures;

class FreeTicketPurchaseTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    public function testFreeTicketPurchaseCompletesWithoutPayment()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 0.0);
        $user = $this->createUser();

        $response = $this->actingAs($user)
            ->post("/events/{$event->id}/register/{$category->id}/process");

        $order = Order::query()->first();
        $this->assertNotNull($order, 'An order should have been created');
        $this->assertEquals(Order::STATE_ACCEPTED, $order->state);
        $this->assertEquals($user->id, $order->user_id);
        $response->assertRedirect(action('OrderController@thanks', [$order->id]));

        // No payment API involved; confirmation email requested via the API.
        $this->assertCount(0, $this->catlabApi->createOrderCalls);
        $this->assertCount(1, $this->catlabApi->sendEmailCalls);

        // The thanks page renders and the order STAYS accepted afterwards.
        $this->actingAs($user)->get("/orders/{$order->id}/thanks")->assertStatus(200);
        $this->assertEquals(Order::STATE_ACCEPTED, $order->fresh()->state);
    }
}
```

- [ ] **Step 2: Write the fakes**

`tests/Integration/Fakes/FakeCatLabApiClient.php`:

```php
<?php

namespace Tests\Integration\Fakes;

use CatLab\Accounts\Client\ApiClient;

class FakeCatLabApiClient extends ApiClient
{
    public $createOrderCalls = [];
    public $sendEmailCalls = [];
    public $orderStatus = 'PENDING';
    public $nextOrderId = 4242;

    public function __construct()
    {
        parent::__construct(null);
    }

    public function createOrder($data)
    {
        $this->createOrderCalls[] = $data;

        return [
            'id' => $this->nextOrderId,
            'payUrl' => 'https://pay.example.com/order/' . $this->nextOrderId,
        ];
    }

    public function getOrder($id, $expanded = false)
    {
        return [
            'id' => $id,
            'status' => $this->orderStatus,
            'price' => 10.0,
            'reference' => 'TEST-' . $id,
        ];
    }

    public function sendEmail($subject, $body, $target = null)
    {
        $this->sendEmailCalls[] = ['subject' => $subject, 'target' => $target];

        return true;
    }
}
```

`tests/Integration/Fakes/FakeCatLabApiClientFactory.php`:

```php
<?php

namespace Tests\Integration\Fakes;

use App\Models\User;
use App\Services\CatLabApiClientFactory;
use CatLab\Accounts\Client\ApiClient;

class FakeCatLabApiClientFactory extends CatLabApiClientFactory
{
    private $client;

    public function __construct(FakeCatLabApiClient $client)
    {
        $this->client = $client;
    }

    public function forUser(User $user = null): ApiClient
    {
        return $this->client;
    }
}
```

`tests/Integration/Fakes/FakeEuklesClient.php` (check `EuklesClient::__construct` signature — `(server, key, secret, environment)` — before writing):

```php
<?php

namespace Tests\Integration\Fakes;

use CatLab\Eukles\Client\EuklesClient;

class FakeEuklesClient extends EuklesClient
{
    public $tracked = [];

    public function __construct()
    {
        parent::__construct('https://eukles.invalid', 'test-key', 'test-secret', 'testing');
    }

    public function trackEvent(\CatLab\Eukles\Client\Models\Event $event)
    {
        $this->tracked[] = $event;

        return true;
    }

    public function trackEvents(array $events)
    {
        foreach ($events as $event) {
            $this->trackEvent($event);
        }

        return true;
    }
}
```

(If the `Models\Event` type hint doesn't match the parent signature, mirror the parent's exact parameter type — check `vendor/catlabinteractive/eukles-client/src/EuklesClient.php:177`.)

- [ ] **Step 3: Bind fakes in IntegrationTestCase**

Update `tests/Integration/IntegrationTestCase.php`:

```php
<?php

namespace Tests\Integration;

use App\Services\CatLabApiClientFactory;
use CatLab\Eukles\Client\Interfaces\EuklesClient as EuklesClientInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Integration\Fakes\FakeCatLabApiClient;
use Tests\Integration\Fakes\FakeCatLabApiClientFactory;
use Tests\Integration\Fakes\FakeEuklesClient;
use Tests\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * @var FakeCatLabApiClient
     */
    protected $catlabApi;

    /**
     * @var FakeEuklesClient
     */
    protected $eukles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catlabApi = new FakeCatLabApiClient();
        $this->app->instance(
            CatLabApiClientFactory::class,
            new FakeCatLabApiClientFactory($this->catlabApi)
        );

        $this->eukles = new FakeEuklesClient();
        $this->app->instance(EuklesClientInterface::class, $this->eukles);
    }
}
```

(The Eukles facade resolves `CatLab\Eukles\Client\Interfaces\EuklesClient::class` from the container — see `EuklesClientFacade::getFacadeAccessor` — so `instance()` intercepts `\Eukles::trackEvent`.)

- [ ] **Step 4: Run and verify**

Run: `bin/integration-tests.sh --filter FreeTicketPurchaseTest`
Expected: PASS.

**Known risk, decided in advance:** `Order::synchronize()` cancels any order without a `catlab_order_id` (Order.php:167-170) — and free orders never get one. If the final assertions fail because visiting the thanks page flipped the order to `CANCELLED`, that is a REAL production bug the test just exposed. STOP, do not "fix the test", and report to the user with the failing output. (Likely minimal fix, only after user agreement: skip the cancel branch in `synchronize()` for orders whose ticket category is free, or skip `synchronize()` entirely for free orders.)

- [ ] **Step 5: Run the full integration + unit suites**

Run: `bin/integration-tests.sh` and `vendor/bin/phpunit --testsuite Unit`
Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add tests/Integration/
git commit -m "Add faked external services and free ticket purchase flow test"
```

---

### Task 5: Paid ticket purchase flow

**Files:**
- Test: `tests/Integration/PaidTicketPurchaseTest.php`

**Interfaces:**
- Consumes: everything from Task 4 (`$this->catlabApi`, fixtures).

- [ ] **Step 1: Write the failing test**

`tests/Integration/PaidTicketPurchaseTest.php`:

```php
<?php

namespace Tests\Integration;

use App\Models\Order;
use Tests\Integration\Concerns\CreatesEventFixtures;

class PaidTicketPurchaseTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    public function testPaidTicketPurchaseRedirectsToPaymentAndConfirmsViaCallback()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);
        $user = $this->createUser();

        // Step 1: submit the order — should create a remote order and
        // redirect the buyer to the payment page.
        $response = $this->actingAs($user)
            ->post("/events/{$event->id}/register/{$category->id}/process");

        $order = Order::query()->first();
        $this->assertNotNull($order, 'An order should have been created');
        $this->assertEquals(Order::STATE_PENDING, $order->state);
        $this->assertEquals($this->catlabApi->nextOrderId, $order->catlab_order_id);
        $this->assertCount(1, $this->catlabApi->createOrderCalls);

        $payload = $this->catlabApi->createOrderCalls[0];
        $this->assertEquals($event->name, $payload['items'][0]['name']);

        $response->assertStatus(302);
        $this->assertStringStartsWith(
            'https://pay.example.com/order/' . $this->catlabApi->nextOrderId,
            $response->headers->get('Location')
        );

        // Step 2: the PSP reports payment — callback flips the order to
        // accepted and triggers the confirmation email.
        $this->catlabApi->orderStatus = 'ACCEPTED';
        $this->get("/orders/{$order->id}/sync")->assertStatus(200);

        $this->assertEquals(Order::STATE_ACCEPTED, $order->fresh()->state);
        $this->assertCount(1, $this->catlabApi->sendEmailCalls);

        // Step 3: the thanks page renders the confirmation.
        $this->actingAs($user)->get("/orders/{$order->id}/thanks")->assertStatus(200);
        $this->assertEquals(Order::STATE_ACCEPTED, $order->fresh()->state);
    }
}
```

- [ ] **Step 2: Run and verify**

Run: `bin/integration-tests.sh --filter PaidTicketPurchaseTest`
Expected: PASS. This exercises: `EventController::processRegister` (paid branch), `Order::getPayUrl`, the unauthenticated `OrderController::sync` callback, `Order::synchronize`/`changeState`, the `OrderConfirmed` listeners (email through the fake factory, Eukles through the fake client), and the thanks view.

If `synchronize()` misbehaves (see the `$this->status` vs `state` mismatch at Order.php:175 — `status` is not a column, so the comparison is always against null), the test may still pass because the branch always executes. Only STOP and report if an assertion actually fails.

- [ ] **Step 3: Commit**

```bash
git add tests/Integration/PaidTicketPurchaseTest.php
git commit -m "Add paid ticket purchase flow test with faked payment API"
```

---

### Task 6: Auth guards

**Files:**
- Test: `tests/Integration/PurchaseGuardsTest.php`

**Interfaces:**
- Consumes: fixtures (Task 2).

- [ ] **Step 1: Write the failing test**

`tests/Integration/PurchaseGuardsTest.php`:

```php
<?php

namespace Tests\Integration;

use App\Models\Order;
use Tests\Integration\Concerns\CreatesEventFixtures;

class PurchaseGuardsTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    public function testTicketRegistrationRequiresLogin()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 10.0);

        $this->get("/events/{$event->id}/register/{$category->id}")
            ->assertRedirect('/login');
    }

    public function testProcessingAnOrderRequiresLogin()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 10.0);

        $this->post("/events/{$event->id}/register/{$category->id}/process")
            ->assertRedirect('/login');

        $this->assertEquals(0, Order::query()->count());
    }
}
```

- [ ] **Step 2: Run and verify**

Run: `bin/integration-tests.sh --filter PurchaseGuardsTest`
Expected: PASS (`CATLAB_CLIENT_ID` is empty in the integration config, so `Auth::routes()` provides `/login` and the `auth` middleware redirects there).

- [ ] **Step 3: Commit**

```bash
git add tests/Integration/PurchaseGuardsTest.php
git commit -m "Add auth guard tests for the purchase routes"
```

---

### Task 7: Documentation + full verification + PR

**Files:**
- Modify: `readme.md` (add a "Running tests" section)

- [ ] **Step 1: Document the runners**

Append to `readme.md`:

```markdown
## Running tests

Unit tests (no database needed):

    vendor/bin/phpunit --testsuite Unit

Integration tests (runs inside docker against a dedicated
`catlab_events_test` database; requires docker compose):

    bin/integration-tests.sh

Pass phpunit arguments through, e.g. `bin/integration-tests.sh --filter PaidTicketPurchaseTest`.
```

- [ ] **Step 2: Full verification**

Run: `vendor/bin/phpunit --testsuite Unit` (host) — expected OK.
Run: `bin/integration-tests.sh` — expected OK, all integration tests green.

- [ ] **Step 3: Commit and open PR**

```bash
git add readme.md
git commit -m "Document unit and integration test runners"
git push -u origin feature/integration-tests
gh pr create --base master --title "Add integration tests for the ticket purchase flow" ...
```

PR body: summarize suites, the factory seam, the docker runner, and CRUCIALLY any production bugs the tests exposed (free-order cancellation, synchronize status mismatch) with how they were handled. Note the PR depends on #49 (PHP 8.5) being merged first.
