<?php

namespace Tests\Integration\Cms;

use App\Http\Controllers\SeriesController;
use App\Models\Organisation;
use App\Models\Page;
use App\Models\Series;
use Illuminate\Http\Request;
use Tests\Integration\Concerns\CreatesCmsFixtures;
use Tests\Integration\Concerns\CreatesEventFixtures;
use Tests\Integration\IntegrationTestCase;

/**
 * organisations.home_page_id picks the CMS page shown at / (and /en, /fr).
 * Without it, / keeps the existing EventController@index behaviour.
 */
class HomePageTest extends IntegrationTestCase
{
    use CreatesEventFixtures;
    use CreatesCmsFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        // EventController@index hands over to SeriesController@view for the
        // organisation's active series. Stand in for it so these tests do not
        // depend on the series page (its "finished events" query needs
        // MySQL 8's functional dependency detection).
        $this->app->instance(SeriesController::class, new class extends SeriesController {
            public function view(Request $request, $seriesId)
            {
                return response('Existing series page ' . $seriesId);
            }
        });
    }

    private function createSeries(Organisation $organisation): Series
    {
        $series = new Series();
        $series->organisation()->associate($organisation);
        $series->name = 'Test series';
        $series->slug = uniqid('series-');
        $series->active = 1;
        $series->save();

        return $series;
    }

    private function createHomePage(Organisation $organisation, bool $published = true): Page
    {
        $page = $this->createPage($organisation, 'welkom', [
            [ 'id' => 'a1b2c3', 'type' => 'hero', 'data' => [ 'title' => 'Welkom bij de quiz', 'align' => 'center' ] ],
        ], 'nl', $published);

        $organisation->home_page_id = $page->id;
        $organisation->save();

        return $page;
    }

    public function testRootRendersTheSelectedHomePage()
    {
        $organisation = $this->createOrganisation();
        $this->createSeries($organisation);
        $this->createHomePage($organisation);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Welkom bij de quiz');
        $response->assertSee('<link rel="canonical" href="http://localhost" />', false);
        $response->assertDontSee('Existing series page');

        // /events is the calendar-ish index, not the home page.
        $this->get('/events')->assertSee('Existing series page');
    }

    public function testWithoutAHomePageTheExistingHomepageStays()
    {
        $organisation = $this->createOrganisation();
        $series = $this->createSeries($organisation);
        $this->createPage($organisation, 'welkom');

        $this->get('/')->assertStatus(200)->assertSee('Existing series page ' . $series->id);
        $this->get('/en')->assertStatus(404);
        $this->get('/fr')->assertStatus(404);
    }

    public function testAnUnpublishedHomePageFallsBackToTheExistingHomepage()
    {
        $organisation = $this->createOrganisation();
        $series = $this->createSeries($organisation);
        $this->createHomePage($organisation, false);

        $this->get('/')->assertSee('Existing series page ' . $series->id);
    }

    public function testLocaleRootsRenderTheHomeTranslationOr404()
    {
        $organisation = $this->createOrganisation();
        $this->createSeries($organisation);
        $page = $this->createHomePage($organisation);
        $this->createPageTranslation($page, 'en', 'welcome', [
            [ 'id' => 'a1b2c3', 'type' => 'hero', 'data' => [ 'title' => 'Welcome to the quiz', 'align' => 'center' ] ],
        ]);

        $response = $this->get('/en');
        $response->assertStatus(200);
        $response->assertSee('Welcome to the quiz');
        $response->assertSee('<html lang="en">', false);
        $response->assertSee('<link rel="canonical" href="http://localhost/en" />', false);
        $response->assertSee('<link rel="alternate" hreflang="nl" href="http://localhost" />', false);

        $this->get('/en/')->assertStatus(200);
        $this->get('/fr')->assertStatus(404);
    }

    public function testTheHomePagesOwnPathRedirectsToTheLocaleRoot()
    {
        $organisation = $this->createOrganisation();
        $page = $this->createHomePage($organisation);
        $this->createPageTranslation($page, 'en', 'welcome');

        $this->get('/welkom?x=1')->assertStatus(301)->assertRedirect('http://localhost?x=1');
        $this->get('/en/welcome')->assertStatus(301)->assertRedirect('http://localhost/en');
    }

    public function testOtherOrganisationsKeepTheirHomepage()
    {
        $a = $this->createOrganisation();
        $b = $this->createOrganisation();
        $this->createOrganisationDomain($a, 'a.test');
        $this->createOrganisationDomain($b, 'b.test');
        $this->createHomePage($a);
        $seriesB = $this->createSeries($b);

        $this->actAsHost('a.test');
        $this->get('http://a.test/')->assertSee('Welkom bij de quiz');

        $this->actAsHost('b.test');
        $this->get('http://b.test/')
            ->assertSee('Existing series page ' . $seriesB->id)
            ->assertDontSee('Welkom bij de quiz');
    }

    public function testDeletingTheHomePageClearsTheSelection()
    {
        $organisation = $this->createOrganisation();
        $series = $this->createSeries($organisation);
        $page = $this->createHomePage($organisation);

        $page->delete();

        $this->assertNull($organisation->fresh()->home_page_id);
        $this->get('/')->assertSee('Existing series page ' . $series->id);
    }

    public function testAHomePageOfAnotherOrganisationIsIgnored()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $series = $this->createSeries($organisation);

        $foreign = $this->createPage($other, 'welkom');
        $organisation->home_page_id = $foreign->id;
        $organisation->save();

        $this->get('/')->assertSee('Existing series page ' . $series->id);
    }
}
