<?php

namespace Tests\Integration;

use App\Models\Order;
use Tests\Integration\Concerns\CreatesEventFixtures;

class PurchaseGuardsTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    public function testOldPerCategoryLinkRedirectsToThePublicForm()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 10.0);

        $this->get("/events/{$event->id}/register/{$category->id}")
            ->assertRedirect(action('EventController@selectTicketCategory', [ $event->id, 'category' => $category->id ]));

        // The old team-select step's form posted here.
        $this->actingAs($this->createUser())
            ->post("/events/{$event->id}/register/{$category->id}", [ 'group' => 1 ])
            ->assertRedirect(action('EventController@selectTicketCategory', [ $event->id, 'category' => $category->id ]));
        $this->assertEquals(0, Order::query()->count());
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
