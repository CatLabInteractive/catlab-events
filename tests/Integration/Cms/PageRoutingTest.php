<?php

namespace Tests\Integration\Cms;

use App\Models\CmsRedirect;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\Integration\Concerns\CreatesCmsFixtures;
use Tests\Integration\Concerns\CreatesEventFixtures;
use Tests\Integration\IntegrationTestCase;

class PageRoutingTest extends IntegrationTestCase
{
    use CreatesEventFixtures;
    use CreatesCmsFixtures;

    private function richText(string $html): array
    {
        return [ [ 'id' => 'a1b2c3', 'type' => 'rich_text', 'data' => [ 'html' => $html ] ] ];
    }

    private function adminOf(Organisation $organisation): User
    {
        $admin = $this->createUser();
        $organisation->users()->attach($admin, [ 'role' => 10 ]);

        return $admin;
    }

    private function actionFor(string $uri): string
    {
        return app('router')->getRoutes()->match(Request::create($uri))->getActionName();
    }

    public function testDutchPageRendersAtTheRoot()
    {
        $organisation = $this->createOrganisation();
        $this->createPage($organisation, 'over-ons', $this->richText('<p>Wij maken quizzen.</p>'));

        $response = $this->get('/over-ons');

        $response->assertStatus(200);
        $response->assertSee('<html lang="nl">', false);
        $response->assertSee('<title>Page over-ons (nl) – Test organisation</title>', false);
        $response->assertSee('<p>Wij maken quizzen.</p>', false);
        $response->assertSee('<link rel="canonical" href="http://localhost/over-ons" />', false);
        $response->assertDontSee('noindex');
    }

    public function testChildPageRendersAtItsFullPath()
    {
        $organisation = $this->createOrganisation();
        $this->createPage($organisation, 'over-ons');
        $this->createPage($organisation, 'over-ons/in-de-pers', $this->richText('<p>Pers</p>'));

        $this->get('/over-ons/in-de-pers')->assertStatus(200)->assertSee('<p>Pers</p>', false);
        $this->get('/in-de-pers')->assertStatus(404);
    }

    public function testTranslationsLiveUnderTheirLocalePrefix()
    {
        $organisation = $this->createOrganisation();
        $page = $this->createPage($organisation, 'over-ons', $this->richText('<p>Nederlands</p>'));
        $this->createPageTranslation($page, 'en', 'about-us', $this->richText('<p>English</p>'));

        $response = $this->get('/en/about-us');
        $response->assertStatus(200);
        $response->assertSee('<html lang="en">', false);
        $response->assertSee('<p>English</p>', false);
        $response->assertDontSee('Nederlands');

        // A missing translation is a 404, never a Dutch fallback.
        $this->get('/fr/about-us')->assertStatus(404);
        $this->get('/fr/over-ons')->assertStatus(404);
        $this->get('/en/over-ons')->assertStatus(404);
        $this->get('/about-us')->assertStatus(404);

        // Not a configured locale: just an (unknown) Dutch path.
        $this->get('/de/about-us')->assertStatus(404);
    }

    public function testAlternatesListOnlyPublishedTranslations()
    {
        $organisation = $this->createOrganisation();
        $page = $this->createPage($organisation, 'over-ons');
        $this->createPageTranslation($page, 'en', 'about-us');
        $this->createPageTranslation($page, 'fr', 'a-propos', [], false);

        $response = $this->get('/en/about-us');
        $response->assertSee('<link rel="canonical" href="http://localhost/en/about-us" />', false);
        $response->assertSee('<link rel="alternate" hreflang="nl" href="http://localhost/over-ons" />', false);
        $response->assertSee('<link rel="alternate" hreflang="en" href="http://localhost/en/about-us" />', false);
        $response->assertSee('<link rel="alternate" hreflang="x-default" href="http://localhost/over-ons" />', false);
        $response->assertDontSee('a-propos');
    }

    public function testUnpublishedPagesAreOnlyVisibleAsPreviewToOrganisationAdmins()
    {
        $organisation = $this->createOrganisation();
        $this->createPage($organisation, 'concept', $this->richText('<p>Nog geheim</p>'), 'nl', false);

        $this->get('/concept')->assertStatus(404);
        $this->get('/concept?preview=1')->assertStatus(404);

        $this->actingAs($this->createUser())->get('/concept?preview=1')->assertStatus(404);

        $otherAdmin = $this->adminOf($this->createOrganisation());
        $this->actingAs($otherAdmin)->get('/concept?preview=1')->assertStatus(404);

        $admin = $this->adminOf($organisation);
        $this->actingAs($admin)->get('/concept')->assertStatus(404);

        $response = $this->actingAs($admin)->get('/concept?preview=1');
        $response->assertStatus(200);
        $response->assertSee('<p>Nog geheim</p>', false);
        $response->assertSee('<meta name="robots" content="noindex">', false);
    }

