<?php

namespace Tests\Integration\Cms;

use App\Http\Controllers\SitemapController;
use App\Models\Organisation;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Support\Facades\Cache;
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

    // ------------------------------------------------------------------
    // The page + translation form
    // ------------------------------------------------------------------

    private function formInput(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'over-ons',
            'title' => 'Over ons',
            'meta_title' => '',
            'meta_description' => 'Wie zijn we?',
            'is_published' => '1',
            'parent_id' => '',
            'sort_order' => '0',
            'blocks' => [
                [ 'id' => 'a1b2c3', 'type' => 'hero', 'data' => [
                    'title' => 'Welkom', 'subtitle' => '', 'image_id' => '', 'align' => 'center',
                    'buttons' => [ [ 'label' => 'Kalender', 'url' => '/calendar', 'style' => 'primary' ] ],
                ] ],
                [ 'id' => 'd4e5f6', 'type' => 'rich_text', 'data' => [
                    'html' => '<p onclick="steal()">Wij zijn <strong>de quizfabriek</strong>.</p><script>alert(1)</script>',
                ] ],
            ],
        ], $overrides);
    }

    public function testCreatePageWithDutchTranslationAndTwoBlocks()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $this->actingAs($admin)->get('/admin/pages/create')->assertStatus(200);

        $response = $this->actingAs($admin)->post('/admin/pages', $this->formInput());
        $response->assertSessionHasNoErrors();

        $translation = PageTranslation::where('organisation_id', $organisation->id)->where('path', 'over-ons')->firstOrFail();
        $response->assertRedirect('/admin/pages/' . $translation->page_id . '/edit/nl');

        $this->assertSame('nl', $translation->locale);
        $this->assertTrue($translation->is_published);
        $this->assertCount(2, $translation->blocks);
        $this->assertSame('hero', $translation->blocks[0]['type']);
        $this->assertSame('Kalender', $translation->blocks[0]['data']['buttons'][0]['label']);
        $this->assertNull($translation->blocks[0]['data']['image_id']);

        // Rich text is stored sanitised.
        $html = $translation->blocks[1]['data']['html'];
        $this->assertStringContainsString('<strong>de quizfabriek</strong>', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('script', $html);

        $this->get('/over-ons')->assertStatus(200)->assertSee('Welkom');

        // The edit form renders the stored blocks.
        $this->actingAs($admin)->get('/admin/pages/' . $translation->page_id . '/edit/nl')
            ->assertStatus(200)
            ->assertSee('value="Welkom"', false)
            ->assertSee('name="blocks[1][data][html]"', false);
    }

    public function testAddEnglishTranslation()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->createPage($organisation, 'over-ons');

        $this->actingAs($admin)->get('/admin/pages/' . $page->id . '/edit/en')
            ->assertRedirect('/admin/pages/' . $page->id . '/translations/en/create');
        $this->actingAs($admin)->get('/admin/pages/' . $page->id . '/translations/en/create')
            ->assertStatus(200)
            ->assertSee('Kopieer uit');

        $this->actingAs($admin)
            ->put('/admin/pages/' . $page->id . '/edit/en', $this->formInput([ 'slug' => 'about-us', 'title' => 'About us' ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/pages/' . $page->id . '/edit/en');

        $en = $page->fresh()->translation('en');
        $this->assertSame('about-us', $en->path);
        $this->get('/en/about-us')->assertStatus(200)->assertSee('About us');
        $this->assertSame(2, $page->translations()->count());
    }

    public function testReservedTopLevelSlugIsRejected()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $this->actingAs($admin)
            ->post('/admin/pages', $this->formInput([ 'slug' => 'events' ]))
            ->assertSessionHasErrors('slug');

        $this->actingAs($admin)
            ->post('/admin/pages', $this->formInput([ 'slug' => '' ]))
            ->assertSessionHasErrors('slug');

        $this->actingAs($admin)
            ->post('/admin/pages', $this->formInput([ 'slug' => 'Over ons!' ]))
            ->assertSessionHasErrors('slug');

        $this->assertSame(0, Page::count());
    }

    public function testChildSlugsMayUseReservedWords()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $parent = $this->createPage($organisation, 'over-ons');

        $this->actingAs($admin)
            ->post('/admin/pages', $this->formInput([ 'slug' => 'events', 'parent_id' => (string) $parent->id ]))
            ->assertSessionHasNoErrors();

        $this->get('/over-ons/events')->assertStatus(200);
    }

    public function testDuplicatePathIsRejected()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $this->createPage($organisation, 'over-ons');

        $this->actingAs($admin)
            ->post('/admin/pages', $this->formInput())
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Page::count());

        // Same path in another organisation is fine.
        $other = $this->createOrganisation();
        $otherAdmin = $this->createOrganisationAdmin($other);
        $this->actingAs($otherAdmin)
            ->post('/admin/pages', $this->formInput())
            ->assertSessionHasNoErrors();
    }

    public function testInvalidBlockDataReRendersTheFormWithErrorsAndInput()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $input = $this->formInput();
        $input['blocks'][0]['data']['title'] = '';
        $input['blocks'][0]['data']['buttons'][0]['url'] = 'javascript:alert(1)';
        $input['blocks'][1]['data']['html'] = '<p>Deze tekst mag niet verloren gaan</p>';

        $this->actingAs($admin)
            ->from('/admin/pages/create')
            ->post('/admin/pages', $input)
            ->assertRedirect('/admin/pages/create')
            ->assertSessionHasErrors([ 'blocks.0.data.title', 'blocks.0.data.buttons.0.url' ]);

        $this->assertSame(0, Page::count());

        $this->actingAs($admin)->get('/admin/pages/create')
            ->assertStatus(200)
            ->assertSee('alert-danger', false)
            ->assertSee('is-invalid', false)
            ->assertSee('Deze tekst mag niet verloren gaan');
    }

    public function testImageOfAnotherOrganisationIsRejected()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $foreign = $this->createOrganisationAsset($other);
        $legacy = $this->createOrganisationAsset(null);
        $mine = $this->createOrganisationAsset($organisation);

        foreach ([ $foreign, $legacy ] as $asset) {
            $input = $this->formInput();
            $input['blocks'][0]['data']['image_id'] = (string) $asset->id;
            $this->actingAs($admin)->post('/admin/pages', $input)->assertSessionHasErrors('blocks.0.data.image_id');

            $this->actingAs($admin)
                ->post('/admin/pages', $this->formInput([ 'og_image_id' => (string) $asset->id ]))
                ->assertSessionHasErrors('og_image_id');
        }

        $input = $this->formInput([ 'og_image_id' => (string) $mine->id ]);
        $input['blocks'][0]['data']['image_id'] = (string) $mine->id;
        $this->actingAs($admin)->post('/admin/pages', $input)->assertSessionHasNoErrors();

        $translation = PageTranslation::firstOrFail();
        $this->assertSame($mine->id, $translation->blocks[0]['data']['image_id']);
        $this->assertSame($mine->id, (int) $translation->og_image_id);
    }

    public function testSlugRenameCascadesToChildPaths()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $parent = $this->createPage($organisation, 'over-ons');
        $child = $this->createPage($organisation, 'over-ons/in-de-pers');
        $this->createPage($organisation, 'over-ons/in-de-pers/archief');

        $this->actingAs($admin)
            ->put('/admin/pages/' . $parent->id . '/edit/nl', $this->formInput([ 'slug' => 'wie-zijn-we' ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('wie-zijn-we/in-de-pers', $child->fresh()->translation('nl')->path);
        $this->assertTrue(PageTranslation::where('path', 'wie-zijn-we/in-de-pers/archief')->exists());
        $this->get('/wie-zijn-we/in-de-pers')->assertStatus(200);
    }

    public function testMovingAPageUnderAnotherPageRebuildsItsPath()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $parent = $this->createPage($organisation, 'over-ons');
        $page = $this->createPage($organisation, 'team');

        $this->actingAs($admin)
            ->put('/admin/pages/' . $page->id . '/edit/nl', $this->formInput([ 'slug' => 'team', 'parent_id' => (string) $parent->id ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('over-ons/team', $page->fresh()->translation('nl')->path);

        // A page cannot hang under itself or its own descendants.
        $this->actingAs($admin)
            ->put('/admin/pages/' . $parent->id . '/edit/nl', $this->formInput([ 'parent_id' => (string) $page->id ]))
            ->assertSessionHasErrors('parent_id');

        // Nor under another organisation's page.
        $foreign = $this->createPage($this->createOrganisation(), 'elders');
        $this->actingAs($admin)
            ->put('/admin/pages/' . $page->id . '/edit/nl', $this->formInput([ 'slug' => 'team', 'parent_id' => (string) $foreign->id ]))
            ->assertSessionHasErrors('parent_id');
    }

    public function testCopyFromDutchClonesBlocksWithFreshIds()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->createPage($organisation, 'over-ons', [
            [ 'id' => 'aaaaaa', 'type' => 'rich_text', 'data' => [ 'html' => '<p>Hallo</p>' ] ],
            [ 'id' => 'bbbbbb', 'type' => 'cta', 'data' => [ 'title' => 'Bel ons', 'background' => 'dark', 'buttons' => [] ] ],
        ]);

        $this->actingAs($admin)
            ->post('/admin/pages/' . $page->id . '/translations/fr/copy', [ 'from' => 'nl' ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/pages/' . $page->id . '/edit/fr');

        $nl = $page->fresh()->translation('nl');
        $fr = $page->fresh()->translation('fr');

        $this->assertFalse($fr->is_published);
        $this->assertSame('over-ons', $fr->path);
        $this->assertCount(2, $fr->blocks);
        $this->assertSame($nl->blocks[0]['data'], $fr->blocks[0]['data']);
        $this->assertSame('Bel ons', $fr->blocks[1]['data']['title']);

        foreach ($fr->blocks as $index => $block) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{6}$/', $block['id']);
            $this->assertNotSame($nl->blocks[$index]['id'], $block['id']);
        }
    }

    public function testDeleteTranslation()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->createPage($organisation, 'over-ons');
        $this->createPageTranslation($page, 'en', 'about-us');

        $this->actingAs($admin)
            ->delete('/admin/pages/' . $page->id . '/translations/en')
            ->assertRedirect('/admin/pages');

        $this->assertNull($page->fresh()->translation('en'));
        $this->assertNotNull($page->fresh()->translation('nl'));
    }

    public function testSavingForgetsTheSitemapCache()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $key = SitemapController::cacheKey($organisation);

        Cache::put($key, [ 'stale' ], 3600);

        $this->actingAs($admin)->post('/admin/pages', $this->formInput())->assertSessionHasNoErrors();

        $this->assertFalse(Cache::has($key));
    }

    public function testSaveAndPreviewRedirectsToThePreview()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $this->actingAs($admin)
            ->post('/admin/pages', $this->formInput([ 'is_published' => '0', 'preview' => '1' ]))
            ->assertRedirect(url('/over-ons') . '?preview=1');
    }

    public function testEditFormRendersEveryBlockType()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->createPage($organisation, 'alles');

        $response = $this->actingAs($admin)->get('/admin/pages/' . $page->id . '/edit/nl');
        $response->assertStatus(200);

        foreach (array_keys(config('cms.blocks')) as $type) {
            $response->assertSee('data-block-type="' . $type . '"', false);
        }
    }
}
