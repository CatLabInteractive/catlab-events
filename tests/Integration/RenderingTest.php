<?php

namespace Tests\Integration;

use App\Enum\GroupMemberRoles;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * Rendered output that the Laravel 12 upgrade rewrote by hand and that no
 * other test looks at: Dutch date formatting (Carbon 3 translatedFormat)
 * and the forms that replaced laravelcollective/html.
 */
class RenderingTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    /** Saturday 3 October 2026, 20:00. */
    private function eventOnKnownDate(): Event
    {
        $event = $this->createEvent($this->createOrganisation());
        $event->eventDates()->delete();
        $event->eventDates()->create([
            'startDate' => Carbon::create(2026, 10, 3, 20, 0, 0),
            'endDate' => Carbon::create(2026, 10, 3, 23, 30, 0),
        ]);
        return $event->fresh();
    }

    private function groupAdministeredBy(User $user, string $name): Group
    {
        $group = new Group();
        $group->name = $name;
        $group->save();

        $member = new GroupMember();
        $member->group()->associate($group);
        $member->user()->associate($user);
        $member->role = GroupMemberRoles::ADMIN;
        $member->save();

        return $group;
    }

    // -- dates --------------------------------------------------------------

    public function testEventPageShowsTheDateInDutch()
    {
        $event = $this->eventOnKnownDate();
        $this->createTicketCategory($event, 10.0);

        $response = $this->get('/events/' . $event->id);

        $response->assertStatus(200);
        $response->assertSee('Zaterdag 3 oktober 2026');
        $response->assertDontSee('Saturday');
    }

    public function testTicketSelectionShowsTheMonthInDutch()
    {
        $event = $this->eventOnKnownDate();
        $this->createTicketCategory($event, 10.0);

        $response = $this->actingAs($this->createUser())->get('/events/' . $event->id . '/register');

        $response->assertStatus(200);
        $response->assertSee('oktober 2026');
        $response->assertDontSee('October');
    }

    // -- forms that replaced laravelcollective/html -------------------------

    public function testTeamSelectionFormListsTeamsAndKeepsTheOldChoice()
    {
        $event = $this->createEvent($this->createOrganisation());
        $event->requires_team = true;
        $event->save();
        // Event::countAvailableTickets() sums the dates' counts, and a date
        // without max_tickets contributes null (= 0), which reads as sold out.
        $event->eventDates()->update([ 'max_tickets' => 100 ]);
        $category = $this->createTicketCategory($event, 10.0);

        $user = $this->createUser();
        $first = $this->groupAdministeredBy($user, 'First team');
        $second = $this->groupAdministeredBy($user, 'Second team');

        $response = $this->actingAs($user)
            ->withSession([ '_old_input' => [ 'group' => (string)$second->id ] ])
            ->get("/events/{$event->id}/register/{$category->id}");

        $response->assertStatus(200);
        $response->assertSee('name="_token"', false);
        $response->assertSee('<option value="' . $first->id . '" >First team', false);
        $response->assertSee('<option value="' . $second->id . '" selected>Second team', false);
    }

    public function testMergeFormAndConfirmationCarryTheOtherTeam()
    {
        $this->createOrganisation();
        $user = $this->createUser();
        $group = $this->groupAdministeredBy($user, 'Alpha');
        $other = $this->groupAdministeredBy($user, 'Beta');

        $response = $this->actingAs($user)->get("/groups/{$group->id}/merge");
        $response->assertStatus(200);
        $response->assertSee('action="' . action('GroupController@mergeGroup', $group->id) . '"', false);
        $response->assertSee('<option value="' . $other->id . '" >Beta', false);

        $response = $this->actingAs($user)->post("/groups/{$group->id}/merge", [ 'id' => $other->id ]);
        $response->assertStatus(200);
        $response->assertSee('action="' . action('GroupController@processMergeGroup', $group->id) . '"', false);
        $response->assertSee('<input type="hidden" name="id" value="' . $other->id . '">', false);
        $response->assertSee('name="_token"', false);
    }

    public function testFailedPaymentOffersARetryFormWithTheOriginalInput()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);
        $user = $this->createUser();

        $this->actingAs($user)->post("/events/{$event->id}/register/{$category->id}/process");
        $order = Order::query()->firstOrFail();

        $this->catlabApi->orderStatus = 'CANCELLED';
        $response = $this->actingAs($user)->get("/orders/{$order->id}/thanks");

        $response->assertStatus(200);
        $this->assertEquals(Order::STATE_CANCELLED, $order->fresh()->state);
        $response->assertSee('action="' . action('EventController@processRegister', [ $event->id, $category->id ]) . '"', false);
        $response->assertSee('<input type="hidden" name="group" value="">', false);
        $response->assertSee('Probeer het opnieuw');
    }
}
