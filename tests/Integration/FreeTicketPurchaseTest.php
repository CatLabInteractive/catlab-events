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
        $response->assertRedirect(action('OrderController@thanks', [$order->id]));

        // No payment API involved; confirmation email requested via the API.
        $this->assertCount(0, $this->catlabApi->createOrderCalls);
        $this->assertCount(1, $this->catlabApi->sendEmailCalls);

        // The thanks page renders and the order STAYS accepted afterwards.
        $this->actingAs($user)->get("/orders/{$order->id}/thanks")->assertStatus(200);
        $this->assertEquals(Order::STATE_ACCEPTED, $order->fresh()->state);
    }
}
