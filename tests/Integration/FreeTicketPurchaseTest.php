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
        // The thanks URL is signed (it doubles as the payment return URL,
        // which may be opened on another device -- audit 2026-08-27).
        $response->assertRedirect($order->getThanksUrl());
        $this->assertStringContainsString("/orders/{$order->id}/thanks?sig=", $order->getThanksUrl());

        // No payment API involved; confirmation email requested via the API.
        $this->assertCount(0, $this->catlabApi->createOrderCalls);
        $this->assertCount(1, $this->catlabApi->sendEmailCalls);
        // Sent in the organisation's name.
        $this->assertSame('Test organisation', $this->catlabApi->sendEmailCalls[0]['fromName']);

        // Order confirmation is tracked via the faked Eukles client.
        $this->assertNotEmpty($this->eukles->tracked);

        // A free order has no accounts order (no catlab_order_id), so
        // getOrderData() is null: the tracked order still carries a price
        // of 0 and an empty reference (#56).
        $this->assertNull($order->catlab_order_id);
        $orderAttributes = $this->findTrackedOrderAttributes($order);
        $this->assertNotNull($orderAttributes, 'The tracked events should include the order');
        $this->assertSame(0, $orderAttributes['price']);
        $this->assertNull($orderAttributes['reference']);
        $this->assertNull($orderAttributes['receipt']);

        // The thanks page renders and the order STAYS accepted afterwards.
        $this->actingAs($user)->get("/orders/{$order->id}/thanks")->assertStatus(200);
        $this->assertEquals(Order::STATE_ACCEPTED, $order->fresh()->state);
    }

    /**
     * The Eukles attributes of $order, as serialised in a tracked event.
     */
    private function findTrackedOrderAttributes(Order $order): ?array
    {
        foreach ($this->eukles->tracked as $event) {
            foreach ($event->getData()['data']['items'] as $item) {
                if (($item['type'] ?? null) === 'order' && $item['uid'] == $order->getEuklesId()) {
                    return $item['attributes'];
                }
            }
        }

        return null;
    }
}