    public function testExistingRoutesWinOverPagesWithTheSameSlug()
    {
        $organisation = $this->createOrganisation();
        foreach ([ 'events', 'calendar', 'archive', 's', 'press', 'competitions' ] as $slug) {
            $this->createPage($organisation, $slug, $this->richText('<p>Shadowed ' . $slug . '</p>'));
        }

        $this->assertSame('App\Http\Controllers\EventController@index', $this->actionFor('/events'));
        $this->assertSame('App\Http\Controllers\EventController@calendar', $this->actionFor('/calendar'));
        $this->assertSame('App\Http\Controllers\EventController@archive', $this->actionFor('/archive'));
        $this->assertSame('App\Http\Controllers\SeriesController@view', $this->actionFor('/s/1/x'));
        $this->assertSame('App\Http\Controllers\HomeController@press', $this->actionFor('/press'));
        $this->assertSame('App\Http\Controllers\PageController@show', $this->actionFor('/over-ons'));
        $this->assertSame('App\Http\Controllers\PageController@show', $this->actionFor('/en/about-us'));
        $this->assertSame('App\Http\Controllers\PageController@home', $this->actionFor('/en'));

        $event = $this->createEvent($organisation);
        $this->createTicketCategory($event, 10.0);
        $this->get('/events/' . $event->id)
            ->assertStatus(200)
            ->assertSee($event->name)
            ->assertDontSee('Shadowed');
    }

    public function testTrailingSlashServesThePageWithACanonicalWithoutIt()
    {
        $organisation = $this->createOrganisation();
        $this->createPage($organisation, 'over-ons');

        $this->get('/over-ons/')
            ->assertStatus(200)
            ->assertSee('<link rel="canonical" href="http://localhost/over-ons" />', false);
    }

    public function testPagesAreScopedToTheOrganisationOfTheDomain()
    {
        $a = $this->createOrganisation();
        $b = $this->createOrganisation();
        $this->createOrganisationDomain($a, 'a.test');
        $this->createOrganisationDomain($b, 'b.test');
        $this->createPage($a, 'over-ons', $this->richText('<p>Organisatie A</p>'));

        $this->actAsHost('a.test');
        $this->get('http://a.test/over-ons')->assertStatus(200)->assertSee('Organisatie A');

        $this->actAsHost('b.test');
        $this->get('http://b.test/over-ons')->assertStatus(404);
    }

    public function testRedirectRowsAnswerMissingPaths()
    {
        $organisation = $this->createOrganisation();
        $this->createPage($organisation, 'over-ons');

        foreach ([
            'oude-pagina' => [ '/over-ons', 301 ],
            'wp-content/uploads/2019/03/foto.jpg' => [ 'https://assets.example.test/assets/abc', 301 ],
            'tijdelijk' => [ '/over-ons?x=1', 302 ],
        ] as $from => [ $to, $status ]) {
            $redirect = new CmsRedirect();
            $redirect->organisation()->associate($organisation);
            $redirect->from_path = $from;
            $redirect->to_url = $to;
            $redirect->status_code = $status;
            $redirect->save();
        }

        // Through the page route (valid slug characters) ...
        $this->get('/oude-pagina/?utm_source=x')
            ->assertStatus(301)
            ->assertRedirect('http://localhost/over-ons?utm_source=x');

        // ... and through the fallback route (dots, uppercase).
        $this->get('/wp-content/uploads/2019/03/foto.jpg')
            ->assertStatus(301)
            ->assertRedirect('https://assets.example.test/assets/abc');

        $this->get('/tijdelijk?y=2')
            ->assertStatus(302)
            ->assertRedirect('http://localhost/over-ons?x=1&y=2');

        $this->get('/bestaat-niet')->assertStatus(404);
        $this->get('/Bestaat.Niet')->assertStatus(404);

        // Another organisation's redirects do not apply.
        $other = $this->createOrganisation();
        $this->createOrganisationDomain($other, 'other.test');
        $this->actAsHost('other.test');
        $this->get('http://other.test/oude-pagina')->assertStatus(404);
    }
}
