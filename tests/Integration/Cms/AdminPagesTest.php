<?php

namespace Tests\Integration\Cms;

use App\Models\Organisation;
use App\Models\Page;
use Tests\Integration\Concerns\CreatesCmsFixtures;
use Tests\Integration\Concerns\CreatesEventFixtures;
use Tests\Integration\IntegrationTestCase;

/**
 * The pages index of the admin: organisation scoping, locale badges and the
 * organisation's home page ("Startpagina").
 */
class AdminPagesTest extends IntegrationTestCase
{
    use CreatesEventFixtures;
    use CreatesCmsFixtures;

    public function testAnonymousUsersAreSentToTheLogin()
    {
        $this->get('/admin/pages')->assertRedirect('/login');
    }

    public function testNonAdminsAreSentAway()
    {
        $this->createOrganisation();
        $this->createUser(); // the first user ever created becomes admin (User::boot)
        $user = $this->createUser();

        $this->actingAs($user)->get('/admin/pages')->assertRedirect('/');
    }

    public function testIndexListsOnlyTheActiveOrganisationsPagesAsATree()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $about = $this->createPage($organisation, 'over-ons');
        $this->createPage($organisation, 'over-ons/in-de-pers');
        $this->createPage($other, 'geheim-van-de-buren');

        $response = $this->actingAs($admin)->get('/admin/pages');

        $response->assertStatus(200);
        $response->assertSee('Page over-ons (nl)');
        $response->assertSee('Page in-de-pers (nl)');
        $response->assertSee('/over-ons/in-de-pers');
        $response->assertDontSee('geheim-van-de-buren');
        $response->assertSee('/admin/pages/' . $about->id . '/edit/nl', false);

        // The child comes after its parent.
        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, 'Page in-de-pers (nl)'),
            strpos($content, 'Page over-ons (nl)')
        );
    }

    public function testIndexShowsLocaleBadges()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $page = $this->createPage($organisation, 'over-ons');
        $this->createPageTranslation($page, 'en', 'about-us', [], false);

        $response = $this->actingAs($admin)->get('/admin/pages');
        $response->assertStatus(200);

        // nl published, en draft, fr missing (links to "add translation").
        $response->assertSee('data-locale-badge="nl" data-state="published"', false);
        $response->assertSee('data-locale-badge="en" data-state="draft"', false);
        $response->assertSee('data-locale-badge="fr" data-state="missing"', false);
        $response->assertSee('/admin/pages/' . $page->id . '/translations/fr/create', false);
    }

    public function testSetHomePageMarksItAsStartpagina()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->createPage($organisation, 'welkom');

        $this->actingAs($admin)
            ->post('/admin/pages/' . $page->id . '/home')
            ->assertRedirect('/admin/pages');

        $this->assertSame($page->id, (int) $organisation->fresh()->home_page_id);

        $this->actingAs($admin)->get('/admin/pages')
            ->assertStatus(200)
            ->assertSee('Startpagina')
            ->assertSee('data-home-page="' . $page->id . '"', false);

        // And clear it again.
        $this->actingAs($admin)
            ->post('/admin/pages/' . $page->id . '/home', [ 'clear' => 1 ])
            ->assertRedirect('/admin/pages');

        $this->assertNull($organisation->fresh()->home_page_id);
    }

    public function testAnotherOrganisationsPageIsA404()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $foreign = $this->createPage($other, 'van-de-buren');

        $this->actingAs($admin)
            ->post('/admin/pages/' . $foreign->id . '/home')
            ->assertStatus(404);

        $this->assertNull($organisation->fresh()->home_page_id);
        $this->assertNull($other->fresh()->home_page_id);

        $this->actingAs($admin)
            ->delete('/admin/pages/' . $foreign->id)
            ->assertStatus(404);

        $this->assertNotNull(Page::find($foreign->id));
    }

    public function testAdminOfAnotherOrganisationOnlyIsA404()
    {
        // Site admin with an organisation of their own, but not of $organisation.
        $organisation = $this->createOrganisation();
        $mine = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($mine);
        $page = $this->createPage($organisation, 'over-ons');

        $this->actingAs($admin)->get('/admin/pages/' . $page->id . '/edit/nl')->assertStatus(404);
    }

    public function testTheSelectedHomePageCannotBeDeleted()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->createPage($organisation, 'welkom');

        $organisation->home_page_id = $page->id;
        $organisation->save();

        $this->actingAs($admin)
            ->delete('/admin/pages/' . $page->id)
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->assertNotNull(Page::find($page->id));
        $this->assertSame($page->id, (int) $organisation->fresh()->home_page_id);
    }

    public function testAPageWithChildrenCannotBeDeleted()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $parent = $this->createPage($organisation, 'over-ons');
        $this->createPage($organisation, 'over-ons/team');

        $this->actingAs($admin)
            ->delete('/admin/pages/' . $parent->id)
            ->assertSessionHasErrors();

        $this->assertNotNull(Page::find($parent->id));
    }

    public function testDeletingAPageFreesItsPath()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->createPage($organisation, 'weg');

        $this->actingAs($admin)
            ->delete('/admin/pages/' . $page->id)
            ->assertRedirect('/admin/pages');

        $this->assertNull(Page::find($page->id));
        $this->assertSame(0, \App\Models\PageTranslation::where('page_id', $page->id)->count());

        // The path is free again.
        $this->createPage($organisation, 'weg');
        $this->get('/weg')->assertStatus(200);
    }

    /**
     * The organisation edit form (charon-frontend, through the API
     * controller) has a writeable "Startpagina" field.
     */
    public function testOrganisationFormSetsTheHomePageOfTheSameOrganisationOnly()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->createPage($organisation, 'welkom');
        $foreign = $this->createPage($other, 'elders');

        $field = function ($value, $type = 'string') {
            return [ 'type' => $type, 'multiple' => 0, 'input' => [ [ 'value' => $value ] ] ];
        };

        $this->actingAs($admin)->get('/admin/organisations/' . $organisation->id . '/edit')
            ->assertStatus(200)
            ->assertSee('Startpagina');

        $this->actingAs($admin)
            ->put('/admin/organisations/' . $organisation->id, [
                'fields' => [
                    'name' => $field($organisation->name),
                    'home_page_id' => $field($foreign->id, 'number'),
                ],
            ])
            ->assertStatus(302);

        $this->assertNull($organisation->fresh()->home_page_id);

        $this->actingAs($admin)
            ->put('/admin/organisations/' . $organisation->id, [
                'fields' => [
                    'name' => $field($organisation->name),
                    'home_page_id' => $field($page->id, 'number'),
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHas('message', 'Saved.');

        $this->assertSame($page->id, (int) $organisation->fresh()->home_page_id);
    }
}
