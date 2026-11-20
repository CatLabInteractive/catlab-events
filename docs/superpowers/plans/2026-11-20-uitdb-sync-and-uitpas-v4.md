# Implementation plan for issue #44: sync events to UiTdatabank and move UiTPAS to API v4

Issue: https://github.com/CatLabInteractive/catlab-events/issues/44. Status: plan only, no code yet. A second agent reviewed this plan against the code and the publiq sources, and its corrections are included. Questions 1, 2, 9 and 10 in section 5 block PR 2; the scheduler (prerequisite in section 5) blocks PR 3.

## 0. What the code does today

**The integration on `master` no longer works.** It uses three publiq systems, and all three are retired:

- **UiTPAS (`app/UitDB/UiTPASVerifier.php`)** uses the old UiTPAS 3.0 XML API, signed with OAuth1 (`UitDatabankService::getOauth1ConsumerGuzzleClient`, base URL `https://www.uitid.be/uitid/rest/`):
  - `GET uitpas/cultureevent/search?cdbid=…&uitpasNumber=…` reads price classes and `buyConstraintReason`.
  - `POST uitpas/cultureevent/{cdbid}/buyonline/{card}` with `priceClass` registers a sale.
  - `POST uitpas/cultureevent/cancelonline/{saleId}` cancels it.
  - `POST uitpas/passholder/checkin` with `cdbid` and `uitpasNumber` checks a passholder in.

  publiq says this XML API was "phased out by 18th of December 2024" (projects/uitpas/docs/legacy-xml-api.md).
- **Admin connect flow (`UitDbController`, `OAuth1/UitDbServer`)** links a UiTiD v1 account with OAuth1 and stores the tokens in `organisations.uitdb_identifier` / `uitdb_secret`.
- **Entry API login** is only started, and that code is commented out: `getConnectUrl()` (JWT provider plus API key) and the unused columns `uitdb_jwt` / `uitdb_refresh`. publiq ends support for JWT-provider tokens on **30 Sept 2026**, which has already passed (entry-api/authentication.md).
- **Event sync does not exist.** `UitDBEvents::upload()` just returns `'abcdef'`. The admin types `events.uitdb_event_id` by hand in the "Koppelingen" group. `EventValidator::checkUiTDBID` blocks publishing when `organisations.uitpas` is set and the ID is missing.
- **Ticket category saves can fail.** `TicketCategoryController::beforeSaveEntity` calls the dead XML API on every save of a ticket category for an event that has a UiTdatabank ID. Only `InvalidEventException` is caught, so any other API error gives an HTTP 500. This happens only where `UITDB_OAUTH_CONSUMER` is set, because `getUitPasService()` returns null without it. Nobody knows yet whether production sets it.
- **Hidden config dependencies.** `EventResourceDefinition` (line 174) defines `uitdb_event_id` only when `services.uitdb.oauth_consumer` is set. `LiveStreamController::uitPASCheckin` (line 200) calls `\UitDb::getUitPasService()->canCheckIn()` without a null check, and `canCheckIn()` reads `organisations.uitdb_identifier`.
- **`.env.example` does not match the config.** It holds `UITDB_CONNECT_KEY` and `UITDB_SEARCH_API_KEY`, but `config/services.php` reads `UITDB_ENTRY_API_KEY`.
- **An earlier attempt exists.** Branch `copilot/implement-synchronization-with-uitdb` (commits `1249f63..967f8a7`) adds client-credentials auth, `UitDBEvents` payload building, an `EventObserver` and `SyncEventToUitDb` / `DeleteEventFromUitDb` jobs. Its UiTPAS calls do not match the v4 spec:
  - `/events/{id}/tariffs` should be `GET /tariffs?eventId&uitpasNumber&regularPrice`.
  - `/events/{id}/checkins` should be `POST /checkins`.
  - `POST /ticket-sales` takes an **array** and requires `regularPrice`. The branch posts a single object without `regularPrice`.

  Its jobs also run in the admin request, because `QUEUE_DRIVER=sync` and there is no `jobs` table or worker. Re-use its payload code; do not merge the branch as it is.

## 1. Gap analysis: admin panel vs Entry API v3

