<?php

namespace Tests\Integration\Cms;

use App\Http\Controllers\SitemapController;
use App\Models\Post;
use App\Models\PostTranslation;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\Integration\Concerns\CreatesCmsFixtures;
use Tests\Integration\Concerns\CreatesEventFixtures;
use Tests\Integration\IntegrationTestCase;

/**
 * The blog post editor of the admin: organisation scoping, per-locale
 * translations, sanitised bodies, featured images of the organisation.
 */
class AdminPostsTest extends IntegrationTestCase
{
    use CreatesEventFixtures;
    use CreatesCmsFixtures;

    private function formInput(array $overrides = []): array
    {
        return array_merge([
            'published_at' => '2019-03-14T20:00',
            'featured_image_id' => '',
            'slug' => 'quiz-van-het-jaar',
            'title' => 'De quiz van het jaar',
            'excerpt' => 'Een verslag.',
            'author' => 'Jan Quizmaster',
            'body' => '<p onclick="steal()">Het was <strong>fantastisch</strong>.</p><script>alert(1)</script>',
            'meta_title' => '',
            'meta_description' => '',
            'is_published' => '1',
        ], $overrides);
    }

    public function testAnonymousUsersAreSentToTheLogin()
    {
        $this->get('/admin/posts')->assertRedirect('/login');
        $this->post('/admin/posts', $this->formInput())->assertRedirect('/login');
    }

    public function testNonAdminsAreSentAway()
    {
        $this->createOrganisation();
        $this->createUser(); // the first user ever created becomes admin (User::boot)
        $user = $this->createUser();

        $this->actingAs($user)->get('/admin/posts')->assertRedirect('/');
    }

    public function testIndexListsOnlyTheActiveOrganisationsPostsNewestFirst()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $old = $this->createPost($organisation, 'oud', Carbon::create(2018, 5, 1, 12));
        $new = $this->createPost($organisation, 'nieuw', Carbon::create(2019, 5, 1, 12));
        $this->createPostTranslation($new, 'en', 'new', '<p>x</p>', false);
        $this->createPost($other, 'van-de-buren', Carbon::create(2019, 1, 1, 12));

        $response = $this->actingAs($admin)->get('/admin/posts');

