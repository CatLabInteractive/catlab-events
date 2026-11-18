<?php

use App\Models\Event;
use App\Models\Organisation;
use App\Models\Person;
use App\Models\Series;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use CatLab\CentralStorage\Client\Models\Asset;
use Illuminate\Database\Seeder;

/**
 * Demo content for the static PR previews (.github/workflows/preview.yml).
 *
 * One organisation with two series, a quiz package and a handful of events,
 * each with a differently shaped poster so the layouts get exercised.
 * The poster files live in .github/preview/assets and are served by
 * .github/preview/assetserver.py, which stands in for CentralStorage.
 *
 * Dates are relative to today, so the events never drop off the calendar.
 *
 *   php artisan db:seed --class=PreviewSeeder
 */
class PreviewSeeder extends Seeder
{
    public function run()
    {
        $organisation = new Organisation();
        $organisation->name = 'De Quizfabriek';
        $organisation->logo_id = $this->asset('logo.png', 160, 180)->id;
        $organisation->save();

        $user = new User();
        $user->name = 'Demo';
        $user->email = 'demo@example.com';
        $user->password = bcrypt(\Illuminate\Support\Str::random(32));
        $user->save();

        $series = new Series();
        $series->organisation_id = $organisation->id;
        $series->name = 'Woonkamerquiz';
        $series->slug = 'woonkamerquiz';
        $series->active = 1;
        $series->teaser = '<p>De gezelligste quiz van het land, gewoon in je eigen woonkamer.</p>';
        $series->header_asset_id = $this->asset('header.jpg', 1280, 768)->id;
        $series->save();

        // Every dated event belongs to a series: the event tables link the
        // date through it.
        $quizavonden = new Series();
        $quizavonden->organisation_id = $organisation->id;
        $quizavonden->name = 'Quizavonden';
        $quizavonden->slug = 'quizavonden';
        $quizavonden->active = 1;
        $quizavonden->teaser = '<p>Met je ploeg aan tafel, op locatie.</p>';
        $quizavonden->save();

        $venue = new Venue();
        $venue->user()->associate($user);
        $venue->name = 'Zaal De Kring';
        $venue->address = 'Kerkstraat 1';
        $venue->postalCode = '9000';
        $venue->city = 'Gent';
        $venue->country = 'BE';
        $venue->save();

        $package = $this->event($organisation, 'Woonkamerquiz', 'package', $this->asset('wide.jpg', 1600, 900), null, null, $series);
        $this->ticketCategory($package, 'Quizpakket', 6);
        $this->ticketCategory($package, 'Quizpakket + bedrijfslicentie', 25);

        $people = [];
        foreach ([['Wietse', 'Heremans'], ['Kim', 'Masselin'], ['Mathias', 'Mathieu'], ['Tim', 'Penninck'], ['Thijs', 'Van der Schaeghe']] as [$first, $last]) {
            $person = new Person();
            $person->first_name = $first;
            $person->last_name = $last;
            $person->organisation_id = $organisation->id;
            $person->user_id = $user->id;
            $person->save();
            $people[] = $person;
        }
        $package->people()->attach($people[0]->id, ['role' => Person::ROLE_PRODUCER]);
        foreach ([0, 2, 3] as $i) {
            $package->people()->attach($people[$i]->id, ['role' => Person::ROLE_AUTHOR]);
        }
        foreach ([0, 1] as $i) {
            $package->people()->attach($people[$i]->id, ['role' => Person::ROLE_PRESENTER]);
        }
        foreach ([4, 0, 2] as $i) {
            $package->people()->attach($people[$i]->id, ['role' => Person::ROLE_TECHNICIAN]);
        }

        $first = Carbon::today()->addWeeks(4)->setTime(20, 0);

        $e = $this->event($organisation, 'Grote Kerstquiz', 'event', $this->asset('portrait.jpg', 1000, 1414), $venue, 6, $series, $first);
        $this->ticketCategory($e, 'Ploegticket', 30);

        $e = $this->event($organisation, 'Pubquiz', 'event', $this->asset('square.jpg', 1200, 1200), $venue, 4, $quizavonden, $first->copy()->addWeeks(2));
        $this->ticketCategory($e, 'Ploegticket', 20);

        $e = $this->event($organisation, 'Zomerquiz', 'event', $this->asset('panorama.jpg', 2400, 700), $venue, 5, $quizavonden, $first->copy()->addWeeks(5));
        $this->ticketCategory($e, 'Ploegticket', 25);

        $e = $this->event($organisation, 'Quiz zonder poster', 'event', null, $venue, 5, $quizavonden, $first->copy()->addWeeks(7));
        $this->ticketCategory($e, 'Ploegticket', 25);
    }

