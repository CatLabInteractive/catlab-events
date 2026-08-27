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

        // Step 2: the PSP reports payment — accounts GETs the (signed)
        // callback we handed it, which flips the order to accepted and
        // triggers the confirmation email.
        $this->catlabApi->orderStatus = 'ACCEPTED';
        $this->get("/orders/{$order->id}/sync?sig=" . $order->syncSignature())->assertStatus(200);

        $this->assertEquals(Order::STATE_ACCEPTED, $order->fresh()->state);
        $this->assertCount(1, $this->catlabApi->sendEmailCalls);

        // Step 3: the thanks page renders the confirmation.
        $this->actingAs($user)->get("/orders/{$order->id}/thanks")->assertStatus(200);
        $this->assertEquals(Order::STATE_ACCEPTED, $order->fresh()->state);
    }

    public function testSyncCallbackWorksUnauthenticated()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);
        $user = $this->createUser();

        $this->actingAs($user)
            ->post("/events/{$event->id}/register/{$category->id}/process");

        $order = Order::query()->first();
        $this->assertNotNull($order);

        // The PSP calls the callback without any session — it must work
        // for guests, or real payment confirmations break. It carries the
        // per-order signature instead (security audit 2026-08-27).
        $this->app['auth']->guard()->logout();
        $this->flushSession();

        $this->catlabApi->orderStatus = 'ACCEPTED';
        $this->get("/orders/{$order->id}/sync?sig=" . $order->syncSignature())->assertStatus(200);

        $this->assertEquals(Order::STATE_ACCEPTED, $order->fresh()->state);
    }
}
