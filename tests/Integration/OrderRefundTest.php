<?php

namespace Tests\Integration;

use App\Models\Order;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
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
        $admin->organisations()->attach($order->event->organisation_id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

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
        $admin->organisations()->attach($order->event->organisation_id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

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
        $admin->organisations()->attach($order->event->organisation_id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

        $response = $this->actingAs($admin)->get("/admin/orders/{$order->id}/refund");

        $response->assertStatus(200);
        $response->assertDontSee('Terugbetalen</button>', false);
    }

    public function testAnAdminOfAnotherOrganisationGets404()
    {
        $order = $this->createRefundableOrder();

        // Global `admin` flag, but the active organisation is a different one.
        $admin = $this->createAdmin();
        $admin->organisations()->attach($this->createOrganisation()->id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

        $this->actingAs($admin)->get("/admin/orders/{$order->id}/refund")->assertStatus(404);
    }

    public function testTheAdminOrderListShowsTheRefundLink()
    {
        $order = $this->createRefundableOrder();
        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

        $response = $this->actingAs($admin)->get('/admin/orders');

        // Regression guard: the table renders a refund action for every row
        // via ResourceAction::getUrl(), which builds the URL from the route
        // parameter named 'id'. A mismatched route parameter name (e.g.
        // {order}) throws UrlGenerationException here, 500ing this page for
        // any organisation with at least one order.
        $response->assertStatus(200);
        $response->assertSee(action('Admin\RefundController@refund', [ 'id' => $order->id ]), false);
    }

    public function testAFreeOrderWithoutACatlabOrderExplainsItIsAFreeTicket()
    {
        $order = $this->createRefundableOrder(0.0);
        $order->catlab_order_id = null;
        $order->refund_token = null;
        $order->save();

        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

        $response = $this->actingAs($admin)->get("/admin/orders/{$order->id}/refund");

        $response->assertStatus(200);
        $response->assertSee('gratis ticket');
        $response->assertDontSee('Terugbetalen</button>', false);
    }

    public function testRefundingCallsAccountsAndSyncsTheOrder()
    {
        $order = $this->createRefundableOrder();
        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

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

        // The design's test plan requires the cancellation mail too, not
        // just the state sync and the freed seat.
        $this->assertCount(1, $this->catlabApi->sendEmailCalls);
    }

    public function testAWrongTypedReferenceRefundsNothing()
    {
        $order = $this->createRefundableOrder();
        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

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
        $admin->organisations()->attach($order->event->organisation_id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

        $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/refund", [
                'reference' => 'TEST-4242',
                'reason' => 'test'
            ]);

        $this->assertSame(1, $order->event->fresh()->countAvailableTickets(true));
    }

    /**
     * The realistic outage: accounts stays unreachable through the re-sync
     * that follows the failed refund call, not just for the refund call
     * itself. A version of this that only threw from refundOrder() (and let
     * the following getOrder() "recover") would pass even with an unguarded
     * synchronize() call in the timeout branch -- it would never actually
     * exercise that unguarded call. This one does: it must never 500, and
     * it must never claim the refund failed, because it may well not have.
     */
    public function testATimeoutThatPersistsThroughTheResyncNeverReportsFailure()
    {
        $order = $this->createRefundableOrder();
        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

        $this->catlabApi->refundOrderException = new ConnectException(
            'Connection timed out',
            new Psr7Request('POST', 'orders/4242/refund')
        );
        $this->catlabApi->getOrderException = new ConnectException(
            'Connection timed out',
            new Psr7Request('GET', 'orders/4242')
        );

        $response = $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/refund", [
                'reference' => 'TEST-4242',
                'reason' => 'test'
            ]);

        $response->assertRedirect('/admin/orders');
        $response->assertSessionHas('message');

        $message = session('message');
        $this->assertNotNull($message, 'a flash message should have been set');
        $this->assertStringNotContainsStringIgnoringCase('mislukt', $message);
        $this->assertStringNotContainsStringIgnoringCase('failed', $message);
        $this->assertStringContainsStringIgnoringCase('accounts', $message);
    }

    /**
     * 429: accounts' rate limit on refunds. The admin should not be told to
     * retry immediately -- that would just trip the limit again.
     */
    public function testAThrottledRefundSaysToTryAgainLater()
    {
        $order = $this->createRefundableOrder();
        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

        $this->catlabApi->refundOrderException = new BadResponseException(
            'Too many refunds',
            new Psr7Request('POST', 'orders/4242/refund'),
            new Psr7Response(429, [], '{"error":"Too many refunds, try again later."}')
        );

        $response = $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/refund", [
                'reference' => 'TEST-4242',
                'reason' => 'test'
            ]);

        $response->assertRedirect('/admin/orders');
        $this->assertStringContainsString('te veel terugbetalingen', session('message'));
        // No re-sync on this path: nothing was attempted on accounts' side.
        $this->assertSame(Order::STATE_ACCEPTED, $order->fresh()->state);
    }

    /**
     * 409: accounts refuses because the order can no longer be refunded
     * (already refunded, cancelled, amount mismatch, ...). The re-sync that
     * follows must pick up the true state.
     */
    public function testANoLongerRefundableOrderReportsAndResyncs()
    {
        $order = $this->createRefundableOrder();
        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

        $this->catlabApi->refundOrderException = new BadResponseException(
            'Order is not refundable.',
            new Psr7Request('POST', 'orders/4242/refund'),
            new Psr7Response(409, [], '{"error":"Order is not refundable."}')
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

    /**
     * The 409 sibling of
     * testATimeoutThatPersistsThroughTheResyncNeverReportsFailure(): accounts
     * rejects the refund AND the re-sync that reportRejectedByAccounts()
     * attempts afterwards also fails. That re-sync is inside its own
     * try/catch precisely so a struggling accounts cannot turn a rejected
     * refund into a 500 here -- this proves the guard holds rather than
     * just looking right.
     */
    public function testANoLongerRefundableOrderWhoseResyncAlsoFailsDegradesGracefully()
    {
        $order = $this->createRefundableOrder();
        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

        $this->catlabApi->refundOrderException = new BadResponseException(
            'Order is not refundable.',
            new Psr7Request('POST', 'orders/4242/refund'),
            new Psr7Response(409, [], '{"error":"Order is not refundable."}')
        );
        // The re-sync inside reportRejectedByAccounts() calls getOrder()
        // without $expanded; this fires only there, not for the earlier
        // expanded reference/amount check made before the refund attempt.
        $this->catlabApi->getOrderException = new ConnectException(
            'Connection timed out',
            new Psr7Request('GET', 'orders/4242')
        );

        $response = $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/refund", [
                'reference' => 'TEST-4242',
                'reason' => 'test'
            ]);

        $response->assertRedirect('/admin/orders');
        $message = session('message');
        $this->assertNotNull($message, 'a flash message should have been set');
        $this->assertSame(
            'Deze order kon niet meer terugbetaald worden. De status kon niet opnieuw opgehaald worden, '
                . 'controleer de order in accounts.',
            $message
        );
        // Nothing was re-synced: the order's local state is untouched.
        $this->assertSame(Order::STATE_ACCEPTED, $order->fresh()->state);
    }

    /**
     * The complementary case to
     * testATimeoutThatPersistsThroughTheResyncNeverReportsFailure(): here
     * refundOrder() throws but the following getOrder() (via synchronize())
     * still answers, so the re-sync succeeds and discovers the refund
     * actually went through on accounts' side. This must be reported as
     * uncertainty, never as failure -- a false failure invites a second
     * click on a path where money moves.
     */
    public function testATimeoutThatResyncsSuccessfullyReportsUncertaintyNotFailure()
    {
        $order = $this->createRefundableOrder();
        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

        $this->catlabApi->refundOrderException = new ConnectException(
            'Connection timed out',
            new Psr7Request('POST', 'orders/4242/refund')
        );
        // The refund did go through on the other side, and this time the
        // re-sync call itself succeeds in finding that out.
        $this->catlabApi->orderStatus = 'REFUNDED';

        $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/refund", [
                'reference' => 'TEST-4242',
                'reason' => 'test'
            ]);

        $message = session('message');
        $this->assertStringContainsString('mogelijk wel doorgegaan', $message);
        $this->assertStringNotContainsStringIgnoringCase('mislukt', $message);
        $this->assertStringNotContainsStringIgnoringCase('failed', $message);
        // The re-sync found the truth.
        $this->assertSame(Order::STATE_REFUNDED, $order->fresh()->state);
    }

    /**
     * A response in the 400/403/422 family: accounts rejected the request
     * outright, before it ever reached the gateway. Unlike the 5xx/timeout
     * path this is not ambiguous -- nothing moved, so the message must not
     * hedge the way the unknown-outcome message does, and there is nothing
     * new to re-sync.
     */
    public function testAnUnmappedClientErrorIsReportedAsDefinitelyRefused()
    {
        $order = $this->createRefundableOrder();
        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

        $this->catlabApi->refundOrderException = new BadResponseException(
            'Unprocessable entity',
            new Psr7Request('POST', 'orders/4242/refund'),
            new Psr7Response(422, [], '{"error":"Validation failed."}')
        );
        // If this branch re-synced, this would flip the order to REFUNDED.
        // Asserting it stays ACCEPTED below proves it does not.
        $this->catlabApi->orderStatus = 'REFUNDED';

        $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/refund", [
                'reference' => 'TEST-4242',
                'reason' => 'test'
            ]);

        $message = session('message');
        $this->assertStringContainsString('niets terugbetaald', $message);
        $this->assertStringContainsString('422', $message);
        $this->assertStringNotContainsString('mogelijk wel doorgegaan', $message);
        // No re-sync on this path.
        $this->assertSame(Order::STATE_ACCEPTED, $order->fresh()->state);
    }

    /**
     * 401/404: accounts refuses the refund outright -- an auth problem or a
     * gone/unknown order on accounts' side. Both status codes share this one
     * branch, so one test covers it; the message is deliberately terse and,
     * unlike the unmapped-4xx message just above, does not name a status
     * code, so it is asserted here in full rather than by substring -- a
     * substring like "geweigerd" or "niets terugbetaald" also appears in
     * sibling guard messages and would not prove this exact branch ran.
     */
    public function testAnUnauthorizedOrGoneRefundIsReportedAsRefusedWithNoResync()
    {
        $order = $this->createRefundableOrder();
        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

        $this->catlabApi->refundOrderException = new BadResponseException(
            'Not found',
            new Psr7Request('POST', 'orders/4242/refund'),
            new Psr7Response(404, [], '{"error":"Order not found."}')
        );
        // If this branch re-synced, this would flip the order to REFUNDED.
        // Asserting it stays ACCEPTED below proves it does not.
        $this->catlabApi->orderStatus = 'REFUNDED';

        $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/refund", [
                'reference' => 'TEST-4242',
                'reason' => 'test'
            ]);

        $this->assertSame(
            'De terugbetaling werd geweigerd. Controleer de order in accounts.',
            session('message')
        );
        // No re-sync on this path.
        $this->assertSame(Order::STATE_ACCEPTED, $order->fresh()->state);
    }

    /**
     * ApiClient::refundOrder() decodes the response body only *after* a
     * successful HTTP 200, and throws a plain \LogicException (not a
     * GuzzleException) if that decode fails -- at the exact moment the
     * money has already moved. Before the fix this escaped the
     * GuzzleException-only catch and 500ed the request right when the admin
     * most needed a hedged answer, not a crash. This proves the widened
     * catch routes it through the same reportUnknownOutcome() path as a
     * timeout.
     */
    public function testANonGuzzleFailureAfterASuccessfulCallIsHedgedNotA500()
    {
        $order = $this->createRefundableOrder();
        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

        // Simulates ApiClient::refundOrder() failing to decode a 200 body.
        $this->catlabApi->refundOrderException = new \LogicException(
            'Could not decode refund order json api request: not json'
        );
        // Nothing actually changed on accounts' side; the re-sync should
        // find that and leave the order alone.
        $this->catlabApi->orderStatus = Order::STATE_ACCEPTED;

        $response = $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/refund", [
                'reference' => 'TEST-4242',
                'reason' => 'test'
            ]);

        $response->assertRedirect('/admin/orders');
        $message = session('message');
        $this->assertNotNull($message, 'a flash message should have been set');
        $this->assertStringNotContainsStringIgnoringCase('mislukt', $message);
        $this->assertStringNotContainsStringIgnoringCase('failed', $message);
        $this->assertStringContainsStringIgnoringCase('mogelijk wel doorgegaan', $message);
        $this->assertSame(Order::STATE_ACCEPTED, $order->fresh()->state);
    }

    /**
     * 408 (or nginx's 499) is a timeout status code an intermediary emits,
     * not part of accounts' own guard chain. It must be hedged the same as
     * a connection-level timeout, not reported through the unmapped-4xx
     * branch's definite "Er is niets terugbetaald.", which is the one
     * message here that would invite a dangerous second click.
     */
    public function testATimeoutStatusCodeIsHedgedNotDefinitelyRefused()
    {
        $order = $this->createRefundableOrder();
        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

        $this->catlabApi->refundOrderException = new BadResponseException(
            'Request Timeout',
            new Psr7Request('POST', 'orders/4242/refund'),
            new Psr7Response(408, [], '{"error":"Request Timeout"}')
        );
        $this->catlabApi->orderStatus = Order::STATE_ACCEPTED;

        $response = $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/refund", [
                'reference' => 'TEST-4242',
                'reason' => 'test'
            ]);

        $response->assertRedirect('/admin/orders');
        $message = session('message');
        $this->assertStringContainsStringIgnoringCase('mogelijk wel doorgegaan', $message);
        $this->assertStringNotContainsString('niets terugbetaald', $message);
        $this->assertSame(Order::STATE_ACCEPTED, $order->fresh()->state);
    }

    /**
     * getActiveOrganisation() returns any organisation the user is attached
     * to at *any* pivot role, not just an admin one. A global admin flag
     * combined with a non-admin membership of the order's organisation must
     * still be refused: every other order path in the panel authorizes
     * through Organisation::isAdmin(), and this one must not be a notch
     * weaker.
     */
    public function testAUserAttachedAtANonAdminRoleGets404EvenWithGlobalAdminFlag()
    {
        $order = $this->createRefundableOrder();

        $admin = $this->createAdmin();
        // Not Organisation::ROLE_ADMIN (10) -- any other pivot role.
        $admin->organisations()->attach($order->event->organisation_id, [ 'role' => 1 ]);

        $this->actingAs($admin)->get("/admin/orders/{$order->id}/refund")->assertStatus(404);
    }

    /**
     * An accounts outage while loading the live reference/amount must not
     * 500 the confirm page: the action renders on every order row, so
     * during an outage every click would otherwise be a Whoops page instead
     * of the explanation the design requires.
     */
    public function testTheConfirmPageExplainsAnAccountsOutageInsteadOf500ing()
    {
        $order = $this->createRefundableOrder();
        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

        $this->catlabApi->getOrderExpandedException = new ConnectException(
            'Connection timed out',
            new Psr7Request('GET', 'orders/4242?expanded=1')
        );

        $response = $this->actingAs($admin)->get("/admin/orders/{$order->id}/refund");

        $response->assertStatus(200);
        $response->assertSee('niet bereikbaar');
        $response->assertDontSee('Terugbetalen</button>', false);
        $this->assertCount(0, $this->catlabApi->refundOrderCalls);
    }

    /**
     * processRefund()'s pre-flight getOrderData(true) catch was previously
     * untested: FakeCatLabApiClient only let getOrder() throw when called
     * *without* $expanded (i.e. via synchronize()'s re-sync), never on the
     * expanded call this pre-flight check itself makes. This exercises that
     * catch directly, and doubles as coverage for the GET confirm page fix
     * above via the shared $getOrderExpandedException fixture.
     */
    public function testAPreflightAccountsFailureBlocksTheRefundWithoutAttemptingIt()
    {
        $order = $this->createRefundableOrder();
        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

        $this->catlabApi->getOrderExpandedException = new ConnectException(
            'Connection timed out',
            new Psr7Request('GET', 'orders/4242?expanded=1')
        );

        $response = $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/refund", [
                'reference' => 'TEST-4242',
                'reason' => 'test'
            ]);

        $response->assertRedirect('/admin/orders');
        $this->assertStringContainsString('niets terugbetaald', session('message'));
        $this->assertCount(0, $this->catlabApi->refundOrderCalls);
        $this->assertSame(Order::STATE_ACCEPTED, $order->fresh()->state);
    }

    /**
     * The POST-side !isRefundable() guard was previously exercised only via
     * the GET confirmation page. This proves the server re-checks it on the
     * POST too, independently of what the GET page (or the disabled button)
     * shows.
     */
    public function testAPostToACancelledOrderRefundsNothing()
    {
        $order = $this->createRefundableOrder();
        $order->state = Order::STATE_CANCELLED;
        $order->save();

        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

        $response = $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/refund", [
                'reference' => 'TEST-4242',
                'reason' => 'test'
            ]);

        $response->assertRedirect('/admin/orders');
        $this->assertStringContainsString('kan hier niet terugbetaald', session('message'));
        $this->assertCount(0, $this->catlabApi->refundOrderCalls);
        $this->assertSame(Order::STATE_CANCELLED, $order->fresh()->state);
    }

    /**
     * The organisation-scoping 404 was previously exercised only via GET.
     * This proves the POST path is scoped the same way, and that nothing is
     * attempted on accounts before the 404 is thrown.
     */
    public function testAPostFromAnotherOrganisationGets404AndRefundsNothing()
    {
        $order = $this->createRefundableOrder();

        $admin = $this->createAdmin();
        $admin->organisations()->attach($this->createOrganisation()->id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

        $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/refund", [
                'reference' => 'TEST-4242',
                'reason' => 'test'
            ])
            ->assertStatus(404);

        $this->assertCount(0, $this->catlabApi->refundOrderCalls);
        $this->assertSame(Order::STATE_ACCEPTED, $order->fresh()->state);
    }

    /**
     * A missing live price must not print "€ 0,00" on the "Dit is
     * definitief" screen. Accounts' amount binding would reject an actual
     * refund at that (false) amount, so no money is at risk -- but a false
     * amount on this specific screen is not acceptable regardless.
     */
    public function testAMissingLivePriceIsTreatedAsNotRefundable()
    {
        $order = $this->createRefundableOrder();
        $admin = $this->createAdmin();
        $admin->organisations()->attach($order->event->organisation_id, [ 'role' => \App\Models\Organisation::ROLE_ADMIN ]);

        $this->catlabApi->price = null;

        $response = $this->actingAs($admin)->get("/admin/orders/{$order->id}/refund");

        $response->assertStatus(200);
        $response->assertDontSee('Terugbetalen</button>', false);
        $response->assertDontSee('0,00');
        $this->assertCount(0, $this->catlabApi->refundOrderCalls);
    }
}
