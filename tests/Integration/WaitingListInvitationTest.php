<?php

namespace Tests\Integration;

use App\Models\Event;
use App\Models\User;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * Inviting people off the waiting list moved into the admin panel and now
 * actually sends the mail (it used to render a block of text for the admin to
 * copy-paste). The mail goes out through the InvitedFromWaitingList event, so
 * eukles sees the invitation as well.
 */
class WaitingListInvitationTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    /** @var Event */
    private $event;

    /** @var User */
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = $this->createEvent($this->createOrganisation());
        $this->createTicketCategory($this->event, 0.0);

        $this->admin = $this->createUser();
        $this->admin->admin = 1;
        $this->admin->save();
    }

    /**
     * @param int $maxTickets
     */
    private function limitTicketsTo(int $maxTickets)
    {
        $eventDate = $this->event->eventDates()->first();
        $eventDate->max_tickets = $maxTickets;
        $eventDate->save();

        $this->event->refresh();
    }

    /**
     * @return User
     */
    private function addToWaitingList(): User
    {
        $user = $this->createUser();
        $this->event->registerToWaitingList($user);

        // registerToWaitingList fires its own eukles event; the invitation
        // assertions below care only about what happens after this point.
        $this->eukles->tracked = [];

        return $user;
    }

    /**
     * @param User $user
     * @return \stdClass|null
     */
    private function pivotFor(User $user)
    {
        return \DB::table('event_waitinglist')
            ->where('event_id', $this->event->id)
            ->where('user_id', $user->id)
            ->first();
    }

    public function testPreviewShowsTheMailWithoutSendingIt()
    {
        $user = $this->addToWaitingList();

        $response = $this->actingAs($this->admin)
            ->get("/admin/events/{$this->event->id}/waitinglist/invite/{$user->id}");

        $response->assertStatus(200);
        $response->assertSee($user->email);

        $this->assertCount(0, $this->catlabApi->sendEmailCalls);
        $this->assertCount(0, $this->eukles->tracked);
        // Previewing must not hand out a working ticket link.
        $this->assertNull($this->pivotFor($user)->access_token);
    }

    public function testSendingInvitationMailsTheAccessTokenAndTracksIt()
    {
        $user = $this->addToWaitingList();

        $this->actingAs($this->admin)
            ->post("/admin/events/{$this->event->id}/waitinglist/invite/{$user->id}")
            ->assertRedirect("/admin/events/{$this->event->id}/waitinglist");

        $pivot = $this->pivotFor($user);
        $this->assertNotNull($pivot->access_token);
        $this->assertNotNull($pivot->invitation_sent_at);

        $this->assertCount(1, $this->catlabApi->sendEmailCalls);
        $mail = $this->catlabApi->sendEmailCalls[0];
        $this->assertSame($user->email, $mail['target']);
        $this->assertSame('Wachtlijst ' . $this->event->name, $mail['subject']);
        $this->assertStringContainsString('wt=' . $pivot->access_token, $mail['body']);

        $this->assertCount(1, $this->eukles->tracked);
    }

    public function testFailedMailLeavesTheInvitationMarkedUnsent()
    {
        $user = $this->addToWaitingList();

        $this->catlabApi->sendEmailException = new ConnectException(
            'Connection timed out',
            new Request('POST', 'users/1/mail')
        );

        $this->actingAs($this->admin)
            ->post("/admin/events/{$this->event->id}/waitinglist/invite/{$user->id}")
            ->assertRedirect("/admin/events/{$this->event->id}/waitinglist");

        $this->assertNull($this->pivotFor($user)->invitation_sent_at);

        $this->actingAs($this->admin)
            ->get("/admin/events/{$this->event->id}/waitinglist")
            ->assertSee('niet verstuurd');
    }

    public function testMassInviteProposesFreeTicketsMinusOutstandingInvitations()
    {
        $this->limitTicketsTo(5);

        $invited = $this->addToWaitingList();
        $this->actingAs($this->admin)
            ->post("/admin/events/{$this->event->id}/waitinglist/invite/{$invited->id}");

        for ($i = 0; $i < 6; $i ++) {
            $this->addToWaitingList();
        }

        $response = $this->actingAs($this->admin)
            ->get("/admin/events/{$this->event->id}/waitinglist/mass-invite");

        $response->assertStatus(200);
        // 5 free tickets, 1 invitation already out there -> 4 proposed.
        $response->assertSee('value="4"', false);
    }

    public function testMassInviteSendsTheRequestedAmountToTheLongestWaiting()
    {
        $this->limitTicketsTo(10);

        $first = $this->addToWaitingList();
        $second = $this->addToWaitingList();
        $third = $this->addToWaitingList();

        $this->actingAs($this->admin)
            ->post("/admin/events/{$this->event->id}/waitinglist/mass-invite", [ 'amount' => 2 ])
            ->assertRedirect("/admin/events/{$this->event->id}/waitinglist");

        $this->assertCount(2, $this->catlabApi->sendEmailCalls);
        $this->assertNotNull($this->pivotFor($first)->invitation_sent_at);
        $this->assertNotNull($this->pivotFor($second)->invitation_sent_at);
        $this->assertNull($this->pivotFor($third)->invitation_sent_at);
    }

    public function testMassInviteNeverExceedsTheFreeTickets()
    {
        $this->limitTicketsTo(2);

        for ($i = 0; $i < 5; $i ++) {
            $this->addToWaitingList();
        }

        // Stale form asking for more than is free.
        $this->actingAs($this->admin)
            ->post("/admin/events/{$this->event->id}/waitinglist/mass-invite", [ 'amount' => 5 ]);

        $this->assertCount(2, $this->catlabApi->sendEmailCalls);
    }

    public function testPublicWaitingListPageLinksAnAdminToTheAdminPanel()
    {
        // The admin shortcuts on the public page used to action() straight at
        // the controller methods that have since moved into the admin panel.
        $response = $this->actingAs($this->admin)
            ->get("/events/{$this->event->id}/waitinglist");

        $response->assertStatus(200);
        $response->assertSee("/admin/events/{$this->event->id}/waitinglist", false);
        $response->assertSee("/admin/events/{$this->event->id}/waitinglist/mass-invite", false);
    }

    public function testAlreadyRegisteredPeopleAreNotInvited()
    {
        $this->limitTicketsTo(10);

        $registered = $this->addToWaitingList();
        $waiting = $this->addToWaitingList();

        // Buying a free ticket registers them without a team, which the old
        // group-only attendance check used to miss.
        $category = $this->event->ticketCategories()->first();
        $this->actingAs($registered)
            ->post("/events/{$this->event->id}/register/{$category->id}/process");

        $this->catlabApi->sendEmailCalls = [];

        $this->actingAs($this->admin)
            ->post("/admin/events/{$this->event->id}/waitinglist/mass-invite", [ 'amount' => 10 ]);

        $this->assertCount(1, $this->catlabApi->sendEmailCalls);
        $this->assertSame($waiting->email, $this->catlabApi->sendEmailCalls[0]['target']);
        $this->assertNull($this->pivotFor($registered)->access_token);
    }
}
