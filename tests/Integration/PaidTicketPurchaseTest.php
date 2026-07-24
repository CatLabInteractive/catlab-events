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
