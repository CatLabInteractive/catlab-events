<?php

namespace Tests\Integration\Cms;

use App\Cms\HtmlSanitizer;
use App\Models\CmsRedirect;
use App\Models\Page;
use App\Models\Post;
use Carbon\Carbon;
use CatLab\CentralStorage\Client\Models\Asset;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Integration\Concerns\CreatesCmsFixtures;
use Tests\Integration\Concerns\CreatesEventFixtures;
use Tests\Integration\Fakes\FakeCentralStorage;
use Tests\Integration\IntegrationTestCase;

/**
 * wordpress:import against a faked WordPress REST API.
 */
class WordPressImportTest extends IntegrationTestCase
{
    use CreatesEventFixtures;
    use CreatesCmsFixtures;

    const GIF = 'R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

    /**
     * @var string[] URLs WordPress was asked for
     */
    private $requested = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Asset URLs (on the fake storage host) must survive the sanitiser.
        config([ 'centralStorage.front' => FakeCentralStorage::FRONT ]);
        $this->app->forgetInstance(HtmlSanitizer::class);

        Sleep::fake();
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $this->requested[] = $request->url();
            $parts = parse_url($request->url());
            parse_str($parts['query'] ?? '', $query);
            $path = $parts['path'];

            if ($parts['host'] !== 'www.quizfabriek.be' && $parts['host'] !== 'quizfabriek.be') {
                return Http::response('stray host', 500);
            }

            switch ($path) {
                case '/wp-json/wp/v2/posts':
                    // Two pages of one post each.
                    $posts = $this->fixture('wp-posts.json');
                    $page = (int) ($query['page'] ?? 1);
                    return Http::response([ $posts[$page - 1] ], 200, [ 'X-WP-TotalPages' => '2' ]);
                case '/wp-json/wp/v2/pages':
                    return Http::response($this->fixture('wp-pages.json'), 200, [ 'X-WP-TotalPages' => '1' ]);
                case '/wp-content/uploads/2019/03/foto.jpg':
                case '/wp-content/uploads/2019/03/winnaars.jpg':
                    return Http::response(base64_decode(self::GIF), 200, [ 'Content-Type' => 'image/jpeg' ]);
                case '/wp-content/uploads/2019/03/vragen.pdf':
                    return Http::response("%PDF-1.4\n%fake\n", 200, [ 'Content-Type' => 'application/pdf' ]);
                case '/wp-sitemap.xml':
                    return Http::response(
                        '<?xml version="1.0"?><sitemapindex>'
                        . '<sitemap><loc>https://www.quizfabriek.be/wp-sitemap-posts-post-1.xml</loc></sitemap>'
                        . '<sitemap><loc>https://www.quizfabriek.be/wp-sitemap-posts-page-1.xml</loc></sitemap>'
                        . '<sitemap><loc>https://elsewhere.example/wp-sitemap-posts-post-1.xml</loc></sitemap>'
                        . '</sitemapindex>'
                    );
                case '/wp-sitemap-posts-post-1.xml':
                    return Http::response(
                        '<?xml version="1.0"?><urlset>'
                        . '<url><loc>https://www.quizfabriek.be/2019/03/14/quiz-van-het-jaar/</loc></url>'
                        . '<url><loc>https://www.quizfabriek.be/2019/07/01/zomerquiz/</loc></url>'
                        . '</urlset>'
                    );
                case '/wp-sitemap-posts-page-1.xml':
                    return Http::response(
                        '<?xml version="1.0"?><urlset>'
                        . '<url><loc>https://www.quizfabriek.be/over-ons/</loc></url>'
                        . '<url><loc>https://www.quizfabriek.be/over-ons/team/</loc></url>'
                        . '<url><loc>https://www.quizfabriek.be/category/nieuws/</loc></url>'
                        . '</urlset>'
                    );
            }

