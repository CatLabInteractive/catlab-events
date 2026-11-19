<?php

namespace Tests\Integration\Cms;

use App\Cms\HtmlSanitizer;
use App\Models\PageTranslation;
use Tests\Integration\Concerns\CreatesCmsFixtures;
use Tests\Integration\Concerns\CreatesEventFixtures;
use Tests\Integration\Fakes\FakeCentralStorage;
use Tests\Integration\IntegrationTestCase;

/**
 * cms:extract-galleries on pages imported before the gallery block.
 */
class ExtractGalleriesTest extends IntegrationTestCase
{
    use CreatesEventFixtures;
    use CreatesCmsFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        // Asset URLs (on the fake storage host) must survive the sanitiser.
        config([ 'centralStorage.front' => FakeCentralStorage::FRONT ]);
        $this->app->forgetInstance(HtmlSanitizer::class);
    }

    public function testPhotosInTextBecomeAGalleryOnlyOnThePagesAsked()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $photos = array_map(function ($i) use ($organisation) {
            return $this->createOrganisationAsset($organisation, 'foto' . $i . '.jpg');
        }, [ 1, 2, 3 ]);
        $foreign = $this->createOrganisationAsset($other);

        $gallery = '<figure class="wp-block-gallery"><ul class="blocks-gallery-grid">' . implode('', array_map(function ($asset) {
            return '<li class="blocks-gallery-item"><figure><img src="' . $asset->getUrl() . '" alt=""></figure></li>';
        }, $photos)) . '</ul></figure>';
        $html = '<p>Onze quizzen.</p><h2>In beeld</h2>' . $gallery;

        $page = $this->createPage($organisation, 'quizzen-op-maat', [ [ 'id' => 'aaaaaa', 'type' => 'rich_text', 'data' => [ 'html' => $html ] ] ]);
        $this->createPage($organisation, 'ander', [ [ 'id' => 'bbbbbb', 'type' => 'rich_text', 'data' => [ 'html' => $html ] ] ]);
        // Another organisation's photos are never moved.
        $this->createPage($organisation, 'vreemd', [ [ 'id' => 'cccccc', 'type' => 'rich_text', 'data' => [
            'html' => '<figure class="wp-block-gallery"><img src="' . $foreign->getUrl() . '"></figure>',
        ] ] ]);

        $this->artisan('cms:extract-galleries', [ '--organisation' => $organisation->id, '--page' => [ 'quizzen-op-maat' ], '--dry-run' => true ])
            ->expectsOutputToContain('would change /quizzen-op-maat (nl): rich_text, gallery (3 photos)')
            ->assertExitCode(0);
        $this->assertSame('rich_text', $page->fresh()->translation('nl')->blocks[0]['type'] ?? null);
        $this->assertCount(1, $page->fresh()->translation('nl')->blocks, 'dry run');

        $this->artisan('cms:extract-galleries', [ '--organisation' => $organisation->id, '--page' => [ 'quizzen-op-maat', 'vreemd' ] ])
            ->expectsOutputToContain('changed /quizzen-op-maat (nl)')
            ->expectsOutputToContain('1 page translation(s) changed.')
            ->assertExitCode(0);

        $translation = $page->fresh()->translation('nl');
        $this->assertTrue($translation->is_published, 'stays published');
        $this->assertSame([ 'rich_text', 'gallery' ], array_column($translation->blocks, 'type'));
        $this->assertSame('aaaaaa', $translation->blocks[0]['id']);
        $this->assertSame('<p>Onze quizzen.</p>', $translation->blocks[0]['data']['html']);
        $this->assertSame('In beeld', $translation->blocks[1]['data']['title']);
        $this->assertSame(array_map(function ($a) {
            return $a->id;
        }, $photos), array_column($translation->blocks[1]['data']['items'], 'image_id'));

        $ander = PageTranslation::where('path', 'ander')->firstOrFail();
        $this->assertCount(1, $ander->blocks, 'not asked');

        $this->get('/quizzen-op-maat')->assertStatus(200)->assertSee('data-kv-gallery="g-', false);

        // Without --page every page: only "ander" is left to change.
        $this->artisan('cms:extract-galleries', [ '--organisation' => $organisation->id ])
            ->expectsOutputToContain('1 page translation(s) changed.')
            ->assertExitCode(0);
        $this->assertSame([ 'rich_text', 'gallery' ], array_column($page->fresh()->translation('nl')->blocks, 'type'));
    }
}