| CatLab today | Entry API needs | Gap and decision |
|---|---|---|
| `events.name`, `description` (HTML) | `mainLanguage: nl`, `name.nl`, `description.nl` | Map directly. Convert HTML to plain text. |
| `event_type` = event / package | Events only | Never sync `package` (quiz packages). |
| (none) | `terms`: ≥1 term in domain `eventtype` | Use `0.50.21.0.0` "Spel of quiz" (checked against taxonomy.uitdatabank.be/terms). Store it in config. |
| `event_dates` (`startDate`, `endDate`, `doorsDate`) | `calendarType` single/multiple + `subEvent[]` | 1 date gives `single`, more give `multiple`. An event without dates is not synced and shows an error. |
| `venues` (address, postalCode, city, country, lat/long). This table has `user_id` and no `organisation_id`. | `location: {"@id": place URL}`. Existing places **must** be re-used. | New column `venues.uitdb_place_id`. Admin searches Search API `GET /places?q=name.*:"…" AND address.*.postalCode:…`. Create with `POST /places` only as a last resort. |
| `livestream` / `livestream_url` | `attendanceMode` online/mixed. Online events must **not** send a `location`. `onlineUrl` is optional. | No venue + livestream gives `online`. Venue + livestream gives `mixed`. Do not send `onlineUrl` for paid streams. |
| `ticket_categories` (name, price, sale window) | `priceInfo[]`: one `base` named "Basistarief", the rest `tariff` | Base = cheapest non-free category (open question). UiTPAS needs a base price above 0 **and** a UiTPAS organizer. |
| Organisation (name, url) | `organizer: {"@id"}`, re-use the existing one | New column `organisations.uitdb_organizer_id`. |
| `logo_id`, `poster_id` (CentralStorage assets) | `POST /images` (JSON URL import or multipart; needs `description`, `copyrightHolder`, `language`), then `POST /events/{id}/images {mediaObjectId}`, then `PUT /events/{id}/images/main` | Two columns on `events` (`uitdb_poster_image_id`, `uitdb_logo_image_id`) remember what is already uploaded. |
| `is_published` | `workflowStatus` DRAFT → `READY_FOR_VALIDATION` (one-way) via `PUT /events/{id}/workflow-status`; optional `availableFrom` | Create as DRAFT, publish when published. Unpublish cannot go back to DRAFT (open question). |
| `getUrl()` | `bookingInfo.url` + `urlLabel` (both required together) | Send the ticket URL with label "Koop tickets". |
| series, competition, people, `max_tickets` | (labels, `bookingAvailability`) | Out of scope for now; sold-out could map to `bookingAvailability` later. |
| Soft delete | `DELETE /events/{id}` (permanent) | Only for events that are deleted and not yet past. Never delete because an event is in the past. |
| Cancelled or sold out | `PUT /events/{id}/status` (`Unavailable`), `bookingAvailability` | publiq asks not to delete cancelled or sold-out events. Set the status or the booking availability instead (open question 13). |

**Updates.** The docs advise fetching the event first, then `PUT /events/{id}`, so that edits made in the UiTdatabank UI are not reverted. Plan: GET the event, overwrite only the fields CatLab owns, then PUT.

**Ownership.** Events created with a client token belong to the **client**. An event ID that an admin typed by hand is "linked, not owned" by default (`uitdb_owned = false`): UiTPAS works for it, but CatLab never sends updates. GET-merge-PUT on such an event would overwrite the description, prices and calendar that someone entered in the UiTdatabank UI. Taking over an event needs an explicit action in the admin. Do not rely on a 403 to detect this.

## 2. Sync design

- **Classes in `app/UitDB/`:**
  - `Publiq/TokenProvider`: client credentials, `POST {account}/realms/uitid/protocol/openid-connect/token`. Cache the token in Laravel Cache for `expires_in` minus 5 minutes, and retry once after a 401.
  - `Publiq/ProblemException`: wraps publiq's problem+json errors (`type`, `endUserMessage.nl`).
  - `Contracts/EntryApiClient` and `Contracts/UiTPASClient`, with Laravel `Http` implementations.
  - `Sync/EventPayloadMapper`, `Sync/PlaceResolver`, `Sync/OrganizerResolver`, `Sync/ImageSync`, `Sync/EventSynchronizer`.
- **When to sync:** the Eloquent observers `EventObserver`, `EventDateObserver` and `TicketCategoryObserver` **only mark the event as pending**. A venue change is picked up at the next save of the event. They set `uitdb_sync_status='pending'` and `uitdb_next_attempt_at=now()`, and make no remote call during the admin request.
  - A new command `uitdb:sync` runs every minute in `app/Console/Kernel.php` with `withoutOverlapping`. It processes pending and failed rows that are due.
  - Retries back off over 1m, 5m, 30m, 2h, then give up at `failed`.
  - This works with today's sync queue. A real queue worker is an option to add later.
- **Errors in the admin:**
  - Add a "UiTdatabank" field group on the event form showing status, last error (`endUserMessage` / `detail`), last sync time, a UiTdatabank link and a "Nu synchroniseren" button.
  - Report `failed` rows through the existing Errbit integration.
  - `EventValidator` stops requiring a hand-typed ID once sync is enabled for the organisation.

## 3. UiTPAS upgrade (API v4, `api(-test).uitpas.be`)

