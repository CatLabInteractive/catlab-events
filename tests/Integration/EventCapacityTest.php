<?php

namespace Tests\Integration;

use App\Models\Event;
use Carbon\Carbon;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * An event date without max_tickets is unlimited (EventDate::countAvailableTickets()
 * returns null). Event::countAvailableTickets() summed those nulls as 0, so an
 * event with an unlimited date read as sold out and the register page bounced
 * everyone back to the ticket selection.
 */
class EventCapacityTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    /** @param array<int|null> $capacities one entry per event date */
    private function eventWithDates(array $capacities): Event
    {
        $event = $this->createEvent($this->createOrganisation());
        $event->eventDates()->delete();
        foreach ($capacities as $i => $capacity) {
            $event->eventDates()->create([
                'startDate' => Carbon::now()->addDays(30 + $i),
                'endDate' => Carbon::now()->addDays(30 + $i)->addHours(4),
                'max_tickets' => $capacity,
            ]);
        }
        $this->createTicketCategory($event, 10.0);

        return $event->fresh();
    }

    public function testAnUnlimitedDateMeansUnlimitedTickets()
    {
        $event = $this->eventWithDates([ null ]);

        $this->assertNull($event->countAvailableTickets());
        $this->assertFalse($event->isSoldOut(true));
        $this->assertFalse($event->isSoldOut(false));
        $this->assertFalse($event->isLastTicketsWarning());
    }

    public function testOneUnlimitedDateAmongFiniteOnesStillMeansUnlimited()
    {
        $event = $this->eventWithDates([ 10, null ]);

        $this->assertNull($event->countAvailableTickets());
        $this->assertFalse($event->isSoldOut(true));
        $this->assertFalse($event->isLastTicketsWarning());
    }

    public function testFiniteDatesAreSummed()
    {
        $event = $this->eventWithDates([ 10, 5 ]);

        $this->assertSame(15, $event->countAvailableTickets());
        $this->assertFalse($event->isSoldOut(true));
    }

    public function testRegisterPageOpensForAnEventWithAnUnlimitedDate()
    {
        $event = $this->eventWithDates([ null ]);
        $category = $event->ticketCategories()->firstOrFail();

        $this->actingAs($this->createUser())
            ->get("/events/{$event->id}/register/{$category->id}")
            ->assertStatus(200);
    }
}
