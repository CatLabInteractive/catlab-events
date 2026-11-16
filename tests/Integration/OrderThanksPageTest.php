<?php

namespace Tests\Integration;

use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * The thanks page after a purchase: it tells the buyer where the mail went,
 * shows the play link of a quiz packet right away, and offers the order's
 * dates as an .ics file.
 */
class OrderThanksPageTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    /** @return array [Order, User] */
    private function acceptedOrder(?callable $configureEvent = null): array
    {
        $event = $this->createEvent($this->createOrganisation());
        if ($configureEvent) {
            $configureEvent($event);
            $event->save();
        }

        $category = $this->createTicketCategory($event, 0.0);
        $category->eventDates()->sync($event->eventDates->pluck('id'));
        $user = $this->createUser();

        $this->actingAs($user)->post("/events/{$event->id}/register/{$category->id}/process");
        $order = Order::query()->orderBy('id', 'desc')->firstOrFail();
        $this->assertEquals(Order::STATE_ACCEPTED, $order->state);

        return [ $order, $user ];
    }

    public function testAcceptedTicketShowsTicketMailAddressAndCalendarLink()
    {
        list ($order, $user) = $this->acceptedOrder();

        $this->actingAs($user)->get("/orders/{$order->id}/thanks")
            ->assertStatus(200)
            ->assertSee('order-ticket', false)
            ->assertSee('#' . $order->id)
            ->assertSee($user->email)
            ->assertSee('Toevoegen aan agenda')
            ->assertSee("/orders/{$order->id}/calendar.ics?sig=" . $order->thanksSignature(), false)
            ->assertDontSee('Start de quiz');
    }

    public function testPacketShowsPlayLinkImmediately()
    {
        list ($order, $user) = $this->acceptedOrder(function (Event $event) {
            $event->event_type = Event::TYPE_PACKAGE;
            // Packet that is playable already.
            $event->eventDates()->update([ 'startDate' => now()->subDay() ]);
        });

        $order->play_link = 'https://quizwitz.example/play/abc?lang=nl';
        $order->save();

        $this->actingAs($user)->get("/orders/{$order->id}/thanks")
            ->assertStatus(200)
            ->assertSee('Start de quiz')
            ->assertSee('https://quizwitz.example/play/abc?lang=nl', false)
            ->assertSee('72 uur')
            ->assertSee($user->email);
    }

    public function testPacketThatOpensLaterHidesPlayLinkAndNamesTheDate()
    {
        list ($order, $user) = $this->acceptedOrder(function (Event $event) {
            $event->event_type = Event::TYPE_PACKAGE;
        });

        $order->play_link = 'https://quizwitz.example/play/abc?lang=nl';
        $order->save();

        $this->actingAs($user)->get("/orders/{$order->id}/thanks")
            ->assertStatus(200)
            ->assertDontSee('https://quizwitz.example/play/abc', false)
            ->assertSee('Speelbaar vanaf');
    }

    public function testCalendarNeedsSignatureOrOwnership()
    {
        list ($order, $user) = $this->acceptedOrder();

        $this->app['auth']->guard()->logout();
        $this->flushSession();

        $this->get("/orders/{$order->id}/calendar.ics")->assertStatus(403);
        $this->actingAs($this->createUser())->get("/orders/{$order->id}/calendar.ics")->assertStatus(403);

        $this->app['auth']->guard()->logout();
        $this->flushSession();

        $response = $this->get("/orders/{$order->id}/calendar.ics?sig=" . $order->thanksSignature());
        $response->assertStatus(200);
        $this->assertStringStartsWith('text/calendar', $response->headers->get('Content-Type'));

        $body = $response->getContent();
        $this->assertStringContainsString("BEGIN:VCALENDAR\r\n", $body);
        $this->assertStringContainsString('SUMMARY:Test quiz night', $body);
        $this->assertStringContainsString('BEGIN:VEVENT', $body);
        $this->assertMatchesRegularExpression('/DTSTART:\d{8}T\d{6}Z/', $body);

        foreach (explode("\r\n", $body) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), 'iCalendar lines must be folded at 75 octets');
        }

        $this->actingAs($user)->get("/orders/{$order->id}/calendar.ics")->assertStatus(200);
    }
}
