<?php

namespace Tests\Integration;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\User;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * Security audit 2026-08-27 (catlab-events section):
 *  - orders/{id} was readable by any logged-in user (team, items, and the
 *    livestream "start game" link), fetched with the OWNER's stored
 *    accounts token;
 *  - orders/{id}/thanks (same link) was public;
 *  - orders/{id}/sync (accounts' notify callback, so session-less by
 *    design) could be triggered by anyone for any order -- transitions and
 *    confirmation e-mails on demand;
 *  - Order::synchronize() compared a non-existent `status` attribute, so a
 *    locally cancelled order could be flipped back to pending.
 */
class OrderAccessTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    /** @return array [Order, User $buyer, Organisation] */
    private function pendingOrder(): array
    {
        $organisation = $this->createOrganisation();
        $event = $this->createEvent($organisation);
        $category = $this->createTicketCategory($event, 15.0);
        $buyer = $this->createUser();

        $this->actingAs($buyer)->post("/events/{$event->id}/register/{$category->id}/process");
        $order = Order::query()->orderBy('id', 'desc')->firstOrFail();

        $this->app['auth']->guard()->logout();
        $this->flushSession();

        return [ $order, $buyer, $organisation ];
    }

    /** Puts the order in a group of which $member is a member (the test event does not require a team). */
    private function addToGroup(Order $order, User $member): void
    {
        $group = new Group();
        $group->name = 'Test group';
        $group->save();

        $groupMember = new GroupMember();
        $groupMember->group()->associate($group);
        $groupMember->user()->associate($member);
        $groupMember->save();

        $order->group()->associate($group);
        $order->save();
    }

    private function admin(): User
    {
        $admin = $this->createUser();
        $admin->admin = true;
        $admin->save();
        return $admin;
    }

    // -- orders/{id} --------------------------------------------------------

    public function testStrangerCannotViewAnotherUsersOrder()
    {
        list ($order) = $this->pendingOrder();
        $stranger = $this->createUser();

        $this->actingAs($stranger)->get("/orders/{$order->id}")->assertStatus(403);
    }

    public function testBuyerAndAdminCanViewTheOrder()
    {
        list ($order, $buyer) = $this->pendingOrder();

        $this->actingAs($buyer)->get("/orders/{$order->id}")->assertStatus(200);
        $this->actingAs($this->admin())->get("/orders/{$order->id}")->assertStatus(200);
    }

    /**
     * Access goes through OrderPolicy::view, which the backoffice API shares:
     * an administrator of the organisation that runs the event may open the
     * order too, and so may any member of the order's group.
     */
    public function testOrganisationAdminAndGroupMemberCanViewTheOrder()
    {
        list ($order, $buyer, $organisation) = $this->pendingOrder();

        $organisationAdmin = $this->createUser();
        $organisation->users()->attach($organisationAdmin->id, [ 'role' => 10 ]);
        $this->actingAs($organisationAdmin)->get("/orders/{$order->id}")->assertStatus(200);

        $teamMate = $this->createUser();
        $this->addToGroup($order, $teamMate);
        $this->actingAs($teamMate)->get("/orders/{$order->id}")->assertStatus(200);

        // Administering an unrelated organisation grants nothing.
        $otherOrganisationAdmin = $this->createUser();
        $this->createOrganisation()->users()->attach($otherOrganisationAdmin->id, [ 'role' => 10 ]);
        $this->actingAs($otherOrganisationAdmin)->get("/orders/{$order->id}")->assertStatus(403);
    }

    // -- orders/{id}/thanks -------------------------------------------------

    /**
     * The thanks page is the payment RETURN URL. That URL is often opened
     * on another device (a QR code scanned to pay on a phone), so it cannot
     * require the buyer's session: it carries a per-order signature
     * instead, generated with the pay URL. A logged-in owner/group
     * member/admin may open it without the signature.
     */
    public function testThanksPageNeedsTheSignedReturnUrlOrOwnership()
    {
        list ($order, $buyer) = $this->pendingOrder();

        // Bare id: enumerable, refused for anonymous and stranger alike.
        $this->get("/orders/{$order->id}/thanks")->assertStatus(403);
        $stranger = $this->createUser();
        $this->actingAs($stranger)->get("/orders/{$order->id}/thanks")->assertStatus(403);

        // The return URL embedded in the pay URL works without any session.
        $this->app['auth']->guard()->logout();
        $this->flushSession();
        parse_str((string)parse_url($order->getPayUrl(), PHP_URL_QUERY), $payQuery);
        $returnUrl = $payQuery['return'];
        $this->assertStringContainsString("/orders/{$order->id}/thanks", $returnUrl);
        $this->get(parse_url($returnUrl, PHP_URL_PATH) . '?' . parse_url($returnUrl, PHP_URL_QUERY))->assertStatus(200);

        // A signature for another order does not open this one.
        list ($other) = $this->pendingOrder();
        $this->get("/orders/{$order->id}/thanks?sig=" . $other->thanksSignature())->assertStatus(403);

        // The buyer needs no signature.
        $this->actingAs($buyer)->get("/orders/{$order->id}/thanks")->assertStatus(200);
    }

    // -- orders/{id}/sync ---------------------------------------------------

    public function testUnsignedSyncIsRefusedAndChangesNothing()
    {
        list ($order) = $this->pendingOrder();
        $this->catlabApi->orderStatus = 'ACCEPTED';

        $this->get("/orders/{$order->id}/sync")->assertStatus(403);
        $this->get("/orders/{$order->id}/sync?sig=deadbeef")->assertStatus(403);

        $this->assertEquals(Order::STATE_PENDING, $order->fresh()->state, 'an unsigned call must not transition the order');
        $this->assertCount(0, $this->catlabApi->sendEmailCalls);
    }

    public function testTheCallbackHandedToAccountsIsSignedAndWorksWithoutASession()
    {
        list ($order) = $this->pendingOrder();
        $callback = $this->catlabApi->createOrderCalls[0]['callback'];

        $this->assertStringContainsString("/orders/{$order->id}/sync", $callback);
        $this->assertStringContainsString('sig=', $callback);

        $this->catlabApi->orderStatus = 'ACCEPTED';
        $this->get(parse_url($callback, PHP_URL_PATH) . '?' . parse_url($callback, PHP_URL_QUERY))->assertStatus(200);

        $this->assertEquals(Order::STATE_ACCEPTED, $order->fresh()->state);
    }

    public function testSignatureIsPerOrder()
    {
        list ($first) = $this->pendingOrder();
        list ($second) = $this->pendingOrder();
        $this->assertNotEquals($first->id, $second->id);

        // The second order's id with the first order's signature: refused,
        // and the second order stays pending.
        $this->catlabApi->orderStatus = 'ACCEPTED';
        $this->get('/orders/' . $second->id . '/sync?sig=' . $first->syncSignature())->assertStatus(403);
        $this->assertEquals(Order::STATE_PENDING, $second->fresh()->state);
    }

    // -- Order::synchronize() -----------------------------------------------

    public function testACancelledOrderIsNotFlippedBackToPendingBySync()
    {
        list ($order) = $this->pendingOrder();
        $order->state = Order::STATE_CANCELLED;
        $order->save();

        $this->catlabApi->orderStatus = 'PENDING';
        $this->get('/orders/' . $order->id . '/sync?sig=' . $order->syncSignature())->assertStatus(200);

        $this->assertEquals(Order::STATE_CANCELLED, $order->fresh()->state, 'synchronize() must compare the real state column');
    }

    public function testAnUnchangedStateDoesNotRetrigger()
    {
        list ($order) = $this->pendingOrder();

        $this->catlabApi->orderStatus = 'ACCEPTED';
        $this->get('/orders/' . $order->id . '/sync?sig=' . $order->syncSignature())->assertStatus(200);
        $this->assertCount(1, $this->catlabApi->sendEmailCalls, 'first acceptance sends the confirmation');

        $this->get('/orders/' . $order->id . '/sync?sig=' . $order->syncSignature())->assertStatus(200);
        $this->assertCount(1, $this->catlabApi->sendEmailCalls, 'a repeated sync with the same state must not re-send it');
    }
}