| Use | Old (XML) | v4 |
|---|---|---|
| Check pass (optional) | part of cultureevent/search | `GET /passes?identifier=` (required parameter; `/passes/{nr}` is deprecated). Needs `PASSES_READ`. Always show `messages`. |
| Tariffs for a passholder | price-class matching by name or price | `GET /tariffs?eventId&uitpasNumber&regularPrice=<category price>` returns `available[]` (id, price, type) or `endUserMessage` |
| Admin warning | `hasApplicableUitPasPrice` | `GET /tariffs/static?eventId&regularPrice`. Show a warning only; never block the save. |
| Register sale | `buyonline` | `POST /ticket-sales` with `[{eventId, uitpasNumber, regularPrice, regularPriceLabel: category name, tariff:{id}}]`. Store `[0].id`. |
| Cancel | `cancelonline` | `DELETE /ticket-sales/{id}` (204) |
| Check-in | `passholder/checkin` | `POST /checkins {eventId, uitpasNumber}` → 201. Error type `…/checkin-not-allowed` outside the allowed time window. |

**Classes and flow:**
- Rewrite `UiTPASVerifier` as `App\UitDB\UiTPAS\UiTPASService`.
- Auto-select the `SOCIALTARIFF` tariff (the docs allow this). Coupons are an open question.
- Show `endUserMessage.nl` in `registerOnePage.blade.php` and `livestream/uitpasCheckin.blade.php`.
- `Order::onCancellation` and `OrderCheckout` must not throw when a cancel fails. Set `orders.uitpas_cancel_pending` and log the failure, so `orders:expire-pending` keeps working. A small command `uitpas:retry-cancels` in the same PR retries these cancels. It does not wait for `uitdb:sync`.
- The client needs `PASSES_READ`, `TARIFFS_READ`, `TICKETSALES_REGISTER` and `CHECKINS_WRITE` for the organizer. Show this from `GET /permissions` on the admin page.
- The livestream check-in page must keep working at every step. If no UiTPAS service is configured, it shows a message instead of a 500.
- UiTPAS registers a new event some seconds after it is created in UiTdatabank. `GET /tariffs` can return nothing in that window. The checkout and the admin warning must treat an empty answer as "try again later", not as "no UiTPAS".
- **Data protection.** A UiTPAS number with a social tariff reveals financial hardship. Keep UiTPAS numbers out of logs, Errbit payloads and `uitdb_sync_error`. Store only the ticket sale id and the tariff on `orders`, not the pass number.

## 4. Config, migrations, tests

- **`config/services.php` → `uitdb`:**
  - Keys: `env` (test|production), `client_id`, `client_secret`, `default_event_type`.
  - Base URLs: `account(-test).uitid.be`, `io(-test).uitdatabank.be`, `search(-test).uitdatabank.be`, `api(-test).uitpas.be`.
  - Remove `oauth_consumer`, `oauth_secret`, `entry_api_key` and every old `UITDB_*` key in `.env.example` (`UITDB_CONNECT_KEY`, `UITDB_SEARCH_API_KEY` and the OAuth keys).
  - `EventResourceDefinition` must show `uitdb_event_id` when the new credentials are set, not when `oauth_consumer` is set.
- **Migrations:**
  - `organisations`: `uitdb_organizer_id`, `uitdb_sync_enabled` bool. One platform client for all organisations; no client secrets per organisation unless question 1 says otherwise.
  - `venues`: `uitdb_place_id`.
  - `events`: `uitdb_sync_status`, `uitdb_sync_error`, `uitdb_synced_at`, `uitdb_sync_attempts`, `uitdb_next_attempt_at`, `uitdb_owned` bool, `uitdb_poster_image_id`, `uitdb_logo_image_id`.
  - `orders`: `uitpas_tariff_id`, `uitpas_regular_price`, `uitpas_cancel_pending`.
  - A final cleanup migration drops `uitdb_identifier`, `uitdb_secret`, `uitdb_jwt`, `uitdb_refresh`.
- **Tests:**
  - New fakes `tests/Integration/Fakes/FakeEntryApiClient.php` and `FakeUiTPASClient.php`. They record calls, return canned UUIDs and can be scripted to fail. Bind them in `IntegrationTestCase::setUp()`, the same way as `FakeCatLabApiClient`.
  - Integration tests:
    - Sync state changes on event, date and category edits.
    - Create, then update, then publish.
    - Online and mixed events.
    - Packages are skipped.
    - 403 marks the event as not owned.
    - Retry and backoff.
    - UiTPAS checkout with social tariff, expired social tariff, blocked card and cancel on expiry.
    - No UiTPAS number appears in the log or in the Errbit payload.
    - Livestream check-in.
  - Unit tests: `EventPayloadMapper` against fixture JSON, and the client classes with `Http::fake()`.
  - Manual test on publiq's test environment with the test organizer `0ce87cbc-9299-4528-8d35-92225dc9489f`:
    - Passholders `0900000067513` (Gent social tariff), `0900000031618` (active card, social tariff expired), `0900000002312` (blocked).
    - Event `d83b5b8a-…` (limited purchase).
  - Add an artisan command `uitdb:check` that tests the token and lists permissions.

