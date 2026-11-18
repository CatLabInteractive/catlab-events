<?php

namespace Tests\Integration\Cms\Api;

use App\Http\Controllers\SitemapController;
use App\Models\Post;
use App\Models\PostTranslation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\Integration\Concerns\CreatesCmsFixtures;
use Tests\Integration\Concerns\CreatesEventFixtures;
use Tests\Integration\IntegrationTestCase;

/**
 * /api/v1/organisations/{organisation}/posts, /api/v1/posts/{post},
 * /api/v1/posts/{post}/translations and /api/v1/postTranslations/{id}: the
 * same rules as the admin editor (both go through App\Cms\PostWriter).
 */
class PostsApiTest extends IntegrationTestCase
{
    use CreatesEventFixtures;
    use CreatesCmsFixtures;

    private function translationInput(array $overrides = []): array
    {
        return array_merge([
            'locale' => 'nl',
            'slug' => 'quiz-van-het-jaar',
            'title' => 'De quiz van het jaar',
            'excerpt' => 'Een verslag.',
            'author' => 'Jan Quizmaster',
            'body' => '<p onclick="steal()">Het was <strong>fantastisch</strong>.</p><script>alert(1)</script>',
            'is_published' => true,
        ], $overrides);
    }

    private function emptyPost($organisation, ?Carbon $publishedAt = null): Post
    {
        $post = new Post();
        $post->organisation()->associate($organisation);
        $post->published_at = $publishedAt ?? Carbon::create(2019, 3, 14, 20);
        $post->save();

        return $post;
    }

    // -- posts ---------------------------------------------------------------

    public function testAnonymousRequestsAreRefused()
    {
        $organisation = $this->createOrganisation();

        $response = $this->getJson('/api/v1/organisations/' . $organisation->id . '/posts');
        $this->assertContains($response->getStatusCode(), [ 401, 302 ]);
    }

    public function testAdminCreatesAPost()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $asset = $this->createOrganisationAsset($organisation);

        $response = $this->actingAs($admin)->postJson('/api/v1/organisations/' . $organisation->id . '/posts', [
            'published_at' => '2019-03-14T19:00:00+00:00',
            'featured_image_id' => $asset->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('featured_image_id', $asset->id);

        $post = Post::findOrFail($response->json('id'));
        $this->assertSame($organisation->id, (int) $post->organisation_id);
        // 19:00 UTC is 20:00 in Brussels (the application timezone).
        $this->assertSame('2019-03-14 20:00:00', $post->published_at->format('Y-m-d H:i:s'));

        // RFC 822, like every other date of the API.
        $response = $this->actingAs($admin)->patchJson('/api/v1/posts/' . $post->id, [
            'published_at' => 'Fri, 15 Mar 19 10:00:00 +0100',
        ]);
        $response->assertStatus(200);
        $this->assertSame('2019-03-15 10:00:00', $post->fresh()->published_at->format('Y-m-d H:i:s'));
        $this->assertSame('Fri, 15 Mar 19 10:00:00 +0100', $response->json('published_at'));
    }

    public function testInvalidPostFieldsAre422()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $foreign = $this->createOrganisationAsset($other);
        $url = '/api/v1/organisations/' . $organisation->id . '/posts';

        $response = $this->actingAs($admin)->postJson($url, [ 'featured_image_id' => $foreign->id ]);
        $response->assertStatus(422);
        $this->assertArrayHasKey('featured_image_id', $response->json('error.issues'));

        $response = $this->actingAs($admin)->postJson($url, [ 'published_at' => 'gisteren' ]);
        $response->assertStatus(422);
        $this->assertArrayHasKey('published_at', $response->json('error.issues'));

        $this->assertSame(0, Post::count());
    }

    public function testIndexListsOnlyTheOrganisationsPostsWithTheirTranslations()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $mine = $this->createPost($organisation, 'quiz', Carbon::create(2019, 3, 14, 20));
        $this->createPost($other, 'buren', Carbon::create(2019, 3, 14, 20));

        $response = $this->actingAs($admin)->getJson('/api/v1/organisations/' . $organisation->id . '/posts');

