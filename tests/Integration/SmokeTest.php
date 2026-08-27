<?php

namespace Tests\Integration;

use Tests\Integration\Concerns\CreatesEventFixtures;

class SmokeTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    public function testHomepageRenders()
    {
        // EventController@index redirects to /admin when no Organisation exists
        // at all (Organisation::getRepresentedOrganisation() falls back to
        // Organisation::first()); a fresh test database has none, so we need one.
        $this->createOrganisation();

        $this->get('/')->assertStatus(200);
    }

    public function testEventPageRenders()
    {
        $event = $this->createEvent($this->createOrganisation());
        $this->createTicketCategory($event, 10.0);

        $response = $this->get('/events/' . $event->id);

        $response->assertStatus(200);
        $response->assertSee($event->name);
    }

    public function testTicketSelectionPageRendersForGuests()
    {
        $event = $this->createEvent($this->createOrganisation());
        $this->createTicketCategory($event, 10.0);

        // Guests get the "log in to register" explanation page.
        $this->get('/events/' . $event->id . '/register')->assertStatus(200);
    }
}