            return Http::response('not found', 404);
        });
    }

    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__ . '/fixtures/' . $name), true);
    }

    private function import(array $options = [])
    {
        return $this->artisan('wordpress:import', $options);
    }

    public function testPostsAreImportedWithTheirMedia()
    {
        $organisation = $this->createOrganisation();

        $this->import([ '--organisation' => $organisation->id, '--posts' => true ])->assertExitCode(0);

        $this->assertDatabaseCount('posts', 2);

        /** @var Post $post */
        $post = Post::where('wp_post_id', 101)->firstOrFail();
        $this->assertSame($organisation->id, (int) $post->organisation_id);
        $this->assertTrue($post->published_at->equalTo(Carbon::create(2019, 3, 14, 20, 0, 0, 'Europe/Brussels')));

        $translation = $post->translation('nl');
        $this->assertSame('quiz-van-het-jaar', $translation->slug);
        $this->assertSame('Quiz van het jaar – verslag', $translation->title);
        $this->assertSame('Een avond om nooit te vergeten', $translation->excerpt);
        $this->assertSame('Thijs & co', $translation->author);
        $this->assertTrue($translation->is_published);

        // Media: the resize and the featured image are one asset (the original).
        $this->assertSame(3, Asset::query()->count());
        $this->assertSame(0, Asset::query()->where('organisation_id', '!=', $organisation->id)->count());
        $foto = Asset::query()->where('name', 'foto.jpg')->firstOrFail();
        $this->assertSame($foto->id, (int) $post->featured_image_id);
        $this->assertNotContains('https://www.quizfabriek.be/wp-content/uploads/2019/03/foto-1024x768.jpg', $this->requested);

        $body = $translation->body;
        $this->assertStringNotContainsString('wp-content/uploads', $body);
        $this->assertStringNotContainsString('srcset', $body);
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringContainsString('src="' . $foto->getUrl() . '"', $body);
        // An old blog.quizfabriek.be upload URL, fetched from the site itself.
        $winnaars = Asset::query()->where('name', 'winnaars.jpg')->firstOrFail();
        $this->assertStringContainsString('src="' . $winnaars->getUrl() . '" alt="Winnaars"', $body);
        $pdf = Asset::query()->where('name', 'vragen.pdf')->firstOrFail();
        $this->assertStringContainsString('href="' . $pdf->getUrl() . '"', $body);

        $video = Post::where('wp_post_id', 102)->firstOrFail()->translation('nl');
        $this->assertStringContainsString('src="https://www.youtube.com/embed/abc123?feature=oembed"', $video->body);
        $this->assertSame('Quizmaster', $video->author);

        // Every old upload URL redirects to its asset.
        $redirects = CmsRedirect::query()->where('organisation_id', $organisation->id)->pluck('to_url', 'from_path')->all();
        $this->assertSame($foto->getUrl(), $redirects['wp-content/uploads/2019/03/foto.jpg']);
        $this->assertSame($foto->getUrl(), $redirects['wp-content/uploads/2019/03/foto-1024x768.jpg']);
        $this->assertSame($pdf->getUrl(), $redirects['wp-content/uploads/2019/03/vragen.pdf']);

        // The post renders at its WordPress URL.
        $this->get('/2019/03/14/quiz-van-het-jaar/')->assertStatus(200)->assertSee('Quiz van het jaar – verslag');
        $this->get('/wp-content/uploads/2019/03/foto.jpg')->assertRedirect($foto->getUrl());
    }

    public function testRunningAgainChangesNothing()
    {
        $organisation = $this->createOrganisation();

        $this->import([ '--organisation' => $organisation->id, '--posts' => true, '--pages' => true ])->assertExitCode(0);
        $counts = $this->counts();
        $body = Post::where('wp_post_id', 101)->firstOrFail()->translation('nl')->body;
        $downloads = count(array_filter($this->requested, function ($url) {
            return strpos($url, '/wp-content/uploads/') !== false;
        }));

        $this->import([ '--organisation' => $organisation->id, '--posts' => true, '--pages' => true ])->assertExitCode(0);

        $this->assertSame($counts, $this->counts());
        $this->assertSame($body, Post::where('wp_post_id', 101)->firstOrFail()->translation('nl')->body);
        $this->assertSame($downloads, count(array_filter($this->requested, function ($url) {
            return strpos($url, '/wp-content/uploads/') !== false;
        })), 'a re-run downloads no media');
    }

    public function testARerunUpdatesPostsButKeepsEditedPages()
    {
        $organisation = $this->createOrganisation();
        $this->import([ '--organisation' => $organisation->id, '--posts' => true, '--pages' => true ]);

        $post = Post::where('wp_post_id', 102)->firstOrFail()->translation('nl');
        $post->title = 'Lokaal aangepast';
        $post->save();

        $page = Page::where('wp_post_id', 201)->firstOrFail()->translation('nl');
        $page->title = 'Herwerkt door een redacteur';
        $page->save();

        $this->import([ '--organisation' => $organisation->id, '--posts' => true, '--pages' => true ]);

        $this->assertSame('Zomerquiz', $post->fresh()->title);
        $this->assertSame('Herwerkt door een redacteur', $page->fresh()->title);
    }

    public function testPagesAreImportedAsUnpublishedDrafts()
    {
        $organisation = $this->createOrganisation();

        $this->import([ '--organisation' => $organisation->id, '--pages' => true, '--home' => 'over-ons' ])->assertExitCode(0);

        $this->assertDatabaseCount('pages', 4);
        $this->assertNull(Page::where('wp_post_id', 203)->first(), '-nieuw pages are skipped');

        // "blog" is a route of the ticket site.
        $this->assertSame('wp-blog', Page::where('wp_post_id', 205)->firstOrFail()->translation('nl')->path);
        $this->assertNull(Page::where('wp_post_id', 204)->first(), '--skip default');

        $parent = Page::where('wp_post_id', 201)->firstOrFail();
        $child = Page::where('wp_post_id', 202)->firstOrFail();
        $this->assertSame($parent->id, (int) $child->parent_id);
        $this->assertSame($parent->id, (int) $organisation->fresh()->home_page_id);

        $translation = $child->translation('nl');
        $this->assertSame('over-ons/team', $translation->path);
        $this->assertSame('Ons team', $translation->title);
        $this->assertFalse($translation->is_published);
        $this->assertCount(1, $translation->blocks);
        $this->assertSame('rich_text', $translation->blocks[0]['type']);

        // Elementor's wrappers are gone; content and the image remain.
        $html = $translation->blocks[0]['data']['html'];
        $this->assertStringNotContainsString('elementor', $html);
        $this->assertStringNotContainsString('<div', $html);
        $this->assertStringContainsString('<h2>Het team</h2>', $html);
        $this->assertStringContainsString('<p>Wij maken quizzen.</p>', $html);
        $foto = Asset::query()->where('name', 'foto.jpg')->firstOrFail();
        $this->assertStringContainsString('src="' . $foto->getUrl() . '"', $html);

        // Unpublished: not served.
        $this->get('/over-ons/team')->assertStatus(404);
    }

    public function testElementorPagesBecomeBlocks()
    {
        $organisation = $this->createOrganisation();

        $this->import([ '--organisation' => $organisation->id, '--pages' => true ])->assertExitCode(0);

        $blocks = Page::where('wp_post_id', 206)->firstOrFail()->translation('nl')->blocks;
        $this->assertSame(
            [ 'hero', 'text_image', 'reviews', 'cards', 'video', 'upcoming_events', 'rich_text' ],
            array_column($blocks, 'type')
        );

        [ $hero, $textImage, $reviews, $cards, $video, $events, $text ] = array_column($blocks, 'data');

        $this->assertSame('De perfecte quiz', $hero['title']);
        $this->assertSame('Voor iedereen een quiz.', $hero['subtitle']);
        // Links to the site become paths; ALL CAPS labels sentence case.
        // assertEquals: MySQL's JSON column reorders keys, MariaDB keeps them.
        $this->assertEquals([ [ 'label' => 'Evenementen', 'url' => '/', 'style' => 'primary' ] ], $hero['buttons']);

        $foto = Asset::query()->where('name', 'foto.jpg')->firstOrFail();
        $this->assertSame('Quizevenementen', $textImage['title']);
        $this->assertSame($foto->id, $textImage['image_id'], 'lazy image, original of the resize');
        $this->assertSame('left', $textImage['image_position']);
        $this->assertSame('<p>Een paar keer per jaar.</p>', $textImage['html'], 'a duplicated text once');
        $this->assertEquals([ 'label' => 'Meer', 'url' => '/evenementen' ], $textImage['button']);

        // Two quote sections merged into one reviews block.
        $this->assertSame([ 'Een topavond.', 'Heel vlot.' ], array_column($reviews['items'], 'quote'));
        $this->assertSame([ 'An Peeters, HR', 'Jan Janssens' ], array_column($reviews['items'], 'author'));

        $this->assertSame(2, $cards['columns']);
        $this->assertSame([ 'Quizwitz', 'Gud Nuse' ], array_column($cards['items'], 'title'));
        $this->assertSame('https://www.quizwitz.com/', $cards['items'][0]['url']);
        $this->assertNotNull($cards['items'][1]['image_id']);

        $this->assertSame('https://www.youtube.com/watch?v=-mH8ZUo5XZc', $video['youtube_url']);

        // The hand-made list of coming events is the live list; past ones go.
        $this->assertSame('komende evenementen', $events['title']);

        $this->assertStringContainsString('<h2>Contact</h2>', $text['html']);
        $this->assertStringContainsString('<li>Quizzen van overal</li>', $text['html']);
        $this->assertStringNotContainsString('script', $text['html']);

        // It renders.
        $translation = Page::where('wp_post_id', 206)->firstOrFail()->translation('nl');
        $translation->is_published = true;
        $translation->save();
        $this->get('/quizzen')->assertStatus(200)->assertSee('De perfecte quiz')->assertSee('Een topavond.');
    }

    public function testRebuildPagesReplacesOnlyUnpublishedPages()
    {
        $organisation = $this->createOrganisation();
        $this->import([ '--organisation' => $organisation->id, '--pages' => true ]);

        $draft = Page::where('wp_post_id', 206)->firstOrFail()->translation('nl');
        $draft->blocks = [ [ 'id' => 'abcdef', 'type' => 'rich_text', 'data' => [ 'html' => '<p>Oud</p>' ] ] ];
        $draft->save();

        $published = Page::where('wp_post_id', 201)->firstOrFail()->translation('nl');
        $published->blocks = [ [ 'id' => 'abcdef', 'type' => 'rich_text', 'data' => [ 'html' => '<p>Herwerkt</p>' ] ] ];
        $published->is_published = true;
        $published->save();

        $this->import([ '--organisation' => $organisation->id, '--pages' => true ])->assertExitCode(0);
        $this->assertSame('<p>Oud</p>', $draft->fresh()->blocks[0]['data']['html'], 'without --rebuild-pages nothing changes');

        $this->import([ '--organisation' => $organisation->id, '--pages' => true, '--rebuild-pages' => true ])
            ->expectsOutputToContain('rebuilt page 206')
            ->assertExitCode(0);

        $this->assertSame('hero', $draft->fresh()->blocks[0]['type']);
        $this->assertSame('<p>Herwerkt</p>', $published->fresh()->blocks[0]['data']['html'], 'published pages are kept');
        $this->assertDatabaseCount('pages', 4);
    }

    public function testRedirectsListsUnmappedSitemapUrls()
    {
        $organisation = $this->createOrganisation();

        $this->import([ '--organisation' => $organisation->id ])
            ->expectsOutputToContain('/category/nieuws/')
            ->doesntExpectOutputToContain('  /over-ons/team/')
            ->doesntExpectOutputToContain('  /2019/03/14/quiz-van-het-jaar/')
            ->assertExitCode(0);

        $this->assertEmpty(array_filter($this->requested, function ($url) {
            return strpos($url, 'elsewhere.example') !== false;
        }), 'only the site itself is fetched');
    }

    public function testDryRunWritesNothing()
    {
        $organisation = $this->createOrganisation();

        $this->import([ '--organisation' => $organisation->id, '--dry-run' => true, '--home' => 'over-ons' ])
            ->expectsOutputToContain('would create post 101')
            ->expectsOutputToContain('would create page 202')
            ->assertExitCode(0);

        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('pages', 0);
        $this->assertDatabaseCount('cms_redirects', 0);
        $this->assertSame(0, Asset::query()->count());
        $this->assertNull($organisation->fresh()->home_page_id);
        $this->assertEmpty(array_filter($this->requested, function ($url) {
            return strpos($url, '/wp-content/uploads/') !== false;
        }), 'a dry run downloads nothing');
    }

    public function testAnUnknownOrganisationFails()
    {
        $this->import([ '--organisation' => 999999 ])->assertExitCode(1);
    }

    private function counts(): array
    {
        $counts = [];
        foreach ([ 'posts', 'post_translations', 'pages', 'page_translations', 'cms_redirects', 'assets' ] as $table) {
            $counts[$table] = \DB::table($table)->count();
        }
        return $counts;
    }
}
