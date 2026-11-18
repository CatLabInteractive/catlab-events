<?php

namespace Tests\Integration\Cms;

use App\Models\Series;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Integration\Concerns\CreatesCmsFixtures;
use Tests\Integration\Concerns\CreatesEventFixtures;
use Tests\Integration\IntegrationTestCase;

/**
 * The public blog: /blog, dated post URLs, per-locale lists, the
 * latest_posts block and the blog block of the series page.
 */
class PostRoutingTest extends IntegrationTestCase
{
    use CreatesEventFixtures;
    use CreatesCmsFixtures;

    private function march14(int $hour = 20): Carbon
    {
        return Carbon::create(2019, 3, 14, $hour, 0, 0, 'Europe/Brussels');
    }

    private function actionFor(string $uri): string
    {
        return app('router')->getRoutes()->match(Request::create($uri))->getActionName();
    }

    public function testRoutesAreRegisteredBeforeThePageCatchAll()
    {
        $this->assertSame('App\Http\Controllers\PostController@show', $this->actionFor('/2019/03/14/quiz'));
        $this->assertSame('App\Http\Controllers\PostController@show', $this->actionFor('/en/2019/03/14/quiz'));
        $this->assertSame('App\Http\Controllers\PageController@blogIndex', $this->actionFor('/blog'));
        $this->assertSame('App\Http\Controllers\PageController@blogIndex', $this->actionFor('/fr/blog'));
        $this->assertSame('App\Http\Controllers\PageController@show', $this->actionFor('/2019/03/quiz'));
    }

    public function testPostRendersAtItsDatedUrl()
    {
        $organisation = $this->createOrganisation();
        $asset = $this->createOrganisationAsset($organisation, 'quiz.jpg');
        $post = $this->createPost($organisation, 'quiz-van-het-jaar', $this->march14(), '<p>Het was een <strong>topavond</strong>.</p>');
        $post->featured_image_id = $asset->id;
        $post->save();

        $response = $this->get('/2019/03/14/quiz-van-het-jaar');

        $response->assertStatus(200);
        $response->assertSee('<html lang="nl">', false);
        $response->assertSee('<title>Post quiz-van-het-jaar (nl) – Test organisation</title>', false);
        $response->assertSee('<h1 class="cms-post-title">Post quiz-van-het-jaar (nl)</h1>', false);
        $response->assertSee('<p>Het was een <strong>topavond</strong>.</p>', false);
        $response->assertSee('14 maart 2019');
        $response->assertSee('<link rel="canonical" href="http://localhost/2019/03/14/quiz-van-het-jaar" />', false);
        $response->assertSee('<meta property="og:type" content="article" />', false);
        $response->assertSee($asset->asset_key, false);
        $response->assertSee('content="Excerpt of quiz-van-het-jaar"', false);

        // Byline: no author set, so the organisation.
        $response->assertSee('<span class="cms-post-author">Test organisation</span>', false);

        $jsonLd = $this->jsonLd($response->getContent(), 'BlogPosting');
        $this->assertSame('Post quiz-van-het-jaar (nl)', $jsonLd['headline']);
        $this->assertSame('Test organisation', $jsonLd['author']['name']);
        $this->assertSame('Organization', $jsonLd['author']['@type']);
        $this->assertStringStartsWith('2019-03-14T20:00:00', $jsonLd['datePublished']);
        $this->assertSame('Test organisation', $jsonLd['publisher']['name']);

        // The WordPress form with a trailing slash serves the post too.
        $this->get('/2019/03/14/quiz-van-het-jaar/')->assertStatus(200);
    }