    private function asset($key, $width, $height)
    {
        $asset = new Asset();
        $asset->name = $key;
        $asset->asset_key = $key;
        $asset->mimetype = str_ends_with($key, '.png') ? 'image/png' : 'image/jpeg';
        $asset->type = 'image';
        $asset->size = 1000;
        $asset->width = $width;
        $asset->height = $height;
        $asset->save();
        return $asset;
    }

    private function event($organisation, $name, $type, $poster, $venue = null, $teamSize = null, $series = null, Carbon $start = null)
    {
        $event = new Event();
        $event->organisation()->associate($organisation);
        $event->name = $name;
        $event->description = self::DESCRIPTION;
        $event->event_type = $type;
        $event->is_published = true;
        $event->requires_team = $type !== 'package';
        $event->visbility = 'public';
        $event->registration = 'open';
        $event->poster_id = $poster?->id;
        $event->team_size = $teamSize;
        if ($venue) {
            $event->venue_id = $venue->id;
        }
        if ($series) {
            $event->series_id = $series->id;
        }
        $event->save();

        if ($type !== 'package') {
            $start = $start ?? Carbon::today()->addWeeks(4)->setTime(20, 0);
            $event->eventDates()->create([
                'startDate' => $start,
                'endDate' => $start->copy()->addMinutes(210),
            ]);
        }
        return $event;
    }

    private function ticketCategory($event, $name, $price)
    {
        $category = new TicketCategory();
        $category->event()->associate($event);
        $category->name = $name;
        $category->price = $price;
        $category->save();
    }

    const DESCRIPTION = '<h4>Quizpakket</h4>'
        . '<p>📺 Het Quizfabriek quizpakket is terug van nooit echt weggeweest! Quizzen waar je maar wil, wanneer je maar wil, met wie je maar wil.</p>'
        . '<p>🛋️ Je komt terecht in onze gezellige woonkamer waar Kim en Wietse je entertainen met gevarieerde vragen en plezierige breinbrekers, uiteraard met de nodige kwinkslag!</p>'
        . '<p>🙋 De vragen in deze quiz zijn bewust toegankelijk gehouden, met een grote focus op sfeer en plezier. Zo is de quiz geschikt voor werkelijk iedereen: van jong tot oud of van ervaren quizzer tot nieuweling!</p>'
        . '<p>📲 Je start de quiz op via een laptop of computer (die je op je tv kan aansluiten) en beantwoordt de vragen met je eigen smartphone, simpelweg door de QR-code te scannen. En zo ben je meteen vertrokken voor een uurtje quizplezier!</p>'
        . '<p><strong>Goed om te weten</strong></p><ul>'
        . '<li><strong>Speel wanneer het jou past:</strong> na je aankoop krijg je een link die je zelf activeert op het moment dat je de quiz wil spelen.</li>'
        . '<li><strong>Pauzeren mag:</strong> speel de quiz op je eigen tempo.</li>'
        . '<li><strong>Alleen of met een groep:</strong> speel solo of in ploegjes, met maximaal twintig smartphones.</li>'
        . '<li><strong>Voor elk wat wils:</strong> van klassieke quizonderwerpen tot onverwachte thema\'s.</li>'
        . '<li><strong>Het hele jaar door:</strong> winters en gezellig, maar ook perfect te spelen buiten de feestdagen.</li>'
        . '</ul>';
}
