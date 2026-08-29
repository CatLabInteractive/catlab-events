# Order refund button

**Date:** 2026-08-29
**Status:** Designed
**Repos:** `catlab-accounts`, `laravel-catlab-accounts`, `catlab-events`

## Goal

Give events admins a refund button for a paid order, in the events admin
panel, behind a confirmation that makes clear the refund is final and costs
us the transaction fee.

## Why this is small

Most of the machinery exists. Only the API surface between the three
systems is missing.

Accounts already refunds for real. `Payment::refund()`
(`src/Accounts/Models/Payment.php:398`) calls the Omnipay gateway, so money
moves at the PSP; it then logs the attempt, writes a `TYPE_REFUND` payment,
and calls `Order::refund()`, which produces a credit note and sets
`STATUS_REFUNDED`. Accounts even has this UI: a Refund button at
`templates/admin/payments/payments-table.phpt:85`, handled at
`Controllers/Admin/Payments.php:60`.

(`Order::refund()` on its own is bookkeeping only — no gateway call. The
money-moving entry point is `Payment::refund()`, and that is what the new
endpoint must call.)

Events already reacts. `updateStatusAndNotify()` calls the order's notify
url, and events supplies `callback` = its signed sync url when creating the
order (`EventController.php:813`). That reaches `OrderController@sync` →
`Order::synchronize()` → `changeState(REFUNDED)` → `onCancellation()` →
UiTPAS sale cancelled and `OrderCancelled` fired → cancellation mail. And
`countSoldTickets()` counts only `ACCEPTED`/`PENDING`, so the seat restocks
itself and becomes invitable through the waiting list.

`Order::STATE_REFUNDED` has existed in events all along
(`app/Models/Order.php:45`); nothing ever set it.

## Security model

The refund endpoint must not be usable by someone holding only the product
client credentials. That is the point of the design, so the reasoning is
recorded here.

**A second secret in the same `.env` is worth nothing.** If the attack is a
dump of the events environment, a `REFUND_SECRET` env var or an HMAC keyed
on `APP_KEY` leaks with everything else. The second factor has to live in a
different store: the database.

**The existing order token cannot be that factor.** Accounts mints
`order_token` (12 chars, `SecureToken::getToken()`, `[A-Za-z0-9]`,
`random_int()`) per order and events already persists it inside
`orders.pay_url`. But `Order::getReceiptUrl()` builds `receipt/{id}/{token}`
and `GET orders/{id}?expanded=1` returns it as `receipt` — so the client
credentials already yield the order token for every order of that product.
It is not independent of the thing we are defending against.

**Therefore: a new per-order refund token.** Minted by accounts at order
creation, returned once in the create response, stored by events in its own
`orders` table, and returned by no GET endpoint. Refunding order X requires
the client credentials *and* that order's row from the events database.
Credentials alone refund nothing, and there is no API path that enumerates
the tokens.

Two supporting measures:

- **A per-product refund throttle in accounts** (10 per hour, 429 beyond).
  Even with both stores compromised, nobody drains the account in one
  script, and it leaves time to notice. This is what protects against
  refunding *everything*.
- **Amount binding.** The refund call carries the expected amount and
  accounts rejects a mismatch, which also catches a stale confirm page.

## Flow

1. Admin opens the events admin order list and clicks **Terugbetalen**.
2. The confirm page fetches the order live from accounts
   (`getOrder(expanded: true)` → `reference`, `price`) and shows the
   warning, buyer, event and the real amount.
3. Admin types the order reference and submits.
4. Events calls `refundOrder()` on the accounts client.
5. Accounts checks credentials, product ownership, refund token, order
   state, amount and throttle, then runs the existing `Payment::refund()`.
6. Events calls `$order->synchronize()`, so local state is read back from
   accounts rather than assumed, and reports the outcome.

Accounts' notify callback fires as well. That is harmless: `changeState()`
returns early when the state already matches.

The amount shown on the confirm page must come from accounts, never from a
local ticket price calculation. Discounts, UiTPAS tariffs and transaction
fees would otherwise let a "this is final" screen disagree with what is
actually refunded.

## Components

### catlab-accounts

- **`db/upgrade-20260829.sql`** — add `order_refund_token varchar(32)` to
  the orders table. Applied by the `commands/migrate.php` chain and tracked
  in the `migrations` table, per the existing `db/upgrade-*.sql` convention.
- **`Accounts\Models\Order::create()`** — mint
  `SecureToken::getToken(24)` into the new field alongside the existing
  order token.
- **`Accounts\Controllers\API\Orders@create`** — add `refundToken` to the
  response. Additive; older clients ignore it.
- **`Accounts\Module`** — new route beside the order routes at line 590:
  `$router->post('api/1.0/orders/{id}/refund', '\Accounts\Controllers\API\Orders@refund')->filter('client-credentials');`
