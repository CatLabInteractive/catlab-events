# Integration tests for the base website and ticket purchase flow

**Date:** 2026-07-24
**Status:** Approved
**Depends on:** PR #49 (PHP 8.5 upgrade) — the docker image only builds from that branch

## Goal

HTTP-level integration tests that prove the public website renders and the
full ticket purchase flow works — free and paid — against a real MySQL
database, with all external services faked.

## Where the tests run

- New `Integration` testsuite in `tests/Integration/`, configured by
  `phpunit.integration.xml` (separate from `phpunit.xml`; the existing
  DB-free unit suite is unchanged).
- Tests run inside the docker-compose `webserver` container (production
  PHP + pdo_mysql) against the `mysql-db` service, using a dedicated
  `catlab_events_test` database so dev data is never touched. The wrapper
  script creates the database if missing.
- `RefreshDatabase` migrates the schema fresh each run — this also proves
  the migrations run end-to-end on a clean MySQL 8. (SQLite is not an
  option: two order migrations use raw `ALTER TABLE ... CHANGE COLUMN`.)
- Entry point: `bin/integration-tests.sh` →
  `docker compose exec webserver vendor/bin/phpunit -c phpunit.integration.xml`.
  Documented in the readme.
- `phpunit.integration.xml` env: test DB credentials; `CATLAB_CLIENT_ID`
  unset so auth routes stay local (`actingAs()` works); Errbit disabled;
  Eukles/QuizWitz/UitPAS unconfigured.

## Production seam (only production change)

`CatLab\Accounts\Client\ApiClient` is currently constructed with `new` at
three sites: `EventController::processRegister`, `Order::getOrderData`,
`App\Listeners\SendEmail`. A new `App\Services\CatLabApiClientFactory`
with `forUser(?User $user): ApiClient` is bound as a container singleton;
the three sites resolve it instead of `new`-ing the client. Production
behavior is unchanged. Tests swap the binding for a fake factory whose
client records `createOrder` / `getOrder` / `sendEmail` calls and returns
canned responses.

## Fixtures

`tests/Integration/Concerns/CreatesEventFixtures.php` — plain Eloquent
builders (the legacy `ModelFactory.php` is dead code on Laravel 9):
Organisation → Event (+ EventDate) → TicketCategory (free or paid) →
User. `campaign_id` stays null so no QuizWitz call fires.

## Test cases

**Smoke** (`SmokeTest`): homepage, event detail page, and ticket-selection
page each return 200 and render.

**Free purchase** (`FreeTicketPurchaseTest`): logged-in user walks
select → confirm → process; order ends in `STATE_ACCEPTED`; redirect to
thanks page which renders; the fake client saw a `sendEmail`
(confirmation) call and no `createOrder` call.

**Paid purchase** (`PaidTicketPurchaseTest`): fake API returns
`{id, payUrl}`; after process the order is pending with `catlab_order_id`
set and the response redirects to the payUrl. Simulating the PSP return
(`GET orders/{id}/sync`, unauthenticated by design) with the fake
reporting an accepted status flips the order to `STATE_ACCEPTED`; the
thanks page then renders the confirmation.

**Guards** (`PurchaseGuardsTest`): unauthenticated requests to the
register routes redirect to login.

Eukles tracking is neutralized (unconfigured client or a bound spy —
whichever its service provider supports, determined during
implementation; sync queue means listeners run inline).

## Out of scope

Browser/JS end-to-end tests, the donation (pay.nl) flow, UitPAS
subsidised tariffs, CI wiring (possible follow-up: GitHub Actions job
running the same docker recipe).
