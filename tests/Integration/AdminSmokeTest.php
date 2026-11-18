<?php

namespace Tests\Integration;

use App\Models\Organisation;
use App\Models\User;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * The admin CRUD pages are rendered by laravel-charon-frontend / laravel-table
 * (index tables, forms, detail pages). Nothing else exercises those
 * controllers, so a broken package upgrade would only show up in production.
 */
class AdminSmokeTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    /** Site admin (IsAdmin middleware) who also administers $organisation (policies). */
    private function adminOf(Organisation $organisation): User
    {
        $admin = $this->createUser();
        $admin->admin = true;
        $admin->save();

        $organisation->users()->attach($admin, [ 'role' => 10 ]);

        return $admin;
    }

    public function testAdminDashboardAndCrudIndexesRender()
    {
        $organisation = $this->createOrganisation();
        $event = $this->createEvent($organisation);
        $this->createTicketCategory($event, 10.0);
        $admin = $this->adminOf($organisation);

        $this->actingAs($admin)->get('/admin')->assertRedirect('/admin/events');

        // OrganisationPolicy::index is deliberately closed.
        $this->actingAs($admin)->get('/admin/organisations')->assertStatus(403);

        foreach ([
            '/admin/venues',
            '/admin/people',
            '/admin/orders',
            '/admin/livestreams',
            '/admin/assets',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertStatus(200);
        }

        // The index table actually lists the data.
        $response = $this->actingAs($admin)->get('/admin/events');
        $response->assertStatus(200);
        $response->assertSee($event->name);
        $response->assertSee('/admin/events/' . $event->id);
    }

    public function testAdminCrudFormsAndDetailPagesRender()
    {
        $organisation = $this->createOrganisation();
        $event = $this->createEvent($organisation);
        $category = $this->createTicketCategory($event, 10.0);
        $admin = $this->adminOf($organisation);

        foreach ([
            '/admin/events/create',
            '/admin/events/' . $event->id,
            '/admin/events/' . $event->id . '/edit',
            '/admin/events/' . $event->id . '/ticketCategories',
            '/admin/events/' . $event->id . '/ticketCategories/' . $category->id . '/edit',
            '/admin/events/' . $event->id . '/eventDates',
            '/admin/venues/create',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertStatus(200);
        }
    }

    /**
     * The event form comes in sections, and the admin speaks Dutch.
     */
    public function testTheEventFormIsGroupedAndInDutch()
    {
        $organisation = $this->createOrganisation();
        $event = $this->createEvent($organisation);
        $admin = $this->adminOf($organisation);

        $html = $this->actingAs($admin)->get('/admin/events/' . $event->id . '/edit')
            ->assertStatus(200)
            ->assertSee('Tickets en inschrijving</legend>', false)
            ->assertSee('Mensen</legend>', false)
            ->assertSee('value="Opslaan"', false)
            ->assertSee('>Annuleren</a>', false)
            ->getContent();

        $legend = strpos($html, 'Tickets en inschrijving</legend>');
        $this->assertTrue($legend < strpos($html, 'name="fields[vat_percentage]"'));
        $this->assertTrue($legend < strpos($html, 'name="fields[team_size]"'));

        $this->actingAs($admin)->get('/admin/events/' . $event->id)
            ->assertSee('>Bewerken</a>', false)
            ->assertSee('>Verwijderen</a>', false);
    }

    /**
     * Ticket categories live under an event: their pages say which event,
     * and saving one from the event's page goes back there.
     */
    public function testTicketCategoryPagesLeadBackToTheirEvent()
    {
        $organisation = $this->createOrganisation();
        $event = $this->createEvent($organisation);
        $category = $this->createTicketCategory($event, 10.0);
        $admin = $this->adminOf($organisation);

        $date = new \App\Models\EventDate();
        $date->event()->associate($event);
        $date->startDate = new \DateTime('+1 month');
        $date->endDate = new \DateTime('+1 month +3 hours');
        $date->save();

        $base = '/admin/events/' . $event->id . '/ticketCategories/' . $category->id;

        $this->actingAs($admin)->get($base . '/edit?return=admin/events/' . $event->id)
            ->assertStatus(200)
            ->assertSee('<li class="breadcrumb-item"><a href="http://localhost/admin/events/' . $event->id, false)
            ->assertSee('">' . e($event->name) . '</a></li>', false)
            ->assertSee('<input type="hidden" name="return" value="/admin/events/' . $event->id . '">', false);

        $this->actingAs($admin)->put($base, [
            'return' => '/admin/events/' . $event->id,
            'fields' => [
                'name' => 'Early bird',
                'price' => '8',
                'eventDates' => [ 'ids' => [ '', (string) $date->id ] ],
            ],
        ])->assertRedirect('http://localhost/admin/events/' . $event->id);

        $this->assertSame('Early bird', $category->fresh()->name);
    }

    public function testAdminCanSetATicketDescription()
    {
        $organisation = $this->createOrganisation();
        $event = $this->createEvent($organisation);
        $category = $this->createTicketCategory($event, 10.0);
        $admin = $this->adminOf($organisation);

        $date = new \App\Models\EventDate();
        $date->event()->associate($event);
        $date->startDate = new \DateTime('+1 month');
        $date->endDate = new \DateTime('+1 month +3 hours');
        $date->save();

        $this->actingAs($admin)
            ->get('/admin/events/' . $event->id . '/ticketCategories/' . $category->id . '/edit')
            ->assertSee('name="fields[description]"', false);

        $this->actingAs($admin)->put('/admin/events/' . $event->id . '/ticketCategories/' . $category->id, [
            'fields' => [
                'name' => 'Single player',
                'description' => 'Je speelt de quiz alleen. Je hebt twee toestellen nodig.',
                'price' => '10',
                'eventDates' => [ 'ids' => [ '', (string) $date->id ] ],
            ],
        ]);

        $this->assertSame('Je speelt de quiz alleen. Je hebt twee toestellen nodig.', $category->fresh()->description);
    }

    /**
     * The order detail page renders a table for every relationship on the
     * resource. Order has an expanded single ticketCategory relationship, so
     * this page asks whether the current user may *create* a TicketCategory --
     * with OrderController's own authorize parameters.
     */
    public function testOrderDetailPageRenders()
    {
        $organisation = $this->createOrganisation();
        $event = $this->createEvent($organisation);
        $category = $this->createTicketCategory($event, 10.0);
        $admin = $this->adminOf($organisation);

        $order = new \App\Models\Order();
        $order->event()->associate($event);
        $order->user()->associate($admin);
        $order->ticketCategory()->associate($category);
        $order->state = \App\Models\Order::STATE_ACCEPTED;
        $order->save();

        $this->actingAs($admin)->get('/admin/orders/' . $order->id)->assertStatus(200);
    }

    /**
     * laravel-charon-frontend enables sorting and filtering on every index
     * table; the controls only appear for fields the resource definition
     * marks sortable()/filterable(). Guards the declarations, not the package.
     */
    public function testIndexTablesExposeSortingAndFiltering()
    {
        $organisation = $this->createOrganisation();
        $this->createEvent($organisation);
        $admin = $this->adminOf($organisation);

        $response = $this->actingAs($admin)->get('/admin/events');
        $response->assertStatus(200);

        // The filter bar, with an input for the filterable "name" field.
        $response->assertSee('table-filters', false);
        $response->assertSee('id="table-filter-name"', false);

        // A sortable "name" column header links to charon's sort parameter.
        $response->assertSee('sort=name', false);
    }

    /**
     * Sorting turns into an ORDER BY on the field's own name, so a sortable()
     * declaration on anything that isn't a real column (an accessor, a dotted
     * path) blows up only when someone clicks the header. Walk them.
     */
    public function testSortingAndFilteringActuallyRun()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->adminOf($organisation);
        $venue = $this->createVenue($admin);

        $event = $this->createEvent($organisation);
        $event->venue()->associate($venue);
        $event->save();
        $category = $this->createTicketCategory($event, 10.0);

        foreach ([
            '/admin/events?sort=name',
            '/admin/events?sort=!name',
            '/admin/venues?sort=name',
            '/admin/venues?sort=city',
            '/admin/people?sort=first_name',
            '/admin/people?sort=last_name',
            '/admin/livestreams?sort=title',
            '/admin/series?sort=name',
            '/admin/series?sort=slug',
            '/admin/competitions?sort=name',
            '/admin/orders?sort=date',
            '/admin/events/' . $event->id . '/ticketCategories?sort=name',
            '/admin/events/' . $event->id . '/ticketCategories?sort=price',
            '/admin/events/' . $event->id . '/eventDates?sort=startDate',
            '/admin/events/' . $event->id . '/eventDates?sort=endDate',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertStatus(200);
        }

        // Filtering narrows the collection rather than erroring out.
        $this->actingAs($admin)->get('/admin/venues?name=Test venue')
            ->assertStatus(200)
            ->assertSee('Test venue');

        $this->actingAs($admin)->get('/admin/venues?name=Nothing matches this')
            ->assertStatus(200)
            ->assertDontSee('Test venue');

        // A blank filter input must not filter on the empty string.
        $this->actingAs($admin)->get('/admin/venues?name=')
            ->assertStatus(200)
            ->assertSee('Test venue');

        $this->assertNotNull($category->id);
    }

    /**
     * Relationship cells render as links to the related resource's own admin
     * page, labelled by the resource rather than shown as a raw identifier.
     * Event::venue is visible on the detail page, not the index. The link
     * only appears because EventController registers VenueController as the
     * child controller for VenueResourceDefinition.
     */
    public function testRelationshipCellsLinkToTheRelatedResource()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->adminOf($organisation);
        $venue = $this->createVenue($admin);

        $event = $this->createEvent($organisation);
        $event->venue()->associate($venue);
        $event->save();

        $response = $this->actingAs($admin)->get('/admin/events/' . $event->id);
        $response->assertStatus(200);
        $response->assertSee('/admin/venues/' . $venue->id . '">Test venue</a>', false);
    }

    public function testAdminLayoutRendersTheGroupedSidebar()
    {
        $organisation = $this->createOrganisation();
        $this->createEvent($organisation);
        $admin = $this->adminOf($organisation);

        $response = $this->actingAs($admin)->get('/admin/events');
        $response->assertStatus(200);

        $response->assertSee('admin-sidebar', false);
        $response->assertSee('admin-content', false);

        // Grouped nav headings.
        $response->assertSee('Programme', false);
        $response->assertSee('Sales', false);

        // The organisation switcher moved into the sidebar footer.
        $response->assertSee('sidebar-footer', false);
        $response->assertSee($organisation->name, false);
    }

    public function testNonAdminsAreSentAwayFromTheAdminArea()
    {
        $this->createOrganisation();
        $this->createUser(); // the first user ever created becomes admin (User::boot)
        $user = $this->createUser();
        $this->assertFalse((bool)$user->fresh()->admin);

        $this->actingAs($user)->get('/admin/events')->assertRedirect('/');
    }

    public function testGroupFormRendersForMembers()
    {
        $this->createOrganisation();
        $user = $this->createUser();

        $this->actingAs($user)->get('/groups/create')->assertStatus(200);
    }
}