        $response->assertStatus(200);
        $this->assertSame([ $mine->id ], array_column($response->json('items'), 'id'));
        $response->assertJsonPath('items.0.translations.items.0.slug', 'quiz');
        $response->assertJsonPath('items.0.translations.items.0.url', url('/2019/03/14/quiz'));
        $response->assertJsonPath('items.0.translations.items.0.is_published', true);
    }

    public function testOtherOrganisationsAndNonAdminsAreRefused()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $outsider = $this->createOrganisationAdmin($other);

        $post = $this->createPost($organisation, 'quiz', Carbon::create(2019, 3, 14, 20));
        $translation = $post->translation('nl');
        $base = '/api/v1/organisations/' . $organisation->id . '/posts';

        $this->actingAs($outsider)->getJson($base)->assertStatus(403);
        $this->actingAs($outsider)->postJson($base, [ 'published_at' => null ])->assertStatus(403);
        $this->actingAs($outsider)->getJson('/api/v1/posts/' . $post->id)->assertStatus(403);
        $this->actingAs($outsider)->patchJson('/api/v1/posts/' . $post->id, [ 'published_at' => null ])->assertStatus(403);
        $this->actingAs($outsider)->deleteJson('/api/v1/posts/' . $post->id)->assertStatus(403);

        $this->actingAs($outsider)->getJson('/api/v1/posts/' . $post->id . '/translations')->assertStatus(403);
        $this->actingAs($outsider)
            ->postJson('/api/v1/posts/' . $post->id . '/translations', $this->translationInput([ 'locale' => 'en', 'slug' => 'x' ]))
            ->assertStatus(403);
        $this->actingAs($outsider)->getJson('/api/v1/postTranslations/' . $translation->id)->assertStatus(403);
        $this->actingAs($outsider)
            ->patchJson('/api/v1/postTranslations/' . $translation->id, [ 'title' => 'Gekaapt' ])
            ->assertStatus(403);
        $this->actingAs($outsider)->deleteJson('/api/v1/postTranslations/' . $translation->id)->assertStatus(403);

        $this->assertSame(1, Post::count());
        $this->assertSame(1, PostTranslation::count());
        $this->assertNotNull($post->fresh()->published_at);
        $this->assertSame('Post quiz (nl)', $translation->fresh()->title);

        $this->actingAs($outsider)->getJson('/api/v1/posts/999999')->assertStatus(404);
    }

    public function testDeletePost()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $post = $this->createPost($organisation, 'quiz', Carbon::create(2019, 3, 14, 20));
        $this->createPostTranslation($post, 'en', 'quiz-night');

        $this->actingAs($admin)->deleteJson('/api/v1/posts/' . $post->id)->assertStatus(200);

        $this->assertNull(Post::find($post->id));
        $this->assertSame(0, PostTranslation::where('post_id', '=', $post->id)->count());
        $this->get('/2019/03/14/quiz')->assertStatus(404);
    }

    public function testBulkDeleteIsRefused()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $post = $this->createPost($organisation, 'quiz', Carbon::create(2019, 3, 14, 20));

        $this->actingAs($admin)
            ->deleteJson('/api/v1/organisations/' . $organisation->id . '/posts', [ 'items' => [ [ 'id' => $post->id ] ] ])
            ->assertStatus(405);
        $this->actingAs($admin)
            ->deleteJson('/api/v1/posts/' . $post->id . '/translations', [ 'items' => [ [ 'id' => $post->translation('nl')->id ] ] ])
            ->assertStatus(405);

        $this->assertNotNull(Post::find($post->id));
        $this->assertSame(1, PostTranslation::count());
    }

    // -- translations --------------------------------------------------------

    public function testCreateTranslationSanitisesTheBody()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $post = $this->emptyPost($organisation);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/posts/' . $post->id . '/translations', $this->translationInput());

        $response->assertStatus(201);
        $response->assertJsonPath('url', url('/2019/03/14/quiz-van-het-jaar'));
        $response->assertJsonPath('author', 'Jan Quizmaster');
        $response->assertJsonPath('is_published', true);

        /** @var PostTranslation $translation */
        $translation = PostTranslation::findOrFail($response->json('id'));
        $this->assertSame($post->id, (int) $translation->post_id);
        $this->assertSame($organisation->id, (int) $translation->organisation_id);
        $this->assertStringContainsString('<strong>fantastisch</strong>', $translation->body);
        $this->assertStringNotContainsString('<script', $translation->body);
        $this->assertStringNotContainsString('onclick', $translation->body);
        $this->assertStringNotContainsString('<script', $response->getContent());

        $this->actingAs($admin)
            ->getJson('/api/v1/postTranslations/' . $translation->id)
            ->assertStatus(200)
            ->assertJsonPath('body', $translation->body);

        $this->get('/2019/03/14/quiz-van-het-jaar')->assertStatus(200)->assertSee('fantastisch');
    }

    public function testPublishAndEdit()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $post = $this->emptyPost($organisation);

        $id = $this->actingAs($admin)
            ->postJson('/api/v1/posts/' . $post->id . '/translations', $this->translationInput([ 'is_published' => false ]))
            ->assertStatus(201)
            ->json('id');

        $this->get('/2019/03/14/quiz-van-het-jaar')->assertStatus(404);

        $this->actingAs($admin)
            ->patchJson('/api/v1/postTranslations/' . $id, [ 'is_published' => true, 'title' => 'Wat een avond' ])
            ->assertStatus(200)
            ->assertJsonPath('is_published', true)
            ->assertJsonPath('title', 'Wat een avond');

        // Untouched fields keep their value.
        $this->assertSame('Jan Quizmaster', PostTranslation::findOrFail($id)->author);

        $this->get('/2019/03/14/quiz-van-het-jaar')->assertStatus(200)->assertSee('Wat een avond');
    }

    public function testSlugRules()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $this->createPost($organisation, 'bestaat-al', Carbon::create(2018, 1, 1, 12));
        $this->createPost($other, 'bij-de-buren', Carbon::create(2018, 1, 1, 12));
        $post = $this->emptyPost($organisation);
        $url = '/api/v1/posts/' . $post->id . '/translations';

        foreach ([ '', 'Hoofd Letters', 'bestaat-al' ] as $slug) {
            $response = $this->actingAs($admin)->postJson($url, $this->translationInput([ 'slug' => $slug ]));
            $response->assertStatus(422);
            $this->assertArrayHasKey('slug', $response->json('error.issues'), 'slug "' . $slug . '"');
        }

        $this->actingAs($admin)->postJson($url, $this->translationInput([ 'locale' => 'de' ]))->assertStatus(422);

        // The same slug in another organisation, or another locale, is fine.
        $this->actingAs($admin)->postJson($url, $this->translationInput([ 'slug' => 'bij-de-buren' ]))->assertStatus(201);
        $this->actingAs($admin)->postJson($url, $this->translationInput([ 'locale' => 'en', 'slug' => 'bestaat-al' ]))->assertStatus(201);

        // One translation per locale.
        $this->actingAs($admin)->postJson($url, $this->translationInput([ 'slug' => 'nog-eens' ]))->assertStatus(422);

        // Renaming onto a taken slug.
        $nl = $post->translation('nl');
        $response = $this->actingAs($admin)->patchJson('/api/v1/postTranslations/' . $nl->id, [ 'slug' => 'bestaat-al' ]);
        $response->assertStatus(422);
        $this->assertArrayHasKey('slug', $response->json('error.issues'));
        $this->assertSame('bij-de-buren', $nl->fresh()->slug);
    }

    public function testDeleteTranslation()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $post = $this->createPost($organisation, 'quiz', Carbon::create(2019, 3, 14, 20));
        $en = $this->createPostTranslation($post, 'en', 'quiz-night');

        $this->actingAs($admin)->deleteJson('/api/v1/postTranslations/' . $en->id)->assertStatus(200);

        $this->assertNull($post->fresh()->translation('en'));
        $this->assertNotNull(Post::find($post->id));
        $this->get('/en/2019/03/14/quiz-night')->assertStatus(404);
    }

    public function testWritesForgetTheCaches()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $post = $this->emptyPost($organisation);
        $sitemapKey = SitemapController::cacheKey($organisation);

        Cache::put($sitemapKey, [ 'stale' ], 3600);

        $this->actingAs($admin)
            ->postJson('/api/v1/posts/' . $post->id . '/translations', $this->translationInput())
            ->assertStatus(201);

        $this->assertFalse(Cache::has($sitemapKey));
    }

    public function testDescriptionListsThePostPaths()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $paths = array_keys($this->actingAs($admin)->get('/api/v1/description.json')->assertStatus(200)->json('paths'));
        foreach ([
            '/api/v1/organisations/{organisation}/posts.{format}',
            '/api/v1/posts/{post}.{format}',
            '/api/v1/posts/{post}/translations.{format}',
            '/api/v1/postTranslations/{postTranslation}.{format}',
        ] as $path) {
            $this->assertContains($path, $paths);
        }
    }
}
