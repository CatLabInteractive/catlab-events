<?php

namespace Tests\Integration;

use App\Models\Group;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Auth\Events\Login;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * Guest checkout (spec 2026-11-17-guest-checkout-design.md): the register
 * page is public, a guest types an email address and goes straight to the
 * pay page. The order is filed under the accounts user that owns the
 * address, and nobody is ever logged in by typing an address.
 */
class GuestCheckoutTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    private function guestPost($event, $category, array $extra = [])
    {
        return $this->post("/events/{$event->id}/register", array_merge([
            'category' => $category->id,
            'email' => 'guest@example.test',
        ], $extra));
    }

    private function teamEvent()
    {
        $event = $this->createEvent($this->createOrganisation());
        $event->requires_team = true;
        $event->save();
        return $event;
    }

    // -- the form ---------------------------------------------------------

    public function testRegisterPageIsPublicAndAsksForAnEmailAddress()
    {
        $event = $this->createEvent($this->createOrganisation());
        $this->createTicketCategory($event, 15.0);

        $response = $this->get("/events/{$event->id}/register");

        $response->assertStatus(200);
        $response->assertSee('name="email"', false);
        $response->assertDontSee('name="email_confirmation"', false);
        $response->assertSee('action="' . action('EventController@storeRegistration', [ $event->id ]) . '"', false);
        $response->assertSee('Betalen');
        $response->assertSee(action('EventController@authSelectTicketCategory', [ $event->id ]), false);
    }

    public function testTicketsThatCannotBeBoughtAreNotListed()
    {
        $event = $this->createEvent($this->createOrganisation());
        $open = $this->createTicketCategory($event, 15.0);
        $open->name = 'Open ticket';
        $open->save();

        $closed = $this->createTicketCategory($event, 10.0);
        $closed->name = 'Closed ticket';
        $closed->end_date = new \DateTime('-1 day');
        $closed->save();

        $response = $this->get("/events/{$event->id}/register");
        $response->assertStatus(200);
        $response->assertSee('Open ticket');
        $response->assertDontSee('Closed ticket');
    }

    public function testLoggedInUserSeesNoEmailField()
    {
        $event = $this->createEvent($this->createOrganisation());
        $this->createTicketCategory($event, 15.0);
        $user = $this->createUser();

        $response = $this->actingAs($user)->get("/events/{$event->id}/register");
        $response->assertStatus(200);
        $response->assertDontSee('name="email"', false);
        $response->assertSee($user->email);
    }

    // -- paid guest checkout ----------------------------------------------

    public function testGuestGoesStraightToThePayPageWithoutBeingLoggedIn()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);
        $this->createUser(); // not the first user, so boot() makes no admin anyway

        $response = $this->guestPost($event, $category);

        $response->assertStatus(302);
        $this->assertStringStartsWith('https://pay.example.com/order/' . $this->catlabApi->nextOrderId . '?return=', $response->headers->get('Location'));
        $this->assertGuest();

        $this->assertSame([['email' => 'guest@example.test', 'language' => 'nl']], $this->catlabApi->findOrCreateUserCalls);
        $buyer = User::query()->where('email', '=', 'guest@example.test')->firstOrFail();
        $this->assertSame(90000, (int)$buyer->catlab_id);

        $order = Order::query()->firstOrFail();
        $this->assertSame($buyer->id, (int)$order->user_id);
        $this->assertTrue((bool)$order->is_guest_checkout);
        $this->assertEquals(Order::STATE_PENDING, $order->state);

        $this->assertCount(1, $this->catlabApi->createOrderCalls);
        $this->assertTrue($this->catlabApi->createOrderCalls[0]['guest']);
        $this->assertSame($buyer->id, $this->catlabApi->currentUser->id, 'the order is created at accounts for the buyer');
    }

    public function testLoggedInCheckoutIsNotFlaggedAsGuest()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);
        $user = $this->createUser();

        $response = $this->actingAs($user)->post("/events/{$event->id}/register", [ 'category' => $category->id ]);
        $this->assertStringStartsWith('https://pay.example.com/order/', $response->headers->get('Location'));

        $order = Order::query()->firstOrFail();
        $this->assertSame($user->id, (int)$order->user_id);
        $this->assertFalse((bool)$order->is_guest_checkout);
        $this->assertArrayNotHasKey('guest', $this->catlabApi->createOrderCalls[0]);
        $this->assertSame([], $this->catlabApi->findOrCreateUserCalls);
    }

    public function testExistingLocalUserIsReusedUnchangedAndNotLoggedIn()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);

        $existing = $this->createUser();
        $existing->catlab_id = 777;
        $existing->name = 'Ada';
        $existing->save();
        $this->catlabApi->accountsUsers['guest@example.test'] = 777;

        $this->guestPost($event, $category)->assertStatus(302);

        $this->assertGuest();
        $order = Order::query()->firstOrFail();
        $this->assertSame($existing->id, (int)$order->user_id);
        $this->assertSame('Ada', $existing->fresh()->name);
        $this->assertSame($existing->email, $existing->fresh()->email);
        $this->assertSame(1, User::query()->count());
    }

    public function testStaleLocalRowHoldingTheAddressIsMovedAside()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);

        $stale = $this->createUser();
        $stale->email = 'guest@example.test';
        $stale->catlab_id = 555;
        $stale->save();

        $this->guestPost($event, $category)->assertStatus(302);

        $this->assertSame('stale-' . $stale->id . '@invalid', $stale->fresh()->email);
        $buyer = User::query()->where('email', '=', 'guest@example.test')->firstOrFail();
        $this->assertNotSame($stale->id, $buyer->id);
        $this->assertSame(90000, (int)$buyer->catlab_id);
    }

    public function testLocalAccountWithoutCatlabIdIsLeftAloneAndAskedToLogIn()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);

        $legacy = $this->createUser();
        $legacy->email = 'guest@example.test';
        $legacy->save();

        $this->guestPost($event, $category)->assertSessionHasErrors();

        $this->assertSame('guest@example.test', $legacy->fresh()->email);
        $this->assertSame(0, Order::query()->count());
    }

    public function testWithoutRecaptchaKeysProductionOffersOnlyLogin()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);
        config([ 'services.recaptcha.secret' => null, 'services.recaptcha.site_key' => null ]);

        // The service itself fails closed in production...
        $this->app['env'] = 'production';
        $recaptcha = new \App\Services\Recaptcha();
        $this->assertFalse($recaptcha->allowsGuestCheckout());
        $this->assertFalse($recaptcha->verify('anything'));
        $this->app['env'] = 'testing'; // (CSRF is only skipped in testing)

        // ...and the page and the POST follow it.
        $this->app->instance(\App\Services\Recaptcha::class, new class extends \App\Services\Recaptcha {
            public function allowsGuestCheckout(): bool
            {
                return false;
            }
        });

        $this->get("/events/{$event->id}/register")
            ->assertStatus(200)
            ->assertDontSee('name="email"', false)
            ->assertSee('Inloggen en bestellen');

        $this->guestPost($event, $category)->assertSessionHasErrors();
        $this->assertSame(0, Order::query()->count());
        $this->assertSame([], $this->catlabApi->findOrCreateUserCalls);

        // A logged-in buyer still gets the form.
        $this->actingAs($this->createUser())->get("/events/{$event->id}/register")
            ->assertSee('name="category"', false);
    }

    public function testGuestCheckoutIsNotTrackedBeforePayment()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);

        $this->guestPost($event, $category)->assertRedirect();
        $this->assertSame([], $this->eukles->tracked);
    }

    public function testAccountsOrderFailureShowsAnErrorAndLeavesNoTeam()
    {
        $event = $this->teamEvent();
        $category = $this->createTicketCategory($event, 15.0);
        $this->catlabApi->createOrderException = new \RuntimeException('accounts down');

        $this->guestPost($event, $category, [ 'team' => 'Ghost team' ])->assertSessionHasErrors();

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Group::query()->where('name', '=', 'Ghost team')->count());
    }

    public function testViewingTheEventNoLongerCancelsUnpaidOrdersLocally()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);

        $this->guestPost($event, $category)->assertRedirect();
        $order = Order::query()->firstOrFail();
        $this->ageOrder($order, 45);

        $this->get("/events/{$event->id}")->assertStatus(200);
        $this->assertEquals(Order::STATE_PENDING, $order->fresh()->state, 'only orders:expire-pending cancels, at accounts first');
    }

    public function testFirstEverUserCreatedByACheckoutIsNoAdmin()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);

        $this->guestPost($event, $category)->assertStatus(302);

        $this->assertFalse((bool)User::query()->where('email', '=', 'guest@example.test')->firstOrFail()->admin);
    }

    // -- refusals ---------------------------------------------------------

    public function testGuestCannotTakeAFreeTicket()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 0.0);

        $this->guestPost($event, $category)
            ->assertRedirect(action('EventController@selectTicketCategory', [ $event->id ]))
            ->assertSessionHasErrors();

        $this->assertSame(0, Order::query()->count());
        $this->assertSame([], $this->catlabApi->findOrCreateUserCalls);
    }

    public function testInvalidOrMissingAddressIsRefused()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);

        $this->guestPost($event, $category, [ 'email' => 'nope' ])->assertSessionHasErrors('email');
        $this->guestPost($event, $category, [ 'email' => '' ])->assertSessionHasErrors('email');

        $this->assertSame(0, Order::query()->count());
        $this->assertSame([], $this->catlabApi->findOrCreateUserCalls);
    }

    public function testTeamEventNeedsATeamName()
    {
        $event = $this->teamEvent();
        $category = $this->createTicketCategory($event, 15.0);

        $this->guestPost($event, $category)->assertSessionHasErrors('team');
        $this->assertSame(0, Order::query()->count());
    }

    public function testAccountsFailureShowsAnErrorAndCreatesNothing()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);
        $this->catlabApi->findOrCreateUserException = new \RuntimeException('accounts down');

        $this->guestPost($event, $category)->assertSessionHasErrors();

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, User::query()->count());
    }

    public function testPendingGuestOrdersPerAddressAreCapped()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);

        $this->guestPost($event, $category)->assertRedirect();
        $this->guestPost($event, $category)->assertRedirect();
        $this->guestPost($event, $category)->assertSessionHasErrors();

        $this->assertSame(2, Order::query()->count());
        $this->assertSame(2, Order::query()->where('state', '=', Order::STATE_PENDING)->count(), 'earlier orders are not cancelled');
    }

    public function testGuestOrdersDoNotBlockTheOwnersOwnCheckout()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);

        $this->guestPost($event, $category)->assertRedirect();
        $owner = User::query()->where('email', '=', 'guest@example.test')->firstOrFail();

        $this->actingAs($owner)->get("/events/{$event->id}/register")
            ->assertStatus(200)
            ->assertSee('name="category"', false);
    }

    // -- teams, mails and claiming ----------------------------------------

    public function testGuestTeamHasNoMembersAndTheConfirmationMailCarriesTheClaimLink()
    {
        $event = $this->teamEvent();
        $category = $this->createTicketCategory($event, 15.0);
        $this->createUser();

        $this->guestPost($event, $category, [ 'team' => 'De Slimmeriken' ])->assertRedirect();

        $order = Order::query()->firstOrFail();
        $group = Group::query()->findOrFail($order->group_id);
        $this->assertSame('De Slimmeriken', $group->name);
        $this->assertFalse($group->hasMembers());

        $this->catlabApi->orderStatus = 'ACCEPTED';
        $this->get("/orders/{$order->id}/sync?sig=" . $order->syncSignature())->assertStatus(200);

        $this->assertCount(1, $this->catlabApi->sendEmailCalls);
        $mail = $this->catlabApi->sendEmailCalls[0];
        $this->assertSame('guest@example.test', $mail['target']);
        $this->assertStringContainsString(action('OrderController@claim', [ $order->id ]), $mail['body']);
    }

    private function activeTeam(string $name, ?User $admin = null): Group
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);

        $team = new Group();
        $team->name = $name;
        $team->save();
        if ($admin) {
            $team->addAdmin($admin);
        }

        $order = $category->createOrder($team, null, $admin ?: $this->createUser());
        $order->state = Order::STATE_ACCEPTED;
        $order->save();

        return $team;
    }

    public function testGuestCannotTakeTheNameOfAnActiveTeam()
    {
        $this->activeTeam('De Kwisbrains');
        $event = $this->teamEvent();
        $category = $this->createTicketCategory($event, 15.0);
        $before = Order::query()->count();

        $response = $this->guestPost($event, $category, [ 'team' => 'De Kwisbrains' ]);
        $response->assertSessionHasErrors();
        $this->assertStringContainsString('Log in', implode(' ', session('errors')->all()));

        $this->assertSame($before, Order::query()->count());
        $this->assertSame(1, Group::query()->where('name', '=', 'De Kwisbrains')->count());
        $this->assertSame([], $this->catlabApi->findOrCreateUserCalls);
    }

    public function testRetryAfterAFailedGuestPaymentKeepsTheTeamName()
    {
        $event = $this->teamEvent();
        $category = $this->createTicketCategory($event, 15.0);

        $this->guestPost($event, $category, [ 'team' => 'Tweede Kans' ])->assertRedirect();
        $first = Order::query()->firstOrFail();
        $first->changeState(Order::STATE_CANCELLED);

        $this->catlabApi->nextOrderId++;
        $this->guestPost($event, $category, [ 'team' => 'Tweede Kans' ])->assertSessionHasNoErrors();
        $this->assertSame(2, Order::query()->count());
    }

    public function testLoggedInUserTypingTheirOwnTeamNameRegistersThatTeam()
    {
        $user = $this->createUser();
        $team = $this->activeTeam('Eigen Team', $user);
        $event = $this->teamEvent();
        $category = $this->createTicketCategory($event, 15.0);

        $this->actingAs($user)->post("/events/{$event->id}/register", [ 'category' => $category->id, 'team' => 'Eigen Team' ])->assertRedirect();

        $this->assertSame(1, Group::query()->where('name', '=', 'Eigen Team')->count());
        $this->assertSame($team->id, (int)Order::query()->where('event_id', '=', $event->id)->firstOrFail()->group_id);
    }

    public function testLoggedInUserCannotTakeSomeoneElsesActiveTeamName()
    {
        $this->activeTeam('Andermans Team', $this->createUser());
        $event = $this->teamEvent();
        $category = $this->createTicketCategory($event, 15.0);

        $this->actingAs($this->createUser())
            ->post("/events/{$event->id}/register", [ 'category' => $category->id, 'team' => 'Andermans Team' ])
            ->assertSessionHasErrors();
        $this->assertSame(0, Order::query()->where('event_id', '=', $event->id)->count());
    }

    public function testReturningTeamsAreNudgedToLogIn()
    {
        $event = $this->teamEvent();
        $this->createTicketCategory($event, 15.0);
        $this->get("/events/{$event->id}/register")->assertSee('Inloggen en mijn team kiezen');
        $this->actingAs($this->createUser())->get("/events/{$event->id}/register")->assertDontSee('Inloggen en mijn team kiezen');

        $this->app['auth']->guard()->logout();
        $package = $this->createEvent($this->createOrganisation());
        $this->createTicketCategory($package, 15.0);
        $this->get("/events/{$package->id}/register")->assertDontSee('Inloggen en mijn team kiezen');
    }

    public function testLoggedInUserCanPickAnExistingTeamOrTypeANewOne()
    {
        $event = $this->teamEvent();
        $category = $this->createTicketCategory($event, 15.0);
        $user = $this->createUser();
        $team = new Group();
        $team->name = 'Bestaand';
        $team->save();
        $team->addAdmin($user);

        $this->actingAs($user)->post("/events/{$event->id}/register", [ 'category' => $category->id, 'group' => $team->id ])->assertRedirect();
        $this->assertSame($team->id, (int)Order::query()->firstOrFail()->group_id);

        $this->actingAs($user)->post("/events/{$event->id}/register?cancelPendingOrders=1", [ 'category' => $category->id, 'group' => $team->id, 'team' => 'Nieuw' ])->assertRedirect();
        $new = Group::query()->where('name', '=', 'Nieuw')->firstOrFail();
        $this->assertTrue($new->hasMembers());
        $this->assertSame($user->id, (int)$new->members()->first()->user_id);
    }

    public function testClaimMakesTheBuyerTeamAdminAndRefusesAnyoneElse()
    {
        $event = $this->teamEvent();
        $category = $this->createTicketCategory($event, 15.0);
        $this->createUser();

        $this->guestPost($event, $category, [ 'team' => 'Claimers' ])->assertRedirect();
        $order = Order::query()->firstOrFail();
        $buyer = $order->user;

        // Unpaid: anyone can create one under any address, so not claimable.
        $this->actingAs($buyer)->get("/orders/{$order->id}/claim")->assertStatus(404);
        $this->assertFalse($order->group->hasMembers());

        $order->state = Order::STATE_ACCEPTED;
        $order->save();

        $this->actingAs($this->createUser())->get("/orders/{$order->id}/claim")->assertStatus(403);
        $this->assertFalse($order->group->hasMembers());

        $this->actingAs($buyer)->get("/orders/{$order->id}/claim")
            ->assertRedirect(action('GroupController@show', [ $order->group_id ]));
        $this->assertSame($buyer->id, (int)$order->group->members()->first()->user_id);
    }

    public function testLoginAttachesMemberlessTeamsOfPaidGuestOrders()
    {
        $event = $this->teamEvent();
        $category = $this->createTicketCategory($event, 15.0);
        $this->createUser();

        $this->guestPost($event, $category, [ 'team' => 'Paid team' ])->assertRedirect();
        $this->catlabApi->nextOrderId++;
        $this->guestPost($event, $category, [ 'team' => 'Unpaid team' ])->assertRedirect();

        $paid = Order::query()->orderBy('id')->firstOrFail();
        $paid->state = Order::STATE_ACCEPTED;
        $paid->save();

        event(new Login('web', $paid->user, false));

        $this->assertTrue(Group::query()->where('name', '=', 'Paid team')->firstOrFail()->hasMembers());
        $this->assertFalse(Group::query()->where('name', '=', 'Unpaid team')->firstOrFail()->hasMembers());
    }

    public function testThanksPageMasksTheGuestAddressForStrangers()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);

        $this->guestPost($event, $category)->assertRedirect();
        $order = Order::query()->firstOrFail();

        $response = $this->get($order->getThanksUrl());
        $response->assertStatus(200);
        $response->assertDontSee('guest@example.test');
        $response->assertSee('g***@example.test');
    }

    // -- orders:expire-pending --------------------------------------------

    private function ageOrder(Order $order, int $minutes)
    {
        Order::query()->whereKey($order->id)->update([ 'created_at' => Carbon::now()->subMinutes($minutes) ]);
    }

    public function testExpiryCancelsOldGuestOrdersAtAccountsAndLocally()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);

        $this->guestPost($event, $category)->assertRedirect();
        $order = Order::query()->firstOrFail();
        $this->ageOrder($order, 31);

        $this->artisan('orders:expire-pending')->assertExitCode(0);

        $this->assertSame([ $order->catlab_order_id ], $this->catlabApi->cancelOrderCalls);
        $this->assertEquals(Order::STATE_CANCELLED, $order->fresh()->state);
    }

    public function testExpiryLeavesRecentAndInProgressOrdersAlone()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);
        $user = $this->createUser();

        // A logged-in order of 45 minutes: within its 60 minute TTL.
        $this->actingAs($user)->post("/events/{$event->id}/register", [ 'category' => $category->id ])->assertRedirect();
        $this->ageOrder(Order::query()->firstOrFail(), 45);
        $this->app['auth']->guard()->logout();

        // A guest order of 10 minutes.
        $this->guestPost($event, $category)->assertRedirect();

        $this->artisan('orders:expire-pending')->assertExitCode(0);
        $this->assertSame([], $this->catlabApi->cancelOrderCalls);
        $this->assertSame(2, Order::query()->where('state', '=', Order::STATE_PENDING)->count());

        // A payment in progress at a gateway: accounts answers 409.
        Order::query()->get()->each(function ($order) { $this->ageOrder($order, 61); });
        $this->catlabApi->cancelOrderException = new ClientException(
            'conflict',
            new PsrRequest('POST', 'orders/1/cancel'),
            new PsrResponse(409)
        );

        $this->artisan('orders:expire-pending')->assertExitCode(0);
        $this->assertSame(2, Order::query()->where('state', '=', Order::STATE_PENDING)->count());
    }

    public function testExpiryAcceptsAnOrderThatWasPaidInTheMeantime()
    {
        $event = $this->createEvent($this->createOrganisation());
        $category = $this->createTicketCategory($event, 15.0);

        $this->guestPost($event, $category)->assertRedirect();
        $order = Order::query()->firstOrFail();
        $this->ageOrder($order, 31);
        $this->catlabApi->orderStatus = 'ACCEPTED';

        $this->artisan('orders:expire-pending')->assertExitCode(0);

        $this->assertSame([], $this->catlabApi->cancelOrderCalls);
        $this->assertEquals(Order::STATE_ACCEPTED, $order->fresh()->state);
    }
}