        $response->assertStatus(200);
        $response->assertSeeInOrder([ 'Post nieuw (nl)', 'Post oud (nl)' ]);
        $response->assertSee('1 mei 2019');
        $response->assertDontSee('van-de-buren');
        $response->assertSee('/admin/posts/' . $old->id . '/edit/nl', false);
        $response->assertSee('data-locale-badge="nl" data-state="published"', false);
        $response->assertSee('data-locale-badge="en" data-state="draft"', false);
        $response->assertSee('data-locale-badge="fr" data-state="missing"', false);
        $response->assertSee('/admin/posts/' . $new->id . '/translations/fr/create', false);
    }

    public function testCreatePostWithDutchTranslation()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $asset = $this->createOrganisationAsset($organisation);

        $this->actingAs($admin)->get('/admin/posts/create')->assertStatus(200)->assertSee('Nieuw bericht');

        $response = $this->actingAs($admin)->post('/admin/posts', $this->formInput([ 'featured_image_id' => $asset->id ]));

        /** @var PostTranslation $translation */
        $translation = PostTranslation::firstOrFail();
        $response->assertRedirect('/admin/posts/' . $translation->post_id . '/edit/nl');

        $post = $translation->post;
        $this->assertSame($organisation->id, (int) $post->organisation_id);
        $this->assertSame($asset->id, (int) $post->featured_image_id);
        $this->assertSame('2019-03-14 20:00', $post->published_at->format('Y-m-d H:i'));
        $this->assertSame('nl', $translation->locale);
        $this->assertSame('quiz-van-het-jaar', $translation->slug);
        $this->assertSame('Jan Quizmaster', $translation->author);
        $this->assertSame('Een verslag.', $translation->excerpt);
        $this->assertTrue($translation->is_published);
        $this->assertNull($translation->meta_title);

        // The body is sanitised.
        $this->assertStringContainsString('<strong>fantastisch</strong>', $translation->body);
        $this->assertStringNotContainsString('<script', $translation->body);
        $this->assertStringNotContainsString('onclick', $translation->body);

        // The edit form shows it.
        $this->actingAs($admin)->get('/admin/posts/' . $post->id . '/edit/nl')
            ->assertStatus(200)
            ->assertSee('value="quiz-van-het-jaar"', false)
            ->assertSee('value="2019-03-14T20:00"', false)
            ->assertSee('value="Jan Quizmaster"', false);

        $this->get('/2019/03/14/quiz-van-het-jaar')->assertStatus(200)->assertSee('fantastisch');
    }

    public function testFeaturedImageUploadedThroughTheEditor()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $upload = $this->actingAs($admin)
            ->post('/admin/cms/upload', [ 'file' => UploadedFile::fake()->image('zaal.png') ])
            ->assertStatus(200);

        $this->actingAs($admin)
            ->post('/admin/posts', $this->formInput([ 'featured_image_id' => $upload->json('id') ]))
            ->assertSessionHasNoErrors();

        $post = Post::firstOrFail();
        $this->assertSame($upload->json('id'), (int) $post->featured_image_id);
        $this->assertCount(1, $this->centralStorage->stored);

        $this->get('/2019/03/14/quiz-van-het-jaar')->assertSee($post->featuredImage->asset_key, false);
    }

    public function testFeaturedImageMustBelongToTheOrganisation()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $foreign = $this->createOrganisationAsset($other);

        $this->actingAs($admin)
            ->from('/admin/posts/create')
            ->post('/admin/posts', $this->formInput([ 'featured_image_id' => $foreign->id ]))
            ->assertRedirect('/admin/posts/create')
            ->assertSessionHasErrors('featured_image_id');

        $this->assertSame(0, Post::count());
    }

    public function testInvalidInputIsRejected()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        foreach ([
            [ 'slug' => 'Met Spaties!' ],
            [ 'slug' => '' ],
            [ 'title' => '' ],
            [ 'published_at' => 'gisteren' ],
        ] as $overrides) {
            $this->actingAs($admin)
                ->post('/admin/posts', $this->formInput($overrides))
                ->assertSessionHasErrors(array_keys($overrides)[0]);
        }

        $this->assertSame(0, Post::count());
    }

    public function testSlugIsNormalised()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $this->actingAs($admin)
            ->post('/admin/posts', $this->formInput([ 'slug' => ' /Quiz-Van-Het-Jaar/ ' ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('quiz-van-het-jaar', PostTranslation::firstOrFail()->slug);
    }

    public function testDuplicateSlugInTheSameLocaleIsRejected()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $this->createPost($organisation, 'quiz-van-het-jaar', Carbon::create(2018, 1, 1, 12));
        $this->createPost($other, 'andere-organisatie', Carbon::create(2018, 1, 1, 12));

        $this->actingAs($admin)
            ->post('/admin/posts', $this->formInput())
            ->assertSessionHasErrors('slug');
        $this->assertSame(1, Post::forOrganisation($organisation)->count());

        // The same slug in another organisation is fine.
        $this->actingAs($admin)
            ->post('/admin/posts', $this->formInput([ 'slug' => 'andere-organisatie' ]))
            ->assertSessionHasNoErrors();

        // And in another locale of the same organisation.
        $post = $this->createPost($organisation, 'tweede', Carbon::create(2018, 2, 1, 12));
        $this->actingAs($admin)
            ->put('/admin/posts/' . $post->id . '/edit/en', $this->formInput())
            ->assertSessionHasNoErrors();
    }

    public function testPublishToggle()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $post = $this->createPost($organisation, 'concept', Carbon::create(2019, 3, 14, 20), '<p>x</p>', 'nl', false);

        $this->get('/2019/03/14/concept')->assertStatus(404);

        $this->actingAs($admin)
            ->put('/admin/posts/' . $post->id . '/edit/nl', $this->formInput([ 'slug' => 'concept', 'is_published' => '1' ]))
            ->assertRedirect('/admin/posts/' . $post->id . '/edit/nl');
        $this->get('/2019/03/14/concept')->assertStatus(200);

        $this->actingAs($admin)
            ->put('/admin/posts/' . $post->id . '/edit/nl', $this->formInput([ 'slug' => 'concept', 'is_published' => '0' ]));
        $this->get('/2019/03/14/concept')->assertStatus(404);
    }

    public function testAddFrenchTranslation()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $post = $this->createPost($organisation, 'quiz', Carbon::create(2019, 3, 14, 20));

        // The edit URL of a missing translation leads to its create form.
        $this->actingAs($admin)
            ->get('/admin/posts/' . $post->id . '/edit/fr')
            ->assertRedirect('/admin/posts/' . $post->id . '/translations/fr/create');
        $this->actingAs($admin)
            ->get('/admin/posts/' . $post->id . '/translations/fr/create')
            ->assertStatus(200)
            ->assertSee('Frans');

        $this->actingAs($admin)
            ->put('/admin/posts/' . $post->id . '/edit/fr', $this->formInput([
                'slug' => 'soiree-quiz', 'title' => 'Soirée quiz', 'published_at' => '2019-03-14T20:00',
            ]))
            ->assertRedirect('/admin/posts/' . $post->id . '/edit/fr');

        $translation = $post->fresh()->translation('fr');
        $this->assertSame('Soirée quiz', $translation->title);
        $this->assertSame('nl', $post->fresh()->translation('nl')->locale);

        $this->get('/fr/2019/03/14/soiree-quiz')->assertStatus(200)->assertSee('Soirée quiz');
    }

    public function testChangingTheDateMovesEveryTranslation()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $post = $this->createPost($organisation, 'quiz', Carbon::create(2019, 3, 14, 20));
        $this->createPostTranslation($post, 'en', 'quiz-night');

        $this->actingAs($admin)
            ->put('/admin/posts/' . $post->id . '/edit/nl', $this->formInput([ 'slug' => 'quiz', 'published_at' => '2019-04-01T09:30' ]))
            ->assertSessionHasNoErrors();

        $this->get('/en/2019/04/01/quiz-night')->assertStatus(200);
        $this->get('/en/2019/03/14/quiz-night')->assertRedirect('http://localhost/en/2019/04/01/quiz-night');
    }

    public function testAnotherOrganisationsPostIs404()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $foreign = $this->createPost($other, 'geheim', Carbon::create(2019, 3, 14, 20));

        $this->actingAs($admin)->get('/admin/posts/' . $foreign->id . '/edit/nl')->assertStatus(404);
        $this->actingAs($admin)->get('/admin/posts/' . $foreign->id . '/translations/en/create')->assertStatus(404);
        $this->actingAs($admin)
            ->put('/admin/posts/' . $foreign->id . '/edit/nl', $this->formInput([ 'slug' => 'geheim', 'title' => 'Gekaapt' ]))
            ->assertStatus(404);
        $this->actingAs($admin)->delete('/admin/posts/' . $foreign->id . '/translations/nl')->assertStatus(404);
        $this->actingAs($admin)->delete('/admin/posts/' . $foreign->id)->assertStatus(404);

        $this->assertSame('Post geheim (nl)', $foreign->fresh()->translation('nl')->title);
        $this->assertNotNull(Post::find($foreign->id));
    }

    public function testDeleteTranslationAndPost()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $post = $this->createPost($organisation, 'quiz', Carbon::create(2019, 3, 14, 20));
        $this->createPostTranslation($post, 'en', 'quiz-night');

        $this->actingAs($admin)
            ->delete('/admin/posts/' . $post->id . '/translations/en')
            ->assertRedirect('/admin/posts');
        $this->assertNull($post->fresh()->translation('en'));

        $this->actingAs($admin)->delete('/admin/posts/' . $post->id)->assertRedirect('/admin/posts');
        $this->assertNull(Post::find($post->id));
        $this->assertSame(0, PostTranslation::where('post_id', '=', $post->id)->count());
        $this->get('/2019/03/14/quiz')->assertStatus(404);

        // The slug is free again.
        $this->actingAs($admin)
            ->post('/admin/posts', $this->formInput([ 'slug' => 'quiz' ]))
            ->assertSessionHasNoErrors();
    }

    public function testSavingForgetsTheCaches()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $sitemapKey = SitemapController::cacheKey($organisation);

        Cache::put($sitemapKey, [ 'stale' ], 3600);
        Cache::put('cms.latest_posts:' . $organisation->id . ':nl', [ 'stale' ], 3600);

        $this->actingAs($admin)->post('/admin/posts', $this->formInput())->assertSessionHasNoErrors();

        $this->assertFalse(Cache::has($sitemapKey));
        $this->assertFalse(Cache::has('cms.latest_posts:' . $organisation->id . ':nl'));
    }

    public function testBodyIsSanitisedWhateverTheWritePath()
    {
        $organisation = $this->createOrganisation();
        $post = $this->createPost($organisation, 'direct', Carbon::create(2019, 3, 14, 20), '<p>Ok</p><img src="x" onerror="alert(1)"><script>alert(2)</script>');

        $body = $post->translation('nl')->body;
        $this->assertStringContainsString('<p>Ok</p>', $body);
        $this->assertStringNotContainsString('onerror', $body);
        $this->assertStringNotContainsString('<script', $body);
    }
}
