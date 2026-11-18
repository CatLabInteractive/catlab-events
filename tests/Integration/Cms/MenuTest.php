<?php

namespace Tests\Integration\Cms;

use Tests\Integration\Concerns\CreatesCmsFixtures;
use Tests\Integration\Concerns\CreatesEventFixtures;
use Tests\Integration\IntegrationTestCase;

/**
 * Pages marked "toon in menu" appear in the site header: published in the
 * locale, by sort_order, never the home page; subpages in a dropdown under
 * their page.
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
        $response->assertDontSee('href="http://localhost/verborgen"', false);
        $response->assertDontSee('href="http://localhost/concept"', false);
    }

    public function testSubpagesInTheMenuOpenUnderTheirPage()
    {
        $organisation = $this->createOrganisation();

        $parent = $this->createPage($organisation, 'over-ons');
        $parent->show_in_menu = true;
        $parent->save();

        $second = $this->createPage($organisation, 'over-ons/partners');
        $second->show_in_menu = true;
        $second->sort_order = 2;
        $second->save();

        $first = $this->createPage($organisation, 'over-ons/pers');
        $first->show_in_menu = true;
        $first->sort_order = 1;
        $first->save();

        $this->createPage($organisation, 'over-ons/team');

        $response = $this->get('/over-ons/pers');
        $response->assertStatus(200);
        $response->assertSeeInOrder([
            'kv-nav-group kv-nav-pages',
            'href="http://localhost/over-ons"', '>Page over-ons (nl)</a>',
            'class="kv-nav-toggle"',
            'class="kv-nav-sub"',
            'href="http://localhost/over-ons/pers"', 'aria-current="page"',
            'href="http://localhost/over-ons/partners"',
        ], false);
        $response->assertDontSee('href="http://localhost/over-ons/team"', false);
    }

    public function testFooterPagesAppearInTheFooter()
    {
        $organisation = $this->createOrganisation();
        $this->createPage($organisation, 'over-ons');

        $contact = $this->createPage($organisation, 'over-ons/contact');
        $contact->show_in_footer = true;
        $contact->sort_order = 2;
        $contact->save();

        $privacy = $this->createPage($organisation, 'cookiebeleid');
        $privacy->show_in_footer = true;
        $privacy->sort_order = 1;
        $privacy->save();

        $draft = $this->createPage($organisation, 'concept', [], 'nl', false);
        $draft->show_in_footer = true;
        $draft->save();

        $response = $this->get('/over-ons');
        $response->assertStatus(200);
        $response->assertSeeInOrder([
            'id="footer"',
            'href="http://localhost/cookiebeleid"', '>Page cookiebeleid (nl)</a>',
            'href="http://localhost/over-ons/contact"',
        ], false);
        $response->assertDontSee('href="http://localhost/concept"', false);
        // Footer pages are not menu pages.
        $this->assertStringNotContainsString('cookiebeleid', explode('id="footer"', $response->getContent())[0]);
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
