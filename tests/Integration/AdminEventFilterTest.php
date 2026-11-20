<?php

namespace Tests\Integration;

use App\Models\User;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * The admin events list can filter on conditions (charon 1.13), e.g. all
 * events that still have an event url: /admin/events?event_url[not_empty]=1
 */
class AdminEventFilterTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    /** @var User */
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $organisation = $this->createOrganisation();

        $withUrl = $this->createEvent($organisation);
        $withUrl->name = 'Quiz with a link';
        $withUrl->event_url = 'https://example.com/old-link';
        $withUrl->save();

        $withoutUrl = $this->createEvent($organisation);
        $withoutUrl->name = 'Quiz without a link';
        $withoutUrl->event_url = null;
        $withoutUrl->save();

        $this->admin = $this->createUser();
        $this->admin->admin = 1;
        $this->admin->save();
        $organisation->users()->attach($this->admin, [ 'role' => 10 ]);
    }

    public function testNotEmptyShowsOnlyEventsWithAnUrl()
    {
        $this->actingAs($this->admin)
            ->get('/admin/events?event_url[not_empty]=1')
            ->assertOk()
            ->assertSee('Quiz with a link')
            ->assertDontSee('Quiz without a link');
    }

    public function testEmptyShowsOnlyEventsWithoutAnUrl()
    {
        $this->actingAs($this->admin)
            ->get('/admin/events?event_url[empty]=1')
            ->assertOk()
            ->assertSee('Quiz without a link')
            ->assertDontSee('Quiz with a link');
    }

    public function testUnknownConditionIsABadRequest()
    {
        $this->actingAs($this->admin)
            ->get('/admin/events?event_url[bogus]=1')
            ->assertStatus(400);
    }
}
