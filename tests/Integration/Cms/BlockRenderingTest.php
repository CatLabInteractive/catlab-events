<?php

namespace Tests\Integration\Cms;

use App\Models\Event;
use CatLab\CentralStorage\Client\Models\Asset;
use Tests\Integration\Concerns\CreatesCmsFixtures;
use Tests\Integration\Concerns\CreatesEventFixtures;
use Tests\Integration\IntegrationTestCase;

class BlockRenderingTest extends IntegrationTestCase
{
    use CreatesEventFixtures;
    use CreatesCmsFixtures;

    private function createAsset(): Asset
    {
        $asset = new Asset();
        $asset->name = 'header.jpg';
        $asset->mimetype = 'image/jpeg';
        $asset->type = 'image';
        $asset->asset_key = 'test-asset-key';
        $asset->save();

        return $asset;
    }

    private function block(string $type, array $data, string $id = null): array
    {
        static $counter = 0;
        $counter++;

        return [ 'id' => $id ?? sprintf('%06x', $counter), 'type' => $type, 'data' => $data ];
    }

    public function testEveryRegisteredBlockTypeRenders()
    {
        $organisation = $this->createOrganisation();
        $asset = $this->createAsset();

        $event = $this->createEvent($organisation);
        $event->name = 'Grote Kerstquiz';
        $event->save();

        $blocks = [
            $this->block('hero', [
                'title' => 'Hero titel', 'subtitle' => 'Hero ondertitel', 'image_id' => $asset->id, 'align' => 'center',
                'buttons' => [ [ 'label' => 'Naar de kalender', 'url' => '/calendar', 'style' => 'primary' ] ],
            ]),
            $this->block('rich_text', [ 'html' => '<p>Rijke <strong>tekst</strong></p>' ]),
            $this->block('text_image', [
                'title' => 'Tekst en beeld', 'html' => '<p>Naast een foto</p>', 'image_id' => $asset->id,
                'image_position' => 'left', 'button' => [ 'label' => 'Meer', 'url' => 'mailto:hallo@example.test' ],
            ]),
            $this->block('cards', [
                'title' => 'Formules', 'intro' => 'Kies maar', 'columns' => 3,
                'items' => [ [ 'title' => 'Kaart een', 'text' => 'Uitleg', 'icon' => 'trophy', 'url' => '/over-ons' ] ],
            ]),
            $this->block('logo_grid', [ 'title' => 'Partners', 'items' => [ [ 'name' => 'Partner NV', 'url' => 'https://partner.test' ] ] ]),
            $this->block('gallery', [ 'title' => 'In beeld', 'items' => [ [ 'image_id' => $asset->id, 'caption' => 'De zaal' ] ] ]),
            $this->block('reviews', [ 'title' => 'Recensies', 'items' => [ [ 'quote' => 'Fantastische avond', 'author' => 'Jan', 'source' => 'Google' ] ] ]),
            $this->block('cta', [
                'title' => 'Boek nu', 'text' => 'Bel ons', 'background' => 'dark',
                'buttons' => [ [ 'label' => 'Bellen', 'url' => 'tel:+3291234567', 'style' => 'secondary' ] ],
            ]),
            $this->block('video', [ 'title' => 'Livestream', 'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'caption' => 'Bekijk' ]),
            $this->block('upcoming_events', [ 'title' => 'Volgende quizzen', 'limit' => 4, 'event_type' => 'all' ]),
            $this->block('latest_posts', [ 'title' => 'Uit de blog', 'limit' => 3 ]),
            $this->block('faq', [ 'title' => 'Vragen', 'items' => [ [ 'question' => 'Hoeveel spelers?', 'answer' => '<p>Maximaal <em>zes</em></p>' ] ] ]),
        ];

        $this->assertCount(count(config('cms.blocks')), array_unique(array_column($blocks, 'type')));

        $this->createPage($organisation, 'alles', $blocks);

        $response = $this->get('/alles');
        $response->assertStatus(200);

        $response->assertSee('<h1 class="kv-cms-hero-title">Hero titel</h1>', false);
        $response->assertSee('Hero ondertitel');
        $response->assertSee('class="kv-cms-hero-image"', false);
        $response->assertSee('test-asset-key', false);
        $response->assertSee('href="/calendar"', false);
        $response->assertSee('<p>Rijke <strong>tekst</strong></p>', false);
        $response->assertSee('Tekst en beeld');
        $response->assertSee('href="mailto:hallo@example.test"', false);
        $response->assertSee('Kaart een');
        $response->assertSee('fa-trophy', false);
        $response->assertSee('Partner NV');
        $response->assertSee('In beeld');
        $response->assertSee('data-kv-caption="De zaal"', false);
        $response->assertSee('Fantastische avond');
        $response->assertSee('kv-cms-cta-dark', false);
        $response->assertSee('href="tel:+3291234567"', false);
        $response->assertSee('src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"', false);
        $response->assertSee('Grote Kerstquiz');
        $response->assertSee('Hoeveel spelers?');
        $response->assertSee('<p>Maximaal <em>zes</em></p>', false);
        $response->assertSee('id="b-000001"', false);

        // No posts yet: the latest posts block renders nothing.
        $response->assertDontSee('Uit de blog');

        // The page title is not repeated as a second H1 when a hero leads.
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function testGalleryLinksEveryPhotoToTheLightboxAndFoldsLongOnes()
    {
        $organisation = $this->createOrganisation();
        $asset = $this->createAsset();
        // An image deleted since.
        $deletedId = $asset->id + 1000;

        $items = [ [ 'image_id' => $deletedId, 'caption' => 'Weg' ] ];
        for ($i = 1; $i <= 15; $i++) {
            $items[] = [ 'image_id' => $asset->id, 'caption' => $i === 1 ? 'Eerste <foto>' : null ];
        }

        $this->createPage($organisation, 'fotos', [
            $this->block('gallery', [ 'title' => 'Foto\'s', 'layout' => 'featured', 'visible' => 6, 'items' => $items ], 'aaaaaa'),
            $this->block('gallery', [ 'title' => 'Leeg', 'items' => [ [ 'image_id' => $deletedId ] ] ], 'bbbbbb'),
            $this->block('gallery', [ 'title' => 'Kort', 'visible' => 12, 'items' => array_slice($items, 1, 13) ], 'cccccc'),
        ]);

        $html = $this->get('/fotos')->assertStatus(200)->getContent();

        // 15 photos; the deleted one is left out.
        $this->assertSame(15, substr_count($html, 'data-kv-gallery="g-aaaaaa"'));
        $this->assertStringNotContainsString('Weg', $html);
        $this->assertStringContainsString('kv-gallery kv-gallery-featured', $html);
        $this->assertStringContainsString('kv-gallery-item kv-gallery-big', $html);
        $this->assertStringContainsString('data-kv-caption="Eerste &lt;foto&gt;"', $html);
        $this->assertStringContainsString('alt="Eerste &lt;foto&gt;"', $html);
        $this->assertStringContainsString('aria-label="Foto 1 van 15 vergroten: Eerste &lt;foto&gt;"', $html);
        // Featured: the big photo plus an even number.
        $this->assertStringContainsString('data-kv-gallery-visible="7"', $html);
        $this->assertStringContainsString('Toon alle 15 foto\'s', $html);

        // A gallery without photos is skipped; 13 photos with 12 visible do not fold.
        $this->assertStringNotContainsString('id="b-bbbbbb"', $html);
        $this->assertStringContainsString('id="b-cccccc"', $html);
        $this->assertSame(1, substr_count($html, 'data-kv-gallery-visible='));
    }

    public function testPageWithoutHeroShowsItsTitleAsH1()
    {
        $organisation = $this->createOrganisation();
        $this->createPage($organisation, 'tekst', [ $this->block('rich_text', [ 'html' => '<p>x</p>' ]) ]);

        $this->get('/tekst')->assertSee('<h1 class="kv-pagehead-title">Page tekst (nl)</h1>', false);
    }

    public function testUnknownAndBrokenBlocksAreSkipped()
    {
        $organisation = $this->createOrganisation();
        $this->createPage($organisation, 'oud', [
            $this->block('marquee', [ 'text' => 'Weg ermee' ]),
            'not a block',
            $this->block('video', [ 'youtube_url' => 'https://evil.test/video' ]),
            $this->block('rich_text', [ 'html' => '<p>Blijft staan</p>' ]),
        ]);

        $this->get('/oud')
            ->assertStatus(200)
            ->assertSee('<p>Blijft staan</p>', false)
            ->assertDontSee('Weg ermee')
            ->assertDontSee('evil.test')
            ->assertDontSee('<iframe', false);
    }

    public function testUpcomingEventsListsOnlyThisOrganisationsPublishedUpcomingEvents()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();

        $published = $this->createEvent($organisation);
        $published->name = 'Gepubliceerde quiz';
        $published->save();

        $draft = $this->createEvent($organisation);
        $draft->name = 'Verborgen quiz';
        $draft->is_published = false;
        $draft->save();

        $past = $this->createEvent($organisation);
        $past->name = 'Voorbije quiz';
        $past->save();
        $past->eventDates()->update([ 'startDate' => now()->subDays(10), 'endDate' => now()->subDays(10)->addHours(3) ]);

        $foreign = $this->createEvent($other);
        $foreign->name = 'Quiz van een ander';
        $foreign->save();

        $package = $this->createEvent($organisation);
        $package->name = 'Quizpakket thuis';
        $package->event_type = Event::TYPE_PACKAGE;
        $package->save();

        $this->createPage($organisation, 'evenementen', [
            $this->block('upcoming_events', [ 'title' => 'Alles', 'limit' => 12, 'event_type' => 'all' ]),
        ]);
        $this->createPage($organisation, 'pakketten', [
            $this->block('upcoming_events', [ 'title' => 'Pakketten', 'limit' => 12, 'event_type' => 'package' ]),
        ]);
        $this->createPage($organisation, 'leeg', [
            $this->block('upcoming_events', [ 'limit' => 12, 'event_type' => 'package', 'empty_text' => 'Binnenkort meer' ]),
        ]);

        $this->get('/evenementen')
            ->assertStatus(200)
            ->assertSee('Gepubliceerde quiz')
            ->assertSee('Quizpakket thuis')
            ->assertDontSee('Verborgen quiz')
            ->assertDontSee('Voorbije quiz')
            ->assertDontSee('Quiz van een ander');

        $this->get('/pakketten')
            ->assertStatus(200)
            ->assertSee('Quizpakket thuis')
            ->assertDontSee('Gepubliceerde quiz');

        $package->is_published = false;
        $package->save();

        $this->get('/leeg')->assertStatus(200)->assertSee('Binnenkort meer');
    }
}