- **`Accounts\Controllers\API\Orders@refund`** — mirrors the guard style of
  `Orders@get`. Body: `refundToken`, `amount`, `reason`.

  `amount` is the order's `getPriceToPay()` — the same figure the API
  returns as `price` from `getOrder(expanded: true)`, which is what the
  confirm page displayed. Compare in cents to avoid float equality.

  | Condition | Response |
  |---|---|
  | Missing or invalid client credentials | 401 |
  | Order not found, product mismatch, or refund token mismatch (`hash_equals`) | 404 |
  | Order not `ACCEPTED`, or amount mismatch | 409 |
  | More than 10 refunds for this product in the last hour | 429 |
  | Otherwise | `$order->getPayment()->refund([...])`, return the new status |

  A wrong token and a missing order return the identical 404, so the
  endpoint cannot be used to probe which orders exist.

### laravel-catlab-accounts

- **`ApiClient::refundOrder($orderId, $refundToken, $amount, $reason)`** —
  POST to `orders/{id}/refund` with the existing product authorization
  headers; same decode-or-throw shape as `createOrder()`.
- Tag **4.2**; bump the constraint in events' `composer.json`.

### catlab-events

- **Migration** — nullable `orders.refund_token`.
- **`EventController@processRegister`** — store `$orderData['refundToken']`
  next to `catlab_order_id` and `pay_url`.
- **`Admin\OrderController`** — add `getTableForResourceCollection()`
  registering a **Terugbetalen** `ResourceAction` (the pattern
  `Admin\EventController` uses for its exports), plus `refund` (GET,
  confirm) and `processRefund` (POST), and the two routes in the admin
  group of `routes/web.php`.
- **`resources/views/admin/orders/refund.blade.php`** — on `layouts.admin`.
  Warning box, buyer, event, live amount, and a text input that must match
  the order reference before the button enables.
- **Visibility** — the action renders only when the order is `ACCEPTED`,
  has a `catlab_order_id`, has a `refund_token`, and has a price above
  zero. Orders predating this feature instead show a line pointing at the
  accounts admin payments page, which already refunds them.
- **Organisation scoping** — the order's event must belong to the acting
  admin's active organisation, else 404. `IsAdmin` only checks the global
  `admin` flag, so without this an admin of one organisation could refund
  another's order.
- **Server-side reference check** — the typed reference is re-validated
  against the live accounts reference. Disabling the button in JS is a
  convenience, not the control.

## Error handling

A timeout is not a failure. If the call to accounts times out the refund may
well have gone through, so events must not report failure: it re-syncs and
reports the true state, and asks the admin to verify in accounts if that is
still inconclusive. Reporting a false failure invites a second click, and
for a money action that is the expensive mistake.

Retries are safe regardless: `Payment::refund()` returns early when the
order is already refunded.

| Outcome | Message |
|---|---|
| 401 / 404 | Could not be refunded; logged. No local state change. |
| 409 | Order is no longer refundable, or the amount changed. Re-sync and show the true state. |
| 429 | Too many refunds in a short time; try again later. |
| Timeout / connection error | Outcome unknown — re-sync, and say to check accounts if still unclear. |

Every path re-syncs before rendering.

## Testing

**catlab-accounts** — endpoint tests: no credentials, another product's
order, wrong refund token, wrong amount, order not accepted, throttle
exceeded, and the happy path (gateway called, `TYPE_REFUND` payment
written, status `REFUNDED`).

**catlab-events** — integration tests on the existing fakes, with
`FakeCatLabApiClient` gaining `refundOrder`: the action renders only under
the four visibility rules; an order from another organisation 404s; the
confirm page rejects a wrong typed reference; a successful refund syncs to
`REFUNDED`, frees the seat and mails the cancellation; and each failure
path above, including that a timeout does not report failure.

## Rollout

Strictly in order:

1. **accounts** — schema, token minting, endpoint, throttle. Deploy.
2. **laravel-catlab-accounts** — `refundOrder()`, tag 4.2.
3. **catlab-events** — composer update, migration, store the token, button.

Only orders created after step 1 is deployed carry a refund token, which is
what makes the "no button for older orders" behaviour take effect on its
own.

## Out of scope

- **Partial refunds.** `Payment::refund()` takes no amount; supporting them
  is a much larger accounts change.
- **Free tickets.** They never get a `catlab_order_id`, and
  `FreeOrder::refund()` is an unimplemented stub. There is no payment to
  refund; cancelling such an order is separate work.
- **A local audit trail in events.** The reason travels to accounts'
  `PaymentLog`; events stores no `refunded_by` / `refunded_at`.
- **Removing the order token from the `receipt` url.** Worth revisiting on
  its own, but other products may depend on that field.
- **`canRefund()`'s commented-out two week window** in accounts. Today a
  refund is possible indefinitely. Noted because this feature exposes it to
  more people; changing it is a separate decision.
