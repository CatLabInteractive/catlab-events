<?php

namespace Tests\Integration\Cms\Api;

use App\Models\Organisation;
use App\Models\Page;
use App\Models\PageTranslation;
use Tests\Integration\Concerns\CreatesCmsFixtures;
use Tests\Integration\Concerns\CreatesEventFixtures;
use Tests\Integration\IntegrationTestCase;

/**
 * /api/v1/organisations/{organisation}/pages and /api/v1/pages/{page}, and
 * the organisation's home_page_id.
 */
class PagesApiTest extends IntegrationTestCase
{
    use CreatesEventFixtures;
    use CreatesCmsFixtures;

    public function testAnonymousRequestsAreRefused()
    {
        $organisation = $this->createOrganisation();

        $response = $this->getJson('/api/v1/organisations/' . $organisation->id . '/pages');
        $this->assertContains($response->getStatusCode(), [ 401, 302 ]);
    }

    public function testAdminCreatesAPage()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $response = $this->actingAs($admin)->postJson('/api/v1/organisations/' . $organisation->id . '/pages', [
            'sort_order' => 3,
            'show_in_menu' => true,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('sort_order', 3);
        $response->assertJsonPath('show_in_menu', true);

        $page = Page::findOrFail($response->json('id'));
        $this->assertSame($organisation->id, (int) $page->organisation_id);
        $this->assertNull($page->parent_id);
    }

    public function testIndexListsOnlyTheOrganisationsPagesWithTheirTranslations()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $mine = $this->createPage($organisation, 'over-ons');
        $this->createPage($other, 'buren');

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/organisations/' . $organisation->id . '/pages');

        $response->assertStatus(200);
        $this->assertSame([ $mine->id ], array_column($response->json('items'), 'id'));
        $response->assertJsonPath('items.0.translations.items.0.path', 'over-ons');
        $response->assertJsonPath('items.0.translations.items.0.locale', 'nl');
        $response->assertJsonPath('items.0.translations.items.0.is_published', true);
    }

    public function testOtherOrganisationsAndNonAdminsAreRefused()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $this->createOrganisationAdmin($organisation);
        $outsider = $this->createOrganisationAdmin($other);

        $page = $this->createPage($organisation, 'over-ons');
        $base = '/api/v1/organisations/' . $organisation->id . '/pages';

        $this->actingAs($outsider)->getJson($base)->assertStatus(403);
        $this->actingAs($outsider)->postJson($base, [ 'sort_order' => 1 ])->assertStatus(403);
        $this->actingAs($outsider)->getJson('/api/v1/pages/' . $page->id)->assertStatus(403);
        $this->actingAs($outsider)->patchJson('/api/v1/pages/' . $page->id, [ 'sort_order' => 9 ])->assertStatus(403);
        $this->actingAs($outsider)->deleteJson('/api/v1/pages/' . $page->id)->assertStatus(403);

        $this->assertSame(1, Page::count());
        $this->assertSame(0, (int) $page->fresh()->sort_order);

        $this->actingAs($outsider)->getJson('/api/v1/pages/999999')->assertStatus(404);
    }

    public function testParentMustBeAPageOfTheSameOrganisation()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $foreign = $this->createPage($other, 'buren');

        $response = $this->actingAs($admin)->postJson('/api/v1/organisations/' . $organisation->id . '/pages', [
            'parent_id' => $foreign->id,
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('parent_id', $response->json('error.issues'));
        $this->assertSame(0, Page::forOrganisation($organisation)->count());
    }

    public function testMovingAPageRebuildsItsPaths()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $about = $this->createPage($organisation, 'over-ons');
        $press = $this->createPage($organisation, 'pers');

        $this->actingAs($admin)
            ->patchJson('/api/v1/pages/' . $press->id, [ 'parent_id' => $about->id ])
            ->assertStatus(200)
            ->assertJsonPath('parent_id', $about->id);

        $this->assertSame('over-ons/pers', $press->translation('nl')->fresh()->path);

        // A page cannot move under itself.
        $this->actingAs($admin)
            ->patchJson('/api/v1/pages/' . $about->id, [ 'parent_id' => $press->id ])
            ->assertStatus(422);
    }

    public function testDeletePage()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $page = $this->createPage($organisation, 'over-ons');

        $this->actingAs($admin)->deleteJson('/api/v1/pages/' . $page->id)->assertStatus(200);

        $this->assertNull(Page::find($page->id));
        $this->assertSame(0, PageTranslation::where('page_id', '=', $page->id)->count());
    }

    public function testTheHomePageAndPagesWithChildrenCannotBeDeleted()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $home = $this->createPage($organisation, 'welkom');
        $parent = $this->createPage($organisation, 'over-ons');
        $this->createPage($organisation, 'over-ons/pers');

        $organisation->home_page_id = $home->id;
        $organisation->save();

        $this->actingAs($admin)->deleteJson('/api/v1/pages/' . $home->id)->assertStatus(422);
        $this->actingAs($admin)->deleteJson('/api/v1/pages/' . $parent->id)->assertStatus(422);

        $this->assertNotNull(Page::find($home->id));
        $this->assertNotNull(Page::find($parent->id));
    }

    public function testHomePageIdMustBeAPageOfTheSameOrganisation()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $mine = $this->createPage($organisation, 'welkom');
        $foreign = $this->createPage($other, 'buren');

        $response = $this->actingAs($admin)
            ->patchJson('/api/v1/organisations/' . $organisation->id, [ 'home_page_id' => $foreign->id ]);
        $this->assertGreaterThanOrEqual(400, $response->getStatusCode());
        $this->assertLessThan(500, $response->getStatusCode());
        $this->assertNull(Organisation::findOrFail($organisation->id)->home_page_id);

        $this->actingAs($admin)
            ->patchJson('/api/v1/organisations/' . $organisation->id, [ 'home_page_id' => $mine->id ])
            ->assertStatus(200)
            ->assertJsonPath('home_page_id', $mine->id);
        $this->assertSame($mine->id, (int) Organisation::findOrFail($organisation->id)->home_page_id);
    }

    public function testDescriptionListsTheCmsPaths()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $response = $this->actingAs($admin)->get('/api/v1/description.json');
        $response->assertStatus(200);

        $paths = array_keys($response->json('paths'));
        foreach ([
            '/api/v1/organisations/{organisation}/pages.{format}',
            '/api/v1/pages/{page}.{format}',
            '/api/v1/pages/{page}/translations.{format}',
            '/api/v1/pageTranslations/{pageTranslation}.{format}',
            '/api/v1/organisations/{organisation}/assets.{format}',
        ] as $path) {
            $this->assertContains($path, $paths);
        }
    }
}
