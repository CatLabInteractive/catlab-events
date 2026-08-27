<?php

namespace Tests\Integration\Concerns;

use App\Models\Event;
use App\Models\Organisation;
use App\Models\TicketCategory;
use App\Models\User;
use Carbon\Carbon;

trait CreatesEventFixtures
{
    protected function createOrganisation(): Organisation
    {
        $organisation = new Organisation();
        $organisation->name = 'Test organisation';
        $organisation->save();

        return $organisation;
    }

    protected function createEvent(Organisation $organisation): Event
    {
        $event = new Event();
        $event->organisation()->associate($organisation);
        $event->name = 'Test quiz night';
        // NOT NULL, no default in the schema; not part of the brief's fixture but required to save.
        $event->description = 'Test event description.';
        // NOT NULL, no default; must be one of Event::TYPE_EVENT / Event::TYPE_PACKAGE.
        $event->event_type = Event::TYPE_EVENT;
        $event->is_published = true;
        // No team requirement, per the fixture's documented contract (column defaults to true).
        $event->requires_team = false;
        $event->visbility = 'public'; // (sic) column name has a typo in the schema
        $event->registration = 'open';
        $event->save();

        // startDate/endDate no longer live on `events` (moved to `event_dates` by
        // migration 2021_08_20_132431_create_event_dates.php); Event::startDate/endDate
        // are now accessors computed from this relation.
        $event->eventDates()->create([
            'startDate' => Carbon::now()->addDays(30),
            'endDate' => Carbon::now()->addDays(30)->addHours(4),
        ]);

        return $event;
    }

    protected function createTicketCategory(Event $event, float $price): TicketCategory
    {
        $category = new TicketCategory();
        $category->event()->associate($event);
        $category->name = $price > 0 ? 'Paid ticket' : 'Free ticket';
        $category->price = $price;
        $category->save();

        return $category;
    }

    protected function createUser(): User
    {
        $user = new User();
        $user->name = 'Test User';
        $user->email = uniqid('test', true) . '@example.com';
        $user->password = bcrypt('secret');
        $user->save();

        return $user;
    }
}
