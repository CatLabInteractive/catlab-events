<?php

namespace Tests\Integration;

use App\Models\Order;
use App\Models\User;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * Security audit 2026-08-27 (catlab-events section):
 *  - orders/{id} was readable by any logged-in user (team, items, and the
 *    livestream "start game" link), fetched with the OWNER's stored
 *    accounts token;
 *  - orders/{id}/thanks (same link) was public;
 *  - orders/{id}/sync (accounts' notify callback, so session-less by
 *    design) could be triggered by anyone for any order -- transitions and
 *    confirmation e-mails on demand;
 *  - Order::synchronize() compared a non-existent `status` attribute, so a
 *    locally cancelled order could be flipped back to pending.
 */
class OrderAccessTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    /** @return array [Order, User $buyer] */
    private function pendingOrder(): array
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);
        $buyer = $this->createUser();

        $this->actingAs($buyer)->post("/events/{$event->id}/register/{$category->id}/process");
        $order = Order::query()->orderBy('id', 'desc')->firstOrFail();

        $this->app['auth']->guard()->logout();
        $this->flushSession();

        return [ $order, $buyer ];
    }

    private function admin(): User
    {
        $admin = $this->createUser();
        $admin->admin = true;
        $admin->save();
        return $admin;
    }

    // -- orders/{id} --------------------------------------------------------

    public function testStrangerCannotViewAnotherUsersOrder()
    {
        list ($order) = $this->pendingOrder();
        $stranger = $this->createUser();

        $this->actingAs($stranger)->get("/orders/{$order->id}")->assertStatus(403);
    }

    public function testBuyerAndAdminCanViewTheOrder()
    {
        list ($order, $buyer) = $this->pendingOrder();

        $this->actingAs($buyer)->get("/orders/{$order->id}")->assertStatus(200);
        $this->actingAs($this->admin())->get("/orders/{$order->id}")->assertStatus(200);
    }

    // -- orders/{id}/thanks -------------------------------------------------

    public function testThanksPageRequiresLoginAndOwnership()
    {
        list ($order, $buyer) = $this->pendingOrder();

        $this->get("/orders/{$order->id}/thanks")->assertRedirect('/login');

        $stranger = $this->createUser();
        $this->actingAs($stranger)->get("/orders/{$order->id}/thanks")->assertStatus(403);

        $this->actingAs($buyer)->get("/orders/{$order->id}/thanks")->assertStatus(200);
    }

    // -- orders/{id}/sync ---------------------------------------------------

    public function testUnsignedSyncIsRefusedAndChangesNothing()
    {
        list ($order) = $this->pendingOrder();
        $this->catlabApi->orderStatus = 'ACCEPTED';

        $this->get("/orders/{$order->id}/sync")->assertStatus(403);
        $this->get("/orders/{$order->id}/sync?sig=deadbeef")->assertStatus(403);

        $this->assertEquals(Order::STATE_PENDING, $order->fresh()->state, 'an unsigned call must not transition the order');
        $this->assertCount(0, $this->catlabApi->sendEmailCalls);
    }

    public function testTheCallbackHandedToAccountsIsSignedAndWorksWithoutASession()
    {
        list ($order) = $this->pendingOrder();
        $callback = $this->catlabApi->createOrderCalls[0]['callback'];

        $this->assertStringContainsString("/orders/{$order->id}/sync", $callback);
        $this->assertStringContainsString('sig=', $callback);

        $this->catlabApi->orderStatus = 'ACCEPTED';
        $this->get(parse_url($callback, PHP_URL_PATH) . '?' . parse_url($callback, PHP_URL_QUERY))->assertStatus(200);

        $this->assertEquals(Order::STATE_ACCEPTED, $order->fresh()->state);
    }

    public function testSignatureIsPerOrder()
    {
        list ($first) = $this->pendingOrder();
        list ($second) = $this->pendingOrder();
        $this->assertNotEquals($first->id, $second->id);

        // The second order's id with the first order's signature: refused,
        // and the second order stays pending.
        $this->catlabApi->orderStatus = 'ACCEPTED';
        $this->get('/orders/' . $second->id . '/sync?sig=' . $first->syncSignature())->assertStatus(403);
        $this->assertEquals(Order::STATE_PENDING, $second->fresh()->state);
    }

    // -- Order::synchronize() -----------------------------------------------

    public function testACancelledOrderIsNotFlippedBackToPendingBySync()
    {
        list ($order) = $this->pendingOrder();
        $order->state = Order::STATE_CANCELLED;
        $order->save();

        $this->catlabApi->orderStatus = 'PENDING';
        $this->get('/orders/' . $order->id . '/sync?sig=' . $order->syncSignature())->assertStatus(200);

        $this->assertEquals(Order::STATE_CANCELLED, $order->fresh()->state, 'synchronize() must compare the real state column');
    }

    public function testAnUnchangedStateDoesNotRetrigger()
    {
        list ($order) = $this->pendingOrder();

        $this->catlabApi->orderStatus = 'ACCEPTED';
        $this->get('/orders/' . $order->id . '/sync?sig=' . $order->syncSignature())->assertStatus(200);
        $this->assertCount(1, $this->catlabApi->sendEmailCalls, 'first acceptance sends the confirmation');

        $this->get('/orders/' . $order->id . '/sync?sig=' . $order->syncSignature())->assertStatus(200);
        $this->assertCount(1, $this->catlabApi->sendEmailCalls, 'a repeated sync with the same state must not re-send it');
    }
}