## 5. PR breakdown, in order

**Prerequisite (ops, before PR 3):** make sure that production runs `php artisan schedule:run` every minute, for example with a dokku `cron` entry in `app.json`. Without it, events stay `pending` forever and no error shows. `orders:expire-pending` needs the same cron today.

1. **Publiq foundation and stop the dead calls.** Config, `TokenProvider`, problem+json errors, client interfaces and fakes, `uitdb:check`. `getUitPasService()` returns null without the new credentials. Remove the TicketCategory hard block. Keep the livestream check-in page working (null check). Keep `uitdb_event_id` visible in the event form. No behavior depends on the XML API any more.
2. **UiTPAS v4.** Rewrite the service, add the `orders` migration, update the register and livestream views, make cancel non-fatal, add `uitpas:retry-cancels`, add tests. It works with hand-entered `uitdb_event_id`. It needs the publiq client and the organizer permissions first (questions 1, 2). `regularPrice` must match a `priceInfo` entry of the UiTdatabank event, which nothing guarantees until PR 3.
3. **Event sync.** Admin linking (connection test, `GET /permissions`, organizer and place pickers), sync-state migration, `EventPayloadMapper`, observers, `uitdb:sync` in the scheduler, create, GET-merge-PUT updates for owned events, workflow status, status panel with a resync button, Errbit. Re-use payload code from the copilot branch.
4. **Images, cancel and delete.** Image upload, link and main image. Event status for cancelled and sold-out events. Delete for deleted upcoming events.
5. **Backfill and cleanup.** `uitdb:backfill --dry-run` for upcoming events. Drop the OAuth1/JWT columns, `UitDbServer` and the `league/oauth1-client` and `guzzle oauth-subscriber` packages. Update docs.

**Risks:**
- Places or organizers created by mistake count as duplicates, and publiq can block the integration for that.
- Going live needs publiq to validate at least 5 test events.
- Events owned by the client are not visible to the organisation's users in the UiTdatabank UI unless those users are made owners.
- The `READY_FOR_VALIDATION` step and deletes cannot be undone.
- Asset URLs must be publicly reachable for the URL image import.

**Open questions for the owner:**
1. Which publiq credentials exist? One CatLab client on platform.publiq.be, or one per organisation? Test credentials only, or production too?
2. Which UiTdatabank/UiTPAS organizer ID belongs to each organisation, and does the client have UiTPAS permissions for it?
3. Is "Spel of quiz" (`0.50.21.0.0`) the right type for all events? Should series map to labels?
4. Which ticket category is the base price, and what should happen with free events?
5. When an event is unpublished in CatLab, what should happen in UiTdatabank: delete it, set its status to unavailable, or leave it?
6. Should only upcoming events be backfilled, or also past ones? Past events should probably not be backfilled.
7. Should UiTPAS coupons be offered, or only the social tariff?
8. Should sync be on by default for every organisation, or opt-in per organisation or per event?
9. Does production set `UITDB_OAUTH_CONSUMER`, and does the UiTPAS checkout work at all today? The answer decides how urgent PR 1 is.
10. Does a cron run `schedule:run` in production?
11. Who owns the events that already have a hand-entered UiTdatabank ID, and may CatLab overwrite them?
12. Who is the `copyrightHolder` for the uploaded images?
13. How must a cancelled or sold-out event show in UiTdatabank?

**Sources.** Every docs.publiq.be page I tried returned 404 with WebFetch and curl, because the site is a client-side Stoplight app. I read the same pages as markdown in the source repo, github.com/cultuurnet/apidocs:
- https://docs.publiq.be/docs/authentication/methods/client-access-token (projects/authentication/docs/client-access-token.md, environments.md)
- https://docs.publiq.be/docs/uitpas/introduction (projects/uitpas/docs/: registering-ticket-sales.md, registering-events.md, authentication.md, test-dataset.md, legacy-xml-api.md)
- UiTPAS OpenAPI file: https://stoplight.io/api/v1/projects/publiq/uitpas/nodes/reference/uitpas.json?deref=optimizedBundle (this one loaded directly; it is version 4.0)
- Entry API (projects/uitdatabank/docs/entry-api/): authentication.md, events/create.md, update.md, publish.md, delete.md, create-online.md, create-uitpas.md, places/*, organizers/*, shared/images.md, price-info.md, calendar-info.md, booking-and-contact-info.md; plus reference/entry.json
- Search API: search-api/authentication.md
- Taxonomy: https://taxonomy.uitdatabank.be/terms
