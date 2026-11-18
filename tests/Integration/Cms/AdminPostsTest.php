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

    /**
     * What the post form posts (fields[...]).
     */
    private function postFields(array $overrides = []): array
    {
        return array_merge([
            'published_at' => [ 'date' => '2019-03-14', 'time' => '20:00' ],
            'featured_image_id' => '',
        ], $overrides);
    }

    /**
     * What the translation form posts (fields[...]).
     */
    private function translationFields(array $overrides = []): array
    {
        return array_merge([
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

    private function postTranslation($admin, Post $post, array $fields)
    {
        return $this->actingAs($admin)
            ->from('/admin/posts/' . $post->id . '/translations/create')
            ->post('/admin/posts/' . $post->id . '/translations', [ 'fields' => $fields ]);
    }

    private function putTranslation($admin, Post $post, string $locale, array $fields)
    {
        $translation = $post->fresh()->translation($locale);

        return $this->actingAs($admin)
            ->from('/admin/posts/' . $post->id . '/translations/' . $translation->id . '/edit')
            ->put('/admin/posts/' . $post->id . '/translations/' . $translation->id, [ 'fields' => $fields ]);
    }

    private function emptyPost($organisation, ?Carbon $publishedAt = null): Post
    {
        $post = new Post();
        $post->organisation()->associate($organisation);
        $post->published_at = $publishedAt ?: Carbon::create(2019, 3, 14, 20);
        $post->save();

        return $post;
    }

    public function testAnonymousUsersAreSentToTheLogin()
    {
        $this->get('/admin/posts')->assertRedirect('/login');
        $this->post('/admin/posts', [ 'fields' => $this->postFields() ])->assertRedirect('/login');
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
        $en = $this->createPostTranslation($new, 'en', 'new', '<p>x</p>', false);
        $this->createPost($other, 'van-de-buren', Carbon::create(2019, 1, 1, 12));
        $this->createPost($organisation, 'kladje', null);

        $response = $this->actingAs($admin)->get('/admin/posts');

        $response->assertStatus(200);
        // A post without a date (a draft) comes first, then the newest.
        $response->assertSeeInOrder([ 'Post kladje (nl)', 'Post nieuw (nl)', 'Post oud (nl)' ]);
        $response->assertDontSee('van-de-buren');
        $response->assertSee('/admin/posts/' . $old->id . '/edit', false);
        $response->assertSee('Blogbericht toevoegen');

        // Every translation links to its editor; drafts say so.
        $response->assertSee('Engels (concept)');
        $response->assertSee('/admin/posts/' . $new->id . '/translations/' . $en->id . '/edit', false);
    }

    public function testATranslationOfAMissingPostOrPageIsNotFound()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $this->actingAs($admin)->get('/admin/posts/999999/translations/create')->assertStatus(404);
        $this->actingAs($admin)->get('/admin/pages/999999/translations/create')->assertStatus(404);
    }

    public function testCreatePostThenItsDutchTranslation()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $asset = $this->createOrganisationAsset($organisation);

        // A new post starts at today.
        $this->actingAs($admin)->get('/admin/posts/create')
            ->assertStatus(200)
            ->assertSee('Publicatiedatum')
            ->assertSee('value="' . Carbon::now(config('app.timezone'))->format('Y-m-d') . '"', false);

        $response = $this->actingAs($admin)->post('/admin/posts', [ 'fields' => $this->postFields([ 'featured_image_id' => $asset->id ]) ]);

        /** @var Post $post */
        $post = Post::firstOrFail();
        $response->assertRedirect('/admin/posts/' . $post->id . '/translations/create');

        $this->assertSame($organisation->id, (int) $post->organisation_id);
        $this->assertSame($asset->id, (int) $post->featured_image_id);
        $this->assertSame('2019-03-14 20:00', $post->published_at->copy()->setTimezone(config('app.timezone'))->format('Y-m-d H:i'));

        $this->actingAs($admin)->get('/admin/posts/' . $post->id . '/translations/create')
            ->assertStatus(200)
            ->assertSee('Nederlands')
            ->assertSee('cms-html', false);

        $response = $this->postTranslation($admin, $post, $this->translationFields([ 'locale' => 'nl' ]));

        /** @var PostTranslation $translation */
        $translation = PostTranslation::firstOrFail();
        $response->assertRedirect('/admin/posts/' . $post->id . '/translations/' . $translation->id . '/edit');

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

        // The edit forms show it.
        $this->actingAs($admin)->get('/admin/posts/' . $post->id . '/translations/' . $translation->id . '/edit')
            ->assertStatus(200)
            ->assertSee('value="quiz-van-het-jaar"', false)
            ->assertSee('value="Jan Quizmaster"', false);
        $this->actingAs($admin)->get('/admin/posts/' . $post->id . '/edit')
            ->assertStatus(200)
            ->assertSee('value="2019-03-14"', false)
            ->assertSee('value="20:00"', false);

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
            ->post('/admin/posts', [ 'fields' => $this->postFields([ 'featured_image_id' => $upload->json('id') ]) ]);

        $post = Post::firstOrFail();
        $this->assertSame($upload->json('id'), (int) $post->featured_image_id);
        $this->assertCount(1, $this->centralStorage->stored);

        $this->postTranslation($admin, $post, $this->translationFields([ 'locale' => 'nl' ]));
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
            ->post('/admin/posts', [ 'fields' => $this->postFields([ 'featured_image_id' => $foreign->id ]) ])
            ->assertRedirect('/admin/posts/create');

        $this->assertStringContainsString('Niet opgeslagen', (string) session('message'));
        $this->assertSame(0, Post::count());
    }

    public function testInvalidInputIsRejected()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $post = $this->emptyPost($organisation);

        foreach ([
            [ 'slug' => 'Met Spaties!' ],
            [ 'slug' => '' ],
            [ 'title' => '' ],
        ] as $overrides) {
            $this->postTranslation($admin, $post, $this->translationFields([ 'locale' => 'nl' ] + $overrides))
                ->assertRedirect('/admin/posts/' . $post->id . '/translations/create');
            $this->assertStringContainsString('Niet opgeslagen', (string) session('message'));
        }

        $this->assertSame(0, PostTranslation::count());

        $this->actingAs($admin)
            ->from('/admin/posts/create')
            ->post('/admin/posts', [ 'fields' => $this->postFields([ 'published_at' => [ 'date' => 'gisteren om acht', 'time' => '' ] ]) ])
            ->assertRedirect('/admin/posts/create');
        $this->assertSame(1, Post::count());
    }

    public function testSlugIsNormalised()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $post = $this->emptyPost($organisation);

        $this->postTranslation($admin, $post, $this->translationFields([ 'locale' => 'nl', 'slug' => ' /Quiz-Van-Het-Jaar/ ' ]));

        $this->assertSame('quiz-van-het-jaar', PostTranslation::firstOrFail()->slug);
    }

    public function testDuplicateSlugInTheSameLocaleIsRejected()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $this->createPost($organisation, 'quiz-van-het-jaar', Carbon::create(2018, 1, 1, 12));
        $this->createPost($other, 'andere-organisatie', Carbon::create(2018, 1, 1, 12));

        $post = $this->emptyPost($organisation);
        $this->postTranslation($admin, $post, $this->translationFields([ 'locale' => 'nl' ]));
        $this->assertStringContainsString('Niet opgeslagen', (string) session('message'));
        $this->assertNull($post->fresh()->translation('nl'));

        // The same slug in another locale of the same organisation is fine.
        $this->postTranslation($admin, $post, $this->translationFields([ 'locale' => 'en' ]))
            ->assertSessionHas('message', 'Vertaling toegevoegd.');

        // And one that another organisation uses.
        $this->postTranslation($admin, $post, $this->translationFields([ 'locale' => 'nl', 'slug' => 'andere-organisatie' ]))
            ->assertSessionHas('message', 'Vertaling toegevoegd.');
    }

    public function testPublishToggle()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $post = $this->createPost($organisation, 'concept', Carbon::create(2019, 3, 14, 20), '<p>x</p>', 'nl', false);
        $translation = $post->translation('nl');

        $this->get('/2019/03/14/concept')->assertStatus(404);

        $this->putTranslation($admin, $post, 'nl', $this->translationFields([ 'slug' => 'concept', 'is_published' => '1' ]))
            ->assertRedirect('/admin/posts/' . $post->id . '/translations/' . $translation->id . '/edit');
        $this->get('/2019/03/14/concept')->assertStatus(200);

        $this->putTranslation($admin, $post, 'nl', $this->translationFields([ 'slug' => 'concept', 'is_published' => '0' ]));
        $this->get('/2019/03/14/concept')->assertStatus(404);
    }

    public function testAddFrenchTranslation()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $post = $this->createPost($organisation, 'quiz', Carbon::create(2019, 3, 14, 20));

        // Only the missing languages are offered.
        $form = $this->actingAs($admin)
            ->get('/admin/posts/' . $post->id . '/translations/create')
            ->assertStatus(200)
            ->assertSee('Frans')
            ->getContent();
        $this->assertStringNotContainsString('<option value="nl"', $form);

        $this->postTranslation($admin, $post, $this->translationFields([ 'locale' => 'fr', 'slug' => 'soiree-quiz', 'title' => 'Soirée quiz' ]))
            ->assertSessionHas('message', 'Vertaling toegevoegd.');

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
            ->put('/admin/posts/' . $post->id, [ 'fields' => $this->postFields([ 'published_at' => [ 'date' => '2019-04-01', 'time' => '09:30' ] ]) ])
            ->assertSessionHas('message', 'Opgeslagen.');

        $this->get('/en/2019/04/01/quiz-night')->assertStatus(200);
        $this->get('/en/2019/03/14/quiz-night')->assertRedirect('http://localhost/en/2019/04/01/quiz-night');
    }

    public function testAnotherOrganisationsPostIsForbidden()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $foreign = $this->createPost($other, 'geheim', Carbon::create(2019, 3, 14, 20));
        $translation = $foreign->translation('nl');
        $base = '/admin/posts/' . $foreign->id;

        $this->actingAs($admin)->get($base)->assertStatus(403);
        $this->actingAs($admin)->get($base . '/edit')->assertStatus(403);
        $this->actingAs($admin)->get($base . '/translations/create')->assertStatus(403);
        $this->actingAs($admin)->get($base . '/translations/' . $translation->id . '/edit')->assertStatus(403);
        $this->actingAs($admin)
            ->put($base . '/translations/' . $translation->id, [ 'fields' => $this->translationFields([ 'slug' => 'geheim', 'title' => 'Gekaapt' ]) ])
            ->assertStatus(403);
        $this->actingAs($admin)->post($base . '/translations', [ 'fields' => $this->translationFields([ 'locale' => 'en' ]) ])->assertStatus(403);
        $this->actingAs($admin)->delete($base . '/translations/' . $translation->id)->assertStatus(403);
        $this->actingAs($admin)->delete($base)->assertStatus(403);

        $this->assertSame('Post geheim (nl)', $foreign->fresh()->translation('nl')->title);
        $this->assertSame(1, $foreign->translations()->count());
        $this->assertNotNull(Post::find($foreign->id));
    }

    public function testDeleteTranslationAndPost()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $post = $this->createPost($organisation, 'quiz', Carbon::create(2019, 3, 14, 20));
        $en = $this->createPostTranslation($post, 'en', 'quiz-night');

        $this->actingAs($admin)
            ->delete('/admin/posts/' . $post->id . '/translations/' . $en->id)
            ->assertRedirect('/admin/posts/' . $post->id . '?tab=translations');
        $this->assertNull($post->fresh()->translation('en'));

        $this->actingAs($admin)->delete('/admin/posts/' . $post->id)->assertRedirect('/admin/posts');
        $this->assertNull(Post::find($post->id));
        $this->assertSame(0, PostTranslation::where('post_id', '=', $post->id)->count());
        $this->get('/2019/03/14/quiz')->assertStatus(404);

        // The slug is free again.
        $again = $this->emptyPost($organisation);
        $this->postTranslation($admin, $again, $this->translationFields([ 'locale' => 'nl', 'slug' => 'quiz' ]))
            ->assertSessionHas('message', 'Vertaling toegevoegd.');
    }

    public function testSavingForgetsTheCaches()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $post = $this->emptyPost($organisation);
        $sitemapKey = SitemapController::cacheKey($organisation);

        Cache::put($sitemapKey, [ 'stale' ], 3600);
        Cache::put('cms.latest_posts:' . $organisation->id . ':nl', [ 'stale' ], 3600);

        $this->postTranslation($admin, $post, $this->translationFields([ 'locale' => 'nl' ]))
            ->assertSessionHas('message', 'Vertaling toegevoegd.');

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
