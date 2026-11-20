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
 * Also the test data of a local copy (docs/local-development.md): the
 * DatabaseSeeder calls it, and the demo user is an admin who can log in
 * with demo@example.com / password (only without CATLAB_CLIENT_ID, which
 * switches the login to CatLab accounts).
 *
 *   php artisan db:seed --class=PreviewSeeder
 */
class PreviewSeeder extends Seeder
{
    const DEMO_EMAIL = 'demo@example.com';

    const DEMO_PASSWORD = 'password';

    public function run()
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('PreviewSeeder writes demo data and a known password: not in production.');
        }

        $organisation = new Organisation();
        $organisation->name = 'De Quizfabriek';
        $organisation->logo_id = $this->asset('logo.png', 160, 180)->id;
        $organisation->save();

        $user = new User();
        $user->name = 'Demo';
        $user->email = self::DEMO_EMAIL;
        $user->password = bcrypt(self::DEMO_PASSWORD);
        $user->admin = 1;
        $user->save();
        $user->organisations()->attach($organisation->id, [ 'role' => Organisation::ROLE_ADMIN ]);

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

        $this->cms($organisation);
    }

    /**
     * CMS content: a page that uses every block type, a plain text page and
     * a few blog posts (one with a featured image). Shown at /quizzen-op-maat,
     * /over-ons and /blog.
     */
    private function cms(Organisation $organisation)
    {
        $wide = $this->asset('wide.jpg', 1600, 900, $organisation);
        $square = $this->asset('square.jpg', 1200, 1200, $organisation);
        $logo = $this->asset('logo.png', 160, 180, $organisation);
        $portrait = $this->asset('portrait.jpg', 1000, 1414, $organisation);
        $panorama = $this->asset('panorama.jpg', 2400, 700, $organisation);
        $header = $this->asset('header.jpg', 1280, 768, $organisation);
        $n = 0;
        $block = function ($type, array $data) use (&$n) {
            return [ 'id' => sprintf('%06x', ++$n), 'type' => $type, 'data' => $data ];
        };

        $posts = app(\App\Cms\PostWriter::class);
        $postBody = '<p class="cms-lead">Een quiz in je eigen woonkamer, met vrienden of familie, wanneer het jou past.</p>'
            . '<p>Voorbeeldtekst met een <a href="/events">link</a>. Deze tekst is demodata voor de preview en geen echte inhoud.</p>'
            . '<h2>Wat zit erin?</h2><ul><li>Eerste punt</li><li>Tweede punt</li><li>Derde punt</li></ul>'
            . '<blockquote><p>Een voorbeeldcitaat in de tekst.</p></blockquote>'
            . '<h3>Hoe begin je?</h3><ol><li>Koop een pakket</li><li>Open de link op je laptop</li><li>Scan de QR-code</li></ol>';
        foreach ([
            [ 'Drie nieuwe quizpakketten', 'drie-nieuwe-quizpakketten', 2, $wide->id ],
            [ 'Terugblik op de Grote Kerstquiz', 'terugblik-grote-kerstquiz', 20, null ],
            [ 'Zo maken we een quizronde', 'zo-maken-we-een-quizronde', 45, $square->id ],
            [ 'Voorbeeldbericht zonder afbeelding', 'voorbeeldbericht', 80, null ],
        ] as [ $title, $slug, $daysAgo, $imageId ]) {
            $posts->createPost($organisation, [
                'published_at' => Carbon::now()->subDays($daysAgo)->setTime(10, 0),
                'featured_image_id' => $imageId,
            ], 'nl', [
                'slug' => $slug, 'title' => $title, 'is_published' => true,
                'excerpt' => 'Een korte inleiding van een paar regels, zodat je weet waar het bericht over gaat.',
                'body' => $postBody,
            ]);
        }

        $pages = app(\App\Cms\PageWriter::class);
        $pages->createPage($organisation, [ 'show_in_menu' => true ], 'nl', [
            'slug' => 'quizzen-op-maat', 'title' => 'Quizzen op maat', 'is_published' => true,
            'blocks' => [
                $block('hero', [
                    'title' => 'Een quiz op maat van jouw feest', 'align' => 'left', 'image_id' => $wide->id,
                    'subtitle' => 'Voor bedrijven, verenigingen en verjaardagen. Wij schrijven de vragen, jij kiest het thema.',
                    'buttons' => [
                        [ 'label' => 'Vraag een offerte', 'url' => 'mailto:hallo@example.test', 'style' => 'primary' ],
                        [ 'label' => 'Bekijk de kalender', 'url' => '/calendar', 'style' => 'secondary' ],
                    ],
                ]),
                $block('cards', [
                    'title' => 'Drie formules', 'intro' => 'Van een korte quiz op je teambuilding tot een avondvullend programma.', 'columns' => 3,
                    'items' => [
                        [ 'title' => 'Op locatie', 'text' => 'Wij komen met presentator, scherm en geluid.', 'icon' => 'map-marker' ],
                        [ 'title' => 'Online', 'text' => 'Live gepresenteerd, iedereen speelt mee van thuis.', 'icon' => 'video-camera' ],
                        [ 'title' => 'Quizpakket', 'text' => 'Zelf spelen wanneer het past, met je smartphone.', 'icon' => 'tv', 'url' => '/events' ],
                    ],
                ]),
                $block('text_image', [
                    'title' => 'Voorbeeld: tekst en beeld', 'image_id' => $square->id, 'image_position' => 'right',
                    'html' => '<p>Voorbeeldtekst naast een afbeelding, met een korte lijst eronder.</p><ul><li>Eerste punt</li><li>Tweede punt</li><li>Derde punt</li></ul>',
                    'button' => [ 'label' => 'Maak kennis', 'url' => '/over-ons' ],
                ]),
                $block('gallery', [
                    'title' => 'Voorbeeld: fotogalerij', 'intro' => 'Klik op een foto om ze groot te bekijken.', 'layout' => 'featured', 'visible' => 6,
                    'items' => array_map(function ($asset, $i) {
                        return [ 'image_id' => $asset->id, 'caption' => $i % 3 === 0 ? 'Voorbeeldonderschrift ' . ($i + 1) : null ];
                    }, $gallery = [ $wide, $portrait, $square, $panorama, $header, $wide, $square, $portrait, $wide, $square ], array_keys($gallery)),
                ]),
                $block('reviews', [
                    'title' => 'Voorbeeld: recensies',
                    'items' => [
                        [ 'quote' => 'Voorbeeldrecensie: een korte quote van een tevreden klant.', 'author' => 'Voorbeeldklant A', 'source' => 'Demo' ],
                        [ 'quote' => 'Voorbeeldrecensie: nog een quote, iets langer, om te zien hoe twee kaarten naast elkaar staan.', 'author' => 'Voorbeeldklant B', 'source' => 'Demo' ],
                    ],
                ]),
                $block('upcoming_events', [ 'title' => 'Volgende quizzen', 'limit' => 4, 'event_type' => 'all' ]),
                $block('cta', [
                    'title' => 'Klaar om te quizzen?', 'text' => 'Stuur ons een mailtje en we maken samen een plan.', 'background' => 'primary',
                    'buttons' => [ [ 'label' => 'Mail ons', 'url' => 'mailto:hallo@example.test', 'style' => 'primary' ] ],
                ]),
                $block('faq', [
                    'title' => 'Veelgestelde vragen',
                    'items' => [
                        [ 'question' => 'Hoeveel spelers kunnen meedoen?', 'answer' => '<p>Voorbeeldantwoord.</p>' ],
                        [ 'question' => 'Hoe lang op voorhand moet ik boeken?', 'answer' => '<p>Voorbeeldantwoord met <strong>vette tekst</strong>.</p>' ],
                        [ 'question' => 'Kunnen we het thema kiezen?', 'answer' => '<p>Voorbeeldantwoord.</p>' ],
                    ],
                ]),
                $block('video', [ 'title' => 'Zo ziet een livestream eruit', 'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'caption' => 'Voorbeeldvideo.' ]),
                $block('logo_grid', [
                    'title' => 'Voorbeeld: logo\'s',
                    'items' => [
                        [ 'name' => 'Voorbeeldpartner 1', 'image_id' => $logo->id ], [ 'name' => 'Voorbeeldpartner 2' ],
                        [ 'name' => 'Voorbeeldpartner 3', 'image_id' => $logo->id ], [ 'name' => 'Voorbeeldpartner 4' ],
                        [ 'name' => 'Voorbeeldpartner 5', 'image_id' => $logo->id ], [ 'name' => 'Voorbeeldpartner 6' ],
                    ],
                ]),
                $block('latest_posts', [ 'title' => 'Uit de blog', 'limit' => 3 ]),
                $block('cta', [
                    'title' => 'Liever bellen?', 'text' => 'Voorbeeldtekst voor een donkere oproep.', 'background' => 'dark',
                    'buttons' => [ [ 'label' => 'Bel ons', 'url' => 'tel:+3291234567', 'style' => 'primary' ], [ 'label' => 'Of mail', 'url' => 'mailto:hallo@example.test', 'style' => 'secondary' ] ],
                ]),
            ],
        ]);

        $pages->createPage($organisation, [ 'show_in_menu' => true ], 'nl', [
            'slug' => 'over-ons', 'title' => 'Over ons', 'is_published' => true,
            'blocks' => [
                $block('rich_text', [ 'html' => $postBody ]),
                $block('cta', [
                    'title' => 'Speel mee', 'text' => 'Bekijk welke quizzen er binnenkort zijn.', 'background' => 'light',
                    'buttons' => [ [ 'label' => 'Naar de kalender', 'url' => '/calendar', 'style' => 'primary' ] ],
                ]),
            ],
        ]);
    }

    private function asset($key, $width, $height, $organisation = null)
    {
        $asset = new Asset();
        if ($organisation) {
            $asset->organisation_id = $organisation->id;
        }
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
