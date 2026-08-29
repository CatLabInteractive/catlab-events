<?php

namespace Tests\Integration;

use App\Models\Order;
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
}
