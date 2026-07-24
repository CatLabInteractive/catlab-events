<?php

namespace Tests\Integration;

use App\Models\Order;
use Tests\Integration\Concerns\CreatesEventFixtures;

class PurchaseGuardsTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    public function testTicketRegistrationRequiresLogin()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 10.0);

        $this->get("/events/{$event->id}/register/{$category->id}")
            ->assertRedirect('/login');
    }

    public function testProcessingAnOrderRequiresLogin()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 10.0);

        $this->post("/events/{$event->id}/register/{$category->id}/process")
            ->assertRedirect('/login');

        $this->assertEquals(0, Order::query()->count());
    }
}
