<?php

namespace Tests\Integration;

use Carbon\Carbon;
use Tests\Integration\Concerns\CreatesCmsFixtures;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * An organisation can have several domains. The menu, footer, latest posts
 * and sitemap are cached per organisation, so a visit on one domain must not
 * leave that domain in the links that another domain gets from the cache
 * (bektoe.be links on www.quizfabriek.be, 2026-11-20).
 */
class HostIndependentCacheTest extends IntegrationTestCase
{
    use CreatesEventFixtures;
    use CreatesCmsFixtures;

    public function testCachedLinksUseTheHostOfTheCurrentRequest()
    {
        $organisation = $this->createOrganisation();
        $this->createOrganisationDomain($organisation, 'www.example.test');
        $this->createOrganisationDomain($organisation, 'other.example.test');

        $menu = $this->createPage($organisation, 'over-ons');
        $menu->show_in_menu = true;
        $menu->save();

        $footer = $this->createPage($organisation, 'cookiebeleid');
        $footer->show_in_footer = true;
        $footer->save();

        $this->createPost($organisation, 'verslag', Carbon::create(2019, 3, 1, 12));
        $this->createPage($organisation, 'start', [
            [ 'id' => 'a1b2c3', 'type' => 'latest_posts', 'data' => [ 'title' => 'Uit de blog', 'limit' => 2 ] ],
        ]);

        // The first visit fills the caches on the other domain.
        $this->actAsHost('other.example.test');
        $this->get('http://other.example.test/start')->assertStatus(200);
        $this->get('http://other.example.test/sitemap.xml')->assertStatus(200);

        $this->actAsHost('www.example.test');
        $page = $this->get('http://www.example.test/start');
        $page->assertStatus(200);
        $page->assertSee('href="http://www.example.test/over-ons"', false);
        $page->assertSee('href="http://www.example.test/cookiebeleid"', false);
        $page->assertSee('href="http://www.example.test/2019/03/01/verslag"', false);
        $page->assertDontSee('other.example.test', false);

        $sitemap = $this->get('http://www.example.test/sitemap.xml');
        $sitemap->assertSee('<loc>http://www.example.test/over-ons</loc>', false);
        $sitemap->assertDontSee('other.example.test', false);
    }
}
