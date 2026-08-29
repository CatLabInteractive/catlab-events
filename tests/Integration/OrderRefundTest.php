<?php

namespace Tests\Integration;

use App\Models\Order;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as Psr7Request;
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
}