    public function testAuthorIsTheByline()
    {
        $organisation = $this->createOrganisation();
        $post = $this->createPost($organisation, 'verslag', $this->march14(), '<p>x</p>', 'nl', true);
        $translation = $post->translation('nl');
        $translation->author = 'Jan Quizmaster';
        $translation->save();

        $response = $this->get('/2019/03/14/verslag');
        $response->assertStatus(200);
        $response->assertSee('<span class="cms-post-author">Jan Quizmaster</span>', false);

        $jsonLd = $this->jsonLd($response->getContent(), 'BlogPosting');
        $this->assertSame([ '@type' => 'Person', 'name' => 'Jan Quizmaster' ], $jsonLd['author']);
    }

    public function testWrongDateRedirectsToTheCanonicalUrl()
    {
        $organisation = $this->createOrganisation();
        $post = $this->createPost($organisation, 'quiz', $this->march14());
        $this->createPostTranslation($post, 'en', 'quiz-night');

        $this->get('/2019/03/15/quiz')->assertStatus(301)->assertRedirect('http://localhost/2019/03/14/quiz');
        $this->get('/2018/01/01/quiz?utm=x')->assertStatus(301)->assertRedirect('http://localhost/2019/03/14/quiz?utm=x');
        $this->get('/en/2020/12/31/quiz-night')->assertStatus(301)->assertRedirect('http://localhost/en/2019/03/14/quiz-night');
    }

    public function testDateIsInBrusselsTime()
    {
        $organisation = $this->createOrganisation();
        // 23:30 in Brussels is still the 14th, although it is the 14th 22:30 UTC.
        $this->createPost($organisation, 'laat', Carbon::create(2019, 3, 14, 23, 30, 0, 'Europe/Brussels'));

        $this->get('/2019/03/14/laat')->assertStatus(200);
    }

    public function testUnpublishedFutureUndatedAndDeletedPostsAre404()
    {
        $organisation = $this->createOrganisation();

        $this->createPost($organisation, 'concept', $this->march14(), '<p>x</p>', 'nl', false);
        $future = Carbon::now()->addDays(3);
        $this->createPost($organisation, 'straks', $future);
        $this->createPost($organisation, 'zonder-datum', null);
        $deleted = $this->createPost($organisation, 'weg', $this->march14());
        $deleted->delete();

        $this->get('/2019/03/14/concept')->assertStatus(404);
        $this->get('/' . $future->copy()->setTimezone('Europe/Brussels')->format('Y/m/d') . '/straks')->assertStatus(404);
        $this->get('/2019/03/14/zonder-datum')->assertStatus(404);
        $this->get('/2019/03/14/weg')->assertStatus(404);
        $this->get('/2019/03/14/bestaat-niet')->assertStatus(404);

        $this->get('/blog')->assertStatus(200)
            ->assertDontSee('Post concept')
            ->assertDontSee('Post straks')
            ->assertDontSee('Post zonder-datum')
            ->assertDontSee('Post weg');
    }

    public function testUnpublishedPostCanBePreviewedByAnAdmin()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $this->createPost($organisation, 'concept', $this->march14(), '<p>Nog niet klaar</p>', 'nl', false);

        $this->get('/2019/03/14/concept?preview=1')->assertStatus(404);

