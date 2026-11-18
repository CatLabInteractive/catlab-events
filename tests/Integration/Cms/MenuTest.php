<?php

namespace Tests\Integration\Cms;

use Tests\Integration\Concerns\CreatesCmsFixtures;
use Tests\Integration\Concerns\CreatesEventFixtures;
use Tests\Integration\IntegrationTestCase;

/**
 * Pages marked "toon in menu" appear in the site header: top-level,
 * published in the locale, by sort_order, never the home page.
 */
class MenuTest extends IntegrationTestCase
{
    use CreatesEventFixtures;
    use CreatesCmsFixtures;

    public function testMenuPagesAppearInTheHeaderInTheirOrder()
    {
        $organisation = $this->createOrganisation();

        $second = $this->createPage($organisation, 'over-ons');
        $second->show_in_menu = true;
        $second->sort_order = 2;
        $second->save();

        $first = $this->createPage($organisation, 'quizzen-op-maat');
        $first->show_in_menu = true;
        $first->sort_order = 1;
        $first->save();

        $child = $this->createPage($organisation, 'over-ons/team');
        $child->show_in_menu = true;
        $child->save();

        $hidden = $this->createPage($organisation, 'verborgen');

        $draft = $this->createPage($organisation, 'concept', [], 'nl', false);
        $draft->show_in_menu = true;
        $draft->save();

        $response = $this->get('/over-ons');
        $response->assertStatus(200);
        $response->assertSeeInOrder([
            'href="http://localhost/quizzen-op-maat"', '>Page quizzen-op-maat (nl)</a>',
            'href="http://localhost/over-ons"', 'aria-current="page"', '>Page over-ons (nl)</a>',
        ], false);
        $response->assertDontSee('href="http://localhost/over-ons/team"', false);
        $response->assertDontSee('href="http://localhost/verborgen"', false);
        $response->assertDontSee('href="http://localhost/concept"', false);
    }

    public function testTheHomePageIsNotInTheMenu()
    {
        $organisation = $this->createOrganisation();
        $this->createPage($organisation, 'over-ons');
        $home = $this->createPage($organisation, 'start');
        $home->show_in_menu = true;
        $home->save();

        $this->get('/over-ons')->assertSee('href="http://localhost/start"', false);

        $organisation->home_page_id = $home->id;
        $organisation->save();

        $this->get('/over-ons')->assertDontSee('>Page start (nl)</a>', false);
    }
}
