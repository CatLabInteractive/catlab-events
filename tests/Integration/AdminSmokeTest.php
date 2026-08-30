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