        $this->actingAs($admin)->get('/2019/03/14/concept?preview=1')
            ->assertStatus(200)
            ->assertSee('Nog niet klaar')
            ->assertSee('<meta name="robots" content="noindex">', false);
    }

    public function testAnotherOrganisationsPostIs404()
    {
        $a = $this->createOrganisation();
        $b = $this->createOrganisation();
        $this->createOrganisationDomain($a, 'a.test');
        $this->createOrganisationDomain($b, 'b.test');
        $this->createPost($a, 'van-a', $this->march14());

        $this->actAsHost('a.test');
        $this->get('http://a.test/2019/03/14/van-a')->assertStatus(200);
        $this->get('http://a.test/blog')->assertSee('Post van-a (nl)');

        $this->actAsHost('b.test');
        $this->get('http://b.test/2019/03/14/van-a')->assertStatus(404);
        $this->get('http://b.test/blog')->assertStatus(200)->assertDontSee('Post van-a (nl)');
    }

    public function testBlogIndexPaginatesNewestFirst()
    {
        config([ 'cms.posts_per_page' => 2 ]);

        $organisation = $this->createOrganisation();
        $this->createPost($organisation, 'oudste', Carbon::create(2019, 1, 1, 12));
        $this->createPost($organisation, 'middelste', Carbon::create(2019, 2, 1, 12));
        $this->createPost($organisation, 'nieuwste', Carbon::create(2019, 3, 1, 12));

        $response = $this->get('/blog');
        $response->assertStatus(200);
        $response->assertSee('<link rel="canonical" href="http://localhost/blog" />', false);
        $response->assertSeeInOrder([ 'Post nieuwste (nl)', 'Post middelste (nl)' ]);
        $response->assertDontSee('Post oudste (nl)');
        $response->assertSee('href="http://localhost/2019/03/01/nieuwste"', false);
        $response->assertSee('Excerpt of nieuwste');
        $response->assertSee('Oudere berichten');
        $response->assertSee('href="http://localhost/blog?page=2"', false);

        $response = $this->get('/blog?page=2');
        $response->assertStatus(200);
        $response->assertSee('Post oudste (nl)');
        $response->assertDontSee('Post nieuwste (nl)');
        $response->assertSee('Nieuwere berichten');
        $response->assertSee('<link rel="canonical" href="http://localhost/blog?page=2" />', false);

        $this->get('/blog?page=3')->assertStatus(404);
    }

    public function testBlogWithoutPostsSaysSo()
    {
        $this->createOrganisation();

        $this->get('/blog')->assertStatus(200)->assertSee('Er zijn nog geen berichten.');
    }

    public function testEnglishBlogListsOnlyPostsWithAnEnglishTranslation()
    {
        $organisation = $this->createOrganisation();
        $both = $this->createPost($organisation, 'tweetalig', $this->march14());
        $this->createPostTranslation($both, 'en', 'bilingual');
        $this->createPost($organisation, 'enkel-nederlands', $this->march14(10));
        $draft = $this->createPost($organisation, 'concept-en', $this->march14(9));
        $this->createPostTranslation($draft, 'en', 'draft-en', '<p>x</p>', false);

        $response = $this->get('/en/blog');
        $response->assertStatus(200);
        $response->assertSee('<html lang="en">', false);
        $response->assertSee('Post bilingual (en)');
        $response->assertSee('href="http://localhost/en/2019/03/14/bilingual"', false);
        $response->assertDontSee('enkel-nederlands');
        $response->assertDontSee('Post tweetalig (nl)');
        $response->assertDontSee('draft-en');

        $this->get('/blog')->assertSee('Post tweetalig (nl)')->assertSee('Post enkel-nederlands (nl)');

        // A missing translation is a 404, never a Dutch fallback.
        $this->get('/en/2019/03/14/bilingual')->assertStatus(200)->assertSee('Post bilingual (en)');
        $this->get('/en/2019/03/14/enkel-nederlands')->assertStatus(404);
        $this->get('/fr/2019/03/14/tweetalig')->assertStatus(404);
        $this->get('/2019/03/14/bilingual')->assertStatus(404);
    }

    public function testPostHasAlternatesForItsPublishedTranslations()
    {
        $organisation = $this->createOrganisation();
        $post = $this->createPost($organisation, 'quiz', $this->march14());
        $this->createPostTranslation($post, 'en', 'quiz-night');
        $this->createPostTranslation($post, 'fr', 'soiree-quiz', '<p>x</p>', false);

        $response = $this->get('/en/2019/03/14/quiz-night');
        $response->assertStatus(200);
        $response->assertSee('<link rel="alternate" hreflang="nl" href="http://localhost/2019/03/14/quiz" />', false);
        $response->assertSee('<link rel="alternate" hreflang="en" href="http://localhost/en/2019/03/14/quiz-night" />', false);
        $response->assertSee('<link rel="alternate" hreflang="x-default" href="http://localhost/2019/03/14/quiz" />', false);
        $response->assertDontSee('soiree-quiz');
    }

    public function testLatestPostsBlockListsPublishedPostsOfTheLocale()
    {
        $organisation = $this->createOrganisation();
        $this->createPost($organisation, 'een', Carbon::create(2019, 1, 1, 12));
        $this->createPost($organisation, 'twee', Carbon::create(2019, 2, 1, 12));
        $this->createPost($organisation, 'drie', Carbon::create(2019, 3, 1, 12));
        $this->createPost($organisation, 'concept', Carbon::create(2019, 4, 1, 12), '<p>x</p>', 'nl', false);
        $this->createPost($organisation, 'toekomst', Carbon::now()->addDay());
        $this->createPost($this->createOrganisation(), 'buren', Carbon::create(2019, 3, 2, 12));

        $this->createPage($organisation, 'start', [
            [ 'id' => 'a1b2c3', 'type' => 'latest_posts', 'data' => [ 'title' => 'Uit de blog', 'limit' => 2 ] ],
        ]);

        $response = $this->get('/start');
        $response->assertStatus(200);
        $response->assertSee('Uit de blog');
        $response->assertSeeInOrder([ 'Post drie (nl)', 'Post twee (nl)' ]);
        $response->assertSee('href="http://localhost/2019/03/01/drie"', false);
        $response->assertDontSee('Post een (nl)');
        $response->assertDontSee('Post concept');
        $response->assertDontSee('Post toekomst');
        $response->assertDontSee('Post buren');

        // Cached, but a new post shows up straight away.
        $this->createPost($organisation, 'vier', Carbon::create(2019, 3, 5, 12));
        $this->get('/start')->assertSeeInOrder([ 'Post vier (nl)', 'Post drie (nl)' ]);
    }

    public function testNavigationLinksToTheLocalBlogWhenThereArePosts()
    {
        $organisation = $this->createOrganisation();
        $organisation->blog_url = 'https://blog.example.test/';
        $organisation->save();
        $this->createPage($organisation, 'over-ons');

        $this->get('/over-ons')->assertSee('href="https://blog.example.test/"', false);

        $this->createPost($organisation, 'eerste', $this->march14());

        $response = $this->get('/over-ons');
        $response->assertSee('href="http://localhost/blog"', false);
        $response->assertDontSee('https://blog.example.test/');
    }

    public function testSeriesPageRendersTheBlogBlockWithAndWithoutPosts()
    {
        // The series page's "finished events" query groups by
        // event_dates.event_id, which MySQL 8 accepts through functional
        // dependency detection and MariaDB does not.
        DB::statement("SET SESSION sql_mode = REPLACE(@@SESSION.sql_mode, 'ONLY_FULL_GROUP_BY', '')");

        $organisation = $this->createOrganisation();
        $series = new Series();
        $series->organisation()->associate($organisation);
        $series->name = 'Test series';
        $series->slug = 'test-series';
        $series->active = 1;
        $series->save();

        $response = $this->get('/s/' . $series->id . '/test-series');
        $response->assertStatus(200);
        $response->assertDontSee('id="blog"', false);

        $this->createPost($organisation, 'verslag', $this->march14());

        $response = $this->get('/s/' . $series->id . '/test-series');
        $response->assertStatus(200);
        $response->assertSee('id="blog"', false);
        $response->assertSee('Post verslag (nl)');
        $response->assertSee('href="http://localhost/2019/03/14/verslag"', false);
    }

    /**
     * The JSON-LD object of $type in the page.
     */
    private function jsonLd(string $html, string $type): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        foreach ($matches[1] as $json) {
            $data = json_decode($json, true);
            if (is_array($data) && ($data['@type'] ?? null) === $type) {
                return $data;
            }
        }

        $this->fail('No ' . $type . ' JSON-LD found.');
    }
}
