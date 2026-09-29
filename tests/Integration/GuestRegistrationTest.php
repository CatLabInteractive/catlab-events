<?php

namespace Tests\Integration;

use App\Models\Event;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\User;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * Admins register guests (or guest teams) from the admin panel, without
 * the guest ever creating an account: an accepted order with no user.
 */
class GuestRegistrationTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    private function adminOf(Organisation $organisation): User
    {
        $admin = $this->createUser();
        $admin->admin = true;
        $admin->save();

        $organisation->users()->attach($admin, [ 'role' => Organisation::ROLE_ADMIN ]);

        return $admin;
    }

    public function testTheGuestsPageRendersAndIsLinkedFromTheEventList()
    {
        $organisation = $this->createOrganisation();
        $event = $this->createEvent($organisation);
        $category = $this->createTicketCategory($event, 10.0);
        $admin = $this->adminOf($organisation);

        $response = $this->actingAs($admin)->get("/admin/events/{$event->id}/guests");
        $response->assertStatus(200);
        $response->assertSee($category->name);

        $this->actingAs($admin)->get('/admin/events')
            ->assertStatus(200)
            ->assertSee(action('Admin\GuestRegistrationController@index', [ $event->id ]), false);
    }

    public function testAGuestIsRegisteredWithoutAnAccount()
    {
        $organisation = $this->createOrganisation();
        $event = $this->createEvent($organisation);
        $category = $this->createTicketCategory($event, 10.0);
        $admin = $this->adminOf($organisation);

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/guests", [
                'name' => 'Famous Person',
                'email' => 'famous@example.com',
                'notes' => 'Via the sponsor',
                'ticketCategory' => $category->id
            ])
            ->assertRedirect(action('Admin\GuestRegistrationController@index', [ $event->id ]));

        $order = Order::query()->first();
        $this->assertNotNull($order);
        $this->assertSame(Order::STATE_ACCEPTED, $order->state);
        $this->assertTrue($order->isGuest());
        $this->assertNull($order->user_id);
        $this->assertNull($order->group_id);
        $this->assertSame('Famous Person', $order->getAttendeeName());
        $this->assertSame('Via the sponsor', $order->guest_notes);

        // Nothing is paid: no accounts order, but the guest does get the
        // regular confirmation mail at the address the admin entered.
        $this->assertCount(0, $this->catlabApi->createOrderCalls);
        $this->assertCount(1, $this->catlabApi->sendEmailCalls);
        $this->assertSame('famous@example.com', $this->catlabApi->sendEmailCalls[0]['target']);

        // Accounts only mails on behalf of a user (users/{id}/mail), and the
        // guest has none: it goes out as the admin registering them.
        $this->assertNotNull($this->catlabApi->sendEmailCalls[0]['user']);
        $this->assertSame($admin->id, $this->catlabApi->sendEmailCalls[0]['user']->id);

        $this->actingAs($admin)->get("/admin/events/{$event->id}/guests")
            ->assertStatus(200)
            ->assertSee('Famous Person');
    }

    public function testAGuestTeamGetsATeamThatAttendsTheEvent()
    {
        $organisation = $this->createOrganisation();
        $event = $this->createEvent($organisation);
        $event->requires_team = true;
        $event->save();
        $category = $this->createTicketCategory($event, 10.0);
        $admin = $this->adminOf($organisation);

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/guests", [
                'name' => 'The Famous Five',
                'ticketCategory' => $category->id
            ])
            ->assertRedirect();

        $order = Order::query()->first();
        $this->assertNotNull($order->group);
        $this->assertSame('The Famous Five', $order->group->name);
        $this->assertTrue($event->isRegistered($order->group));
        $this->assertSame([ 'The Famous Five' ], $event->attendees()->pluck('name')->all());

        // No address given, no team members: nobody to mail.
        $this->assertCount(0, $this->catlabApi->sendEmailCalls);
    }

    public function testCancellingAGuestRegistration()
    {
        $organisation = $this->createOrganisation();
        $event = $this->createEvent($organisation);
        $category = $this->createTicketCategory($event, 10.0);
        $admin = $this->adminOf($organisation);

        $this->actingAs($admin)->post("/admin/events/{$event->id}/guests", [
            'name' => 'Famous Person',
            'email' => 'famous@example.com',
            'ticketCategory' => $category->id
        ]);
        $order = Order::query()->first();

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/guests/{$order->id}/cancel")
            ->assertRedirect();

        $this->assertSame(Order::STATE_CANCELLED, $order->fresh()->state);
        $this->assertCount(2, $this->catlabApi->sendEmailCalls);
        $this->assertSame('famous@example.com', $this->catlabApi->sendEmailCalls[1]['target']);
        $this->assertSame($admin->id, $this->catlabApi->sendEmailCalls[1]['user']->id);
    }

    public function testOnlyGuestOrdersCanBeCancelledHere()
    {
        $organisation = $this->createOrganisation();
        $event = $this->createEvent($organisation);
        $category = $this->createTicketCategory($event, 10.0);
        $admin = $this->adminOf($organisation);

        $order = new Order();
        $order->event()->associate($event);
        $order->ticketCategory()->associate($category);
        $order->user()->associate($this->createUser());
        $order->state = Order::STATE_ACCEPTED;
        $order->save();

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/guests/{$order->id}/cancel")
            ->assertStatus(404);

        $this->assertSame(Order::STATE_ACCEPTED, $order->fresh()->state);
    }

    public function testANameIsRequired()
    {
        $organisation = $this->createOrganisation();
        $event = $this->createEvent($organisation);
        $category = $this->createTicketCategory($event, 10.0);
        $admin = $this->adminOf($organisation);

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/guests", [ 'ticketCategory' => $category->id ])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Order::query()->count());
    }

    public function testATicketCategoryOfAnotherEventIsRejected()
    {
        $organisation = $this->createOrganisation();
        $event = $this->createEvent($organisation);
        $this->createTicketCategory($event, 10.0);
        $otherCategory = $this->createTicketCategory($this->createEvent($organisation), 10.0);
        $admin = $this->adminOf($organisation);

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/guests", [
                'name' => 'Famous Person',
                'ticketCategory' => $otherCategory->id
            ])
            ->assertStatus(404);

        $this->assertSame(0, Order::query()->count());
    }

    public function testAnAdminOfAnotherOrganisationGets404()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 10.0);
        $admin = $this->adminOf($this->createOrganisation());

        $this->actingAs($admin)->get("/admin/events/{$event->id}/guests")->assertStatus(404);
        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/guests", [
                'name' => 'Famous Person',
                'ticketCategory' => $category->id
            ])
            ->assertStatus(404);

        $this->assertSame(0, Order::query()->count());
    }

    public function testAGuestOrderShowsInTheAdminOrderList()
    {
        $organisation = $this->createOrganisation();
        $event = $this->createEvent($organisation);
        $category = $this->createTicketCategory($event, 10.0);
        $admin = $this->adminOf($organisation);

        $this->actingAs($admin)->post("/admin/events/{$event->id}/guests", [
            'name' => 'Famous Person',
            'ticketCategory' => $category->id
        ]);

        $this->actingAs($admin)->get('/admin/orders')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/events/' . $event->id . '/export/sales')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/events/' . $event->id . '/export/members')->assertStatus(200);
    }
}
