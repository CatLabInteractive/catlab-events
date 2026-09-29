<?php

namespace Tests\Integration\Cms\Api;

use App\Http\Controllers\SitemapController;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\Integration\Concerns\CreatesCmsFixtures;
use Tests\Integration\Concerns\CreatesEventFixtures;
use Tests\Integration\IntegrationTestCase;

/**
 * /api/v1/pages/{page}/translations and /api/v1/pageTranslations/{id}: the
 * same validation, sanitising and path rules as the admin editor (both go
 * through App\Cms\PageWriter), plus the API image upload.
 */
class PageTranslationsApiTest extends IntegrationTestCase
{
    use CreatesEventFixtures;
    use CreatesCmsFixtures;

    private function emptyPage($organisation): Page
    {
        $page = new Page();
        $page->organisation()->associate($organisation);
        $page->save();

        return $page;
    }

    private function translationInput(array $overrides = []): array
    {
        return array_merge([
            'locale' => 'nl',
            'slug' => 'over-ons',
            'title' => 'Over ons',
            'meta_description' => 'Wie zijn we?',
            'is_published' => false,
            'blocks' => [
                [ 'id' => 'a1b2c3', 'type' => 'hero', 'data' => [
                    'title' => 'Welkom', 'align' => 'center',
                    'buttons' => [ [ 'label' => 'Kalender', 'url' => '/calendar', 'style' => 'primary' ] ],
                ] ],
                [ 'id' => 'd4e5f6', 'type' => 'rich_text', 'data' => [
                    'html' => '<p onclick="steal()">Wij zijn <strong>de quizfabriek</strong>.</p><script>alert(1)</script>',
                ] ],
            ],
        ], $overrides);
    }

    public function testCreateTranslationWithBlocks()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->emptyPage($organisation);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/pages/' . $page->id . '/translations', $this->translationInput());

        $response->assertStatus(201);
        $response->assertJsonPath('path', 'over-ons');
        $response->assertJsonPath('url', url('/over-ons'));
        $response->assertJsonPath('is_published', false);
        $response->assertJsonPath('blocks.0.type', 'hero');
        $response->assertJsonPath('blocks.0.data.buttons.0.url', '/calendar');

        /** @var PageTranslation $translation */
        $translation = PageTranslation::findOrFail($response->json('id'));
        $this->assertSame($page->id, (int) $translation->page_id);
        $this->assertSame($organisation->id, (int) $translation->organisation_id);
        $this->assertCount(2, $translation->blocks);

        // Rich text is sanitised on the way in.
        $html = $translation->blocks[1]['data']['html'];
        $this->assertStringContainsString('<strong>de quizfabriek</strong>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('<script', $response->getContent());

        // The view returns the blocks as a list of objects.
        $this->actingAs($admin)
            ->getJson('/api/v1/pageTranslations/' . $translation->id)
            ->assertStatus(200)
            ->assertJsonPath('blocks.1.type', 'rich_text');
    }

    public function testPublishAndEdit()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->emptyPage($organisation);

        $id = $this->actingAs($admin)
            ->postJson('/api/v1/pages/' . $page->id . '/translations', $this->translationInput())
            ->assertStatus(201)
            ->json('id');

        $response = $this->actingAs($admin)
            ->patchJson('/api/v1/pageTranslations/' . $id, [ 'is_published' => true, 'title' => 'Wie wij zijn' ]);
        $response->assertStatus(200);
        $response->assertJsonPath('is_published', true);
        $response->assertJsonPath('title', 'Wie wij zijn');

        $translation = PageTranslation::findOrFail($id);
        $this->assertTrue($translation->is_published);
        $this->assertNotNull($translation->published_at);
        // Untouched fields keep their value.
        $this->assertCount(2, $translation->blocks);

        $this->get('/over-ons')->assertStatus(200)->assertSee('Wie wij zijn');
    }

    public function testSlugRenameCascadesToChildPaths()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $about = $this->createPage($organisation, 'over-ons');
        $press = $this->createPage($organisation, 'over-ons/pers');

        $this->actingAs($admin)
            ->patchJson('/api/v1/pageTranslations/' . $about->translation('nl')->id, [ 'slug' => 'wie-zijn-we' ])
            ->assertStatus(200)
            ->assertJsonPath('path', 'wie-zijn-we');

        $this->assertSame('wie-zijn-we/pers', $press->translation('nl')->fresh()->path);
    }

    public function testInvalidBlocksAre422()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->emptyPage($organisation);
        $url = '/api/v1/pages/' . $page->id . '/translations';

        // Unknown block type.
        $response = $this->actingAs($admin)->postJson($url, $this->translationInput([
            'blocks' => [ [ 'id' => 'a1b2c3', 'type' => 'carousel', 'data' => [] ] ],
        ]));
        $response->assertStatus(422);
        $this->assertArrayHasKey('blocks.0.type', $response->json('error.issues'));

        // Per-type rules at the right index.
        $response = $this->actingAs($admin)->postJson($url, $this->translationInput([
            'blocks' => [
                [ 'id' => 'a1b2c3', 'type' => 'rich_text', 'data' => [ 'html' => '<p>Ok</p>' ] ],
                [ 'id' => 'd4e5f6', 'type' => 'cta', 'data' => [ 'title' => 'Doe mee', 'button' => [ 'label' => 'Klik', 'url' => 'javascript:alert(1)' ] ] ],
            ],
        ]));
        $response->assertStatus(422);
        $this->assertNotEmpty(array_filter(array_keys($response->json('error.issues')), function ($key) {
            return strpos($key, 'blocks.1.data.') === 0;
        }));

        // Blocks must be a list of objects.
        $this->actingAs($admin)
            ->postJson($url, $this->translationInput([ 'blocks' => 'not json' ]))
            ->assertStatus(422);

        $this->assertSame(0, PageTranslation::count());
    }

    public function testSlugAndPathRules()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $this->createPage($organisation, 'over-ons');
        $page = $this->emptyPage($organisation);
        $url = '/api/v1/pages/' . $page->id . '/translations';

        foreach ([ 'events', '', 'Hoofd Letters', 'over-ons' ] as $slug) {
            $response = $this->actingAs($admin)->postJson($url, $this->translationInput([ 'slug' => $slug ]));
            $response->assertStatus(422);
            $this->assertArrayHasKey('slug', $response->json('error.issues'), 'slug "' . $slug . '"');
        }

        $this->actingAs($admin)->postJson($url, $this->translationInput([ 'locale' => 'de', 'slug' => 'ueber-uns' ]))
            ->assertStatus(422);

        // One translation per locale.
        $this->actingAs($admin)->postJson($url, $this->translationInput([ 'slug' => 'nieuw' ]))->assertStatus(201);
        $this->actingAs($admin)->postJson($url, $this->translationInput([ 'slug' => 'nog-eens' ]))->assertStatus(422);
    }

    public function testImagesMustBelongToTheOrganisation()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->emptyPage($organisation);
        $url = '/api/v1/pages/' . $page->id . '/translations';

        $foreign = $this->createOrganisationAsset($other);

        $response = $this->actingAs($admin)->postJson($url, $this->translationInput([ 'og_image_id' => $foreign->id ]));
        $response->assertStatus(422);
        $this->assertArrayHasKey('og_image_id', $response->json('error.issues'));

        $response = $this->actingAs($admin)->postJson($url, $this->translationInput([
            'blocks' => [ [ 'id' => 'a1b2c3', 'type' => 'text_image', 'data' => [
                'html' => '<p>Zaal</p>', 'image_id' => $foreign->id, 'image_position' => 'left',
            ] ] ],
        ]));
        $response->assertStatus(422);
        $this->assertArrayHasKey('blocks.0.data.image_id', $response->json('error.issues'));

        // Upload through the API, then use it.
        $upload = $this->actingAs($admin)->post('/api/v1/organisations/' . $organisation->id . '/assets', [
            'file' => UploadedFile::fake()->image('zaal.png', 800, 600),
        ], [ 'Accept' => 'application/json' ]);
        $upload->assertStatus(201);
        $upload->assertJsonPath('name', 'zaal.png');
        $this->assertStringStartsWith('https://storage.test/assets/', $upload->json('url'));

        $this->actingAs($admin)->postJson($url, $this->translationInput([
            'og_image_id' => $upload->json('id'),
            'blocks' => [ [ 'id' => 'a1b2c3', 'type' => 'text_image', 'data' => [
                'html' => '<p>Zaal</p>', 'image_id' => $upload->json('id'), 'image_position' => 'left',
            ] ] ],
        ]))->assertStatus(201)->assertJsonPath('blocks.0.data.image_id', $upload->json('id'));
    }

    public function testApiUploadIsForOrganisationAdminsAndImagesOnly()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $outsider = $this->createOrganisationAdmin($other);
        $url = '/api/v1/organisations/' . $organisation->id . '/assets';

        $this->actingAs($outsider)
            ->post($url, [ 'file' => UploadedFile::fake()->image('x.png') ], [ 'Accept' => 'application/json' ])
            ->assertStatus(403);

        $this->actingAs($admin)
            ->post($url, [ 'file' => UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;') ], [ 'Accept' => 'application/json' ])
            ->assertStatus(422);

        $this->assertCount(0, $this->centralStorage->stored);
    }

    public function testOtherOrganisationsAreRefused()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $outsider = $this->createOrganisationAdmin($other);

        $page = $this->createPage($organisation, 'over-ons');
        $translation = $page->translation('nl');

        $this->actingAs($outsider)->getJson('/api/v1/pages/' . $page->id . '/translations')->assertStatus(403);
        $this->actingAs($outsider)
            ->postJson('/api/v1/pages/' . $page->id . '/translations', $this->translationInput([ 'locale' => 'en', 'slug' => 'about' ]))
            ->assertStatus(403);
        $this->actingAs($outsider)->getJson('/api/v1/pageTranslations/' . $translation->id)->assertStatus(403);
        $this->actingAs($outsider)
            ->patchJson('/api/v1/pageTranslations/' . $translation->id, [ 'title' => 'Gekaapt' ])
            ->assertStatus(403);
        $this->actingAs($outsider)->deleteJson('/api/v1/pageTranslations/' . $translation->id)->assertStatus(403);

        $this->assertSame(1, PageTranslation::count());
        $this->assertSame('Page over-ons (nl)', $translation->fresh()->title);
    }

    public function testDeleteTranslation()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $parent = $this->createPage($organisation, 'over-ons');
        $child = $this->createPage($organisation, 'over-ons/pers');

        // A translation with same-locale children cannot go first.
        $this->actingAs($admin)
            ->deleteJson('/api/v1/pageTranslations/' . $parent->translation('nl')->id)
            ->assertStatus(422);

        $this->actingAs($admin)
            ->deleteJson('/api/v1/pageTranslations/' . $child->translation('nl')->id)
            ->assertStatus(200);

        $this->assertNull($child->fresh()->translation('nl'));
        $this->assertNotNull(Page::find($child->id));
    }

    public function testWritesForgetTheSitemapCache()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->emptyPage($organisation);
        $key = SitemapController::cacheKey($organisation);

        Cache::put($key, [ 'stale' ], 3600);

        $this->actingAs($admin)
            ->postJson('/api/v1/pages/' . $page->id . '/translations', $this->translationInput())
            ->assertStatus(201);

        $this->assertFalse(Cache::has($key));
    }
}
