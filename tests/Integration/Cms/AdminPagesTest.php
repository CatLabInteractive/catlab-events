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
 * The pages admin: Charon admin controllers (Admin\PageController for the
 * page, Admin\PageTranslationController for its content per language) on top
 * of the CMS api controllers. Forms post every field as fields[name].
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

        // Created child first, sorted last: the tree decides, not the id.
        $about = $this->createPage($organisation, 'over-ons');
        $contact = $this->createPage($organisation, 'contact');
        $this->createPage($organisation, 'over-ons/in-de-pers');
        $this->createPage($other, 'geheim-van-de-buren');

        $about->sort_order = 5;
        $about->save();

        $response = $this->actingAs($admin)->get('/admin/pages');

        $response->assertStatus(200);
        $response->assertSee('Page over-ons (nl)');
        $response->assertSee('— Page in-de-pers (nl)');
        $response->assertDontSee('geheim-van-de-buren');
        $response->assertSee('/admin/pages/' . $about->id . '/edit', false);
        $response->assertSee('Pagina toevoegen');

        // contact (0), over-ons (5), and its child right after it.
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'Page over-ons (nl)'), strpos($content, 'Page contact (nl)'));
        $this->assertLessThan(strpos($content, 'Page in-de-pers (nl)'), strpos($content, 'Page over-ons (nl)'));
    }

    public function testIndexLinksEveryTranslationToItsEditor()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $page = $this->createPage($organisation, 'over-ons');
        $en = $this->createPageTranslation($page, 'en', 'about-us', [], false);

        $response = $this->actingAs($admin)->get('/admin/pages');
        $response->assertStatus(200);

        // nl published, en a draft.
        $response->assertSee('Nederlands');
        $response->assertSee('Engels (concept)');
        $response->assertSee('/admin/pages/' . $page->id . '/translations/' . $en->id . '/edit', false);
    }

    public function testShowPageListsTheTranslationsOnATab()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->createPage($organisation, 'over-ons');
        $nl = $page->translation('nl');

        $this->actingAs($admin)->get('/admin/pages/' . $page->id)
            ->assertStatus(200)
            ->assertSee('Page over-ons (nl)')
            ->assertSee('Talen');

        $this->actingAs($admin)->get('/admin/pages/' . $page->id . '?tab=translations')
            ->assertStatus(200)
            ->assertSee('/admin/pages/' . $page->id . '/translations/' . $nl->id . '/edit', false)
            ->assertSee('Vertaling toevoegen');

        // A translation has no show page of its own.
        $this->actingAs($admin)->get('/admin/pages/' . $page->id . '/translations/' . $nl->id)
            ->assertRedirect('/admin/pages/' . $page->id . '/translations/' . $nl->id . '/edit');
    }

    public function testStartpaginaCheckboxSetsAndClearsTheHomePage()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->createPage($organisation, 'welkom');
        $other = $this->createPage($organisation, 'over-ons');

        $this->actingAs($admin)
            ->put('/admin/pages/' . $page->id, [ 'fields' => $this->pageFields([ 'is_home' => '1' ]) ])
            ->assertRedirect('/admin/pages/' . $page->id);

        $this->assertSame($page->id, (int) $organisation->fresh()->home_page_id);

        // Saving another page (box unchecked) leaves the home page alone.
        $this->actingAs($admin)
            ->put('/admin/pages/' . $other->id, [ 'fields' => $this->pageFields() ])
            ->assertRedirect('/admin/pages/' . $other->id);

        $this->assertSame($page->id, (int) $organisation->fresh()->home_page_id);

        $form = $this->actingAs($admin)->get('/admin/pages/' . $page->id . '/edit')->assertStatus(200)->getContent();
        $this->assertMatchesRegularExpression('/name="fields\[is_home\]"[^>]*checked/', $form);

        // And clear it again.
        $this->actingAs($admin)
            ->put('/admin/pages/' . $page->id, [ 'fields' => $this->pageFields() ])
            ->assertRedirect('/admin/pages/' . $page->id);

        $this->assertNull($organisation->fresh()->home_page_id);
    }

    public function testAnotherOrganisationsPageIsForbidden()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $foreign = $this->createPage($other, 'van-de-buren');
        $translation = $foreign->translation('nl');

        $this->actingAs($admin)->get('/admin/pages/' . $foreign->id)->assertStatus(403);
        $this->actingAs($admin)->get('/admin/pages/' . $foreign->id . '/edit')->assertStatus(403);
        $this->actingAs($admin)->get('/admin/pages/' . $foreign->id . '/translations/' . $translation->id . '/edit')->assertStatus(403);
        $this->actingAs($admin)->get('/admin/pages/' . $foreign->id . '/translations/create')->assertStatus(403);

        $this->actingAs($admin)
            ->put('/admin/pages/' . $foreign->id, [ 'fields' => $this->pageFields([ 'is_home' => '1' ]) ])
            ->assertStatus(403);

        $this->assertNull($organisation->fresh()->home_page_id);
        $this->assertNull($other->fresh()->home_page_id);

        $this->actingAs($admin)
            ->put('/admin/pages/' . $foreign->id . '/translations/' . $translation->id, [ 'fields' => $this->translationFields([ 'title' => 'Gekaapt' ]) ])
            ->assertStatus(403);
        $this->assertNotSame('Gekaapt', $translation->fresh()->title);

        $this->actingAs($admin)
            ->delete('/admin/pages/' . $foreign->id)
            ->assertStatus(403);

        $this->assertNotNull(Page::find($foreign->id));
    }

    public function testTheSelectedHomePageCannotBeDeleted()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->createPage($organisation, 'welkom');

        $organisation->home_page_id = $page->id;
        $organisation->save();

        $response = $this->actingAs($admin)
            ->delete('/admin/pages/' . $page->id)
            ->assertRedirect();

        $this->assertStringContainsString('startpagina', (string) session('message'));
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
            ->assertRedirect();

        $this->assertStringContainsString('onderliggende pagina', (string) session('message'));
        $this->assertNotNull(Page::find($parent->id));
    }

    public function testDeletingAPageFreesItsPath()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->createPage($organisation, 'weg');

        $this->actingAs($admin)->get('/admin/pages/' . $page->id . '/delete')->assertStatus(200);

        $this->actingAs($admin)
            ->delete('/admin/pages/' . $page->id)
            ->assertRedirect('/admin/pages')
            ->assertSessionHas('message', 'Verwijderd.');

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

        // The charon-frontend form posts every field as fields[name].
        $field = function ($value) {
            return $value;
        };

        $this->actingAs($admin)->get('/admin/organisations/' . $organisation->id . '/edit')
            ->assertStatus(200)
            ->assertSee('Startpagina');

        $this->actingAs($admin)
            ->put('/admin/organisations/' . $organisation->id, [
                'fields' => [
                    'name' => $field($organisation->name),
                    // Fields left out of the form are saved as empty.
                    'private_events_count' => $field((int) $organisation->private_events_count),
                    'home_page_id' => $field($foreign->id),
                ],
            ])
            ->assertStatus(302);

        $this->assertNull($organisation->fresh()->home_page_id);

        $this->actingAs($admin)
            ->put('/admin/organisations/' . $organisation->id, [
                'fields' => [
                    'name' => $field($organisation->name),
                    // Fields left out of the form are saved as empty.
                    'private_events_count' => $field((int) $organisation->private_events_count),
                    'home_page_id' => $field($page->id),
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHas('message', 'Opgeslagen.');

        $this->assertSame($page->id, (int) $organisation->fresh()->home_page_id);
    }

    // ------------------------------------------------------------------
    // The page form and the translation form
    // ------------------------------------------------------------------

    /**
     * What the page form posts (fields[...]).
     */
    private function pageFields(array $overrides = []): array
    {
        return array_merge([
            'parent_id' => '',
            'sort_order' => '0',
            'link' => [ 'kind' => '', 'url' => '' ],
            'show_in_menu' => '0',
            'show_in_footer' => '0',
            'is_home' => '0',
        ], $overrides);
    }

    /**
     * What the translation form posts (fields[...]).
     */
    private function translationFields(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'over-ons',
            'title' => 'Over ons',
            'meta_title' => '',
            'meta_description' => 'Wie zijn we?',
            'is_published' => '1',
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

    /**
     * Post the translation form of a page for a new translation.
     */
    private function postTranslation($admin, Page $page, array $fields)
    {
        return $this->actingAs($admin)
            ->from('/admin/pages/' . $page->id . '/translations/create')
            ->post('/admin/pages/' . $page->id . '/translations', [ 'fields' => $fields ]);
    }

    /**
     * Post the edit form of the translation of a page in a locale.
     */
    private function putTranslation($admin, Page $page, string $locale, array $fields)
    {
        $translation = $page->fresh()->translation($locale);

        return $this->actingAs($admin)
            ->from('/admin/pages/' . $page->id . '/translations/' . $translation->id . '/edit')
            ->put('/admin/pages/' . $page->id . '/translations/' . $translation->id, [ 'fields' => $fields ]);
    }

    private function emptyPage($organisation, ?Page $parent = null): Page
    {
        $page = new Page();
        $page->organisation()->associate($organisation);
        if ($parent) {
            $page->parent()->associate($parent);
        }
        $page->save();

        return $page;
    }

    public function testCreatePageThenItsDutchTranslationWithTwoBlocks()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $this->actingAs($admin)->get('/admin/pages/create')
            ->assertStatus(200)
            ->assertSee('Bovenliggende pagina')
            ->assertSee('Menulink: de kalender');

        // The page itself, then on to its first translation.
        $response = $this->actingAs($admin)->post('/admin/pages', [ 'fields' => $this->pageFields([ 'show_in_menu' => '1' ]) ]);
        $page = Page::forOrganisation($organisation)->firstOrFail();
        $response->assertRedirect('/admin/pages/' . $page->id . '/translations/create');
        $this->assertTrue($page->show_in_menu);

        $this->actingAs($admin)->get('/admin/pages/' . $page->id . '/translations/create')
            ->assertStatus(200)
            ->assertSee('Nederlands')
            ->assertSee('Sectie toevoegen')
            ->assertDontSee('Kopieer uit');

        $response = $this->postTranslation($admin, $page, $this->translationFields([ 'locale' => 'nl' ]));

        $translation = PageTranslation::where('organisation_id', $organisation->id)->where('path', 'over-ons')->firstOrFail();
        $response->assertRedirect('/admin/pages/' . $page->id . '/translations/' . $translation->id . '/edit');
        $response->assertSessionHas('message', 'Vertaling toegevoegd.');

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

        // The edit form renders the stored blocks, and links to the page on the site.
        $this->actingAs($admin)->get('/admin/pages/' . $page->id . '/translations/' . $translation->id . '/edit')
            ->assertStatus(200)
            ->assertSee('value="Welkom"', false)
            ->assertSee('name="fields[blocks][1][data][html]"', false)
            ->assertSee(url('/over-ons') . '?preview=1', false);
    }

    /**
     * One editor screen, like the old hand-built one: a tab per language,
     * the page's own settings beside the content, saved together.
     */
    public function testTheEditorHasLanguageTabsAndThePageSettings()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->emptyPage($organisation);
        $this->postTranslation($admin, $page, $this->translationFields([ 'locale' => 'nl' ]));
        $this->postTranslation($admin, $page, $this->translationFields([ 'locale' => 'en', 'slug' => 'about-us', 'is_published' => '0' ]));
        $nl = $page->fresh()->translation('nl');
        $en = $page->fresh()->translation('en');

        $html = $this->actingAs($admin)->get('/admin/pages/' . $page->id . '/translations/' . $nl->id . '/edit')
            ->assertStatus(200)
            ->getContent();

        // Tabs: the two languages with their state, and French to add.
        $this->assertStringContainsString('href="' . url('/admin/pages/' . $page->id . '/translations/' . $nl->id . '/edit') . '" aria-current="page"', $html);
        $this->assertStringContainsString('gepubliceerd</span>', $html);
        $this->assertStringContainsString('href="' . url('/admin/pages/' . $page->id . '/translations/' . $en->id . '/edit') . '"', $html);
        $this->assertStringContainsString('concept</span>', $html);
        $this->assertStringContainsString('href="' . url('/admin/pages/' . $page->id . '/translations/create') . '?locale=fr"', $html);
        $this->assertStringContainsString('+ Frans', $html);

        // The page's settings in the side column, the address in front of the slug.
        $aside = substr($html, strpos($html, '<div class="col-lg-4 charon-form-aside">'));
        $this->assertStringContainsString('Geldt voor alle talen.', $aside);
        $this->assertStringContainsString('name="parent[show_in_menu]"', $aside);
        $this->assertStringContainsString('name="fields[is_published]"', $aside);
        $this->assertStringContainsString('cms-slug-prefix', $html);
        $this->assertStringContainsString('Opslaan en bekijken', $html);

        // Saving changes both.
        $this->actingAs($admin)
            ->put('/admin/pages/' . $page->id . '/translations/' . $nl->id, [
                'fields' => $this->translationFields([ 'title' => 'Wie zijn we' ]),
                'parent' => $this->pageFields([ 'show_in_menu' => '1', 'sort_order' => '4' ]),
            ])
            ->assertRedirect('/admin/pages/' . $page->id . '/translations/' . $nl->id . '/edit');

        $this->assertSame('Wie zijn we', $nl->fresh()->title);
        $this->assertTrue($page->fresh()->show_in_menu);
        $this->assertSame(4, (int) $page->fresh()->sort_order);

        // "Opslaan en bekijken" opens the page on the site.
        $this->actingAs($admin)
            ->put('/admin/pages/' . $page->id . '/translations/' . $nl->id, [
                'fields' => $this->translationFields(),
                'parent' => $this->pageFields(),
                'charon_save' => 'view',
            ])
            ->assertRedirect(url('/over-ons') . '?preview=1');

        // A new language opened from its tab starts on that language.
        $this->actingAs($admin)->get('/admin/pages/' . $page->id . '/translations/create?locale=fr')
            ->assertStatus(200)
            ->assertSee('<option value="fr" selected>', false);
    }

    public function testRefusedPageSettingsSaveNothing()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->emptyPage($organisation);
        $this->postTranslation($admin, $page, $this->translationFields([ 'locale' => 'nl' ]));
        $nl = $page->fresh()->translation('nl');
        $edit = '/admin/pages/' . $page->id . '/translations/' . $nl->id . '/edit';

        // A page can't hang under itself.
        $this->actingAs($admin)->from($edit)
            ->put('/admin/pages/' . $page->id . '/translations/' . $nl->id, [
                'fields' => $this->translationFields([ 'title' => 'Wie zijn we' ]),
                'parent' => $this->pageFields([ 'parent_id' => (string) $page->id ]),
            ])
            ->assertRedirect($edit)
            ->assertSessionHasErrors('parent.parent_id');

        $this->assertSame('Over ons', $nl->fresh()->title);
        $this->assertNull($page->fresh()->parent_id);
    }

    public function testAddEnglishTranslation()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->createPage($organisation, 'over-ons');

        // Only the missing languages are offered; copying from the existing one is.
        $form = $this->actingAs($admin)->get('/admin/pages/' . $page->id . '/translations/create')
            ->assertStatus(200)
            ->assertSee('Kopieer uit')
            ->getContent();
        $this->assertStringContainsString('<option value="en"', $form);
        $this->assertStringNotContainsString('<option value="nl">Nederlands</option>', explode('field-copy_from', $form)[0]);

        $this->postTranslation($admin, $page, $this->translationFields([ 'locale' => 'en', 'slug' => 'about-us', 'title' => 'About us' ]))
            ->assertSessionHas('message', 'Vertaling toegevoegd.');

        $en = $page->fresh()->translation('en');
        $this->assertSame('about-us', $en->path);
        $this->get('/en/about-us')->assertStatus(200)->assertSee('About us');
        $this->assertSame(2, $page->translations()->count());

        // A second English translation is refused.
        $this->postTranslation($admin, $page, $this->translationFields([ 'locale' => 'en', 'slug' => 'about-us-2', 'title' => 'Again' ]))
            ->assertRedirect('/admin/pages/' . $page->id . '/translations/create');
        $this->assertSame(2, $page->translations()->count());
    }

    public function testReservedTopLevelSlugIsRejected()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->emptyPage($organisation);

        foreach ([ 'events', '', 'Over ons!' ] as $slug) {
            $this->postTranslation($admin, $page, $this->translationFields([ 'locale' => 'nl', 'slug' => $slug ]))
                ->assertRedirect('/admin/pages/' . $page->id . '/translations/create');
            $this->assertTrue((session('errors') && session('errors')->any()) || session()->has('message'), 'The form says what is wrong.');
        }

        $this->assertSame(0, PageTranslation::count());
    }

    public function testSlugIsNormalised()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->emptyPage($organisation);

        $this->postTranslation($admin, $page, $this->translationFields([ 'locale' => 'nl', 'slug' => ' /Over-Ons/ ' ]));

        $this->assertSame('over-ons', $page->fresh()->translation('nl')->slug);
    }

    public function testChildSlugsMayUseReservedWords()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $parent = $this->createPage($organisation, 'over-ons');

        $this->actingAs($admin)->post('/admin/pages', [ 'fields' => $this->pageFields([ 'parent_id' => (string) $parent->id ]) ]);
        $child = Page::where('parent_id', $parent->id)->firstOrFail();

        $this->postTranslation($admin, $child, $this->translationFields([ 'locale' => 'nl', 'slug' => 'events' ]))
            ->assertSessionHas('message', 'Vertaling toegevoegd.');

        $this->get('/over-ons/events')->assertStatus(200);
    }

    public function testDuplicatePathIsRejected()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $this->createPage($organisation, 'over-ons');
        $page = $this->emptyPage($organisation);

        $this->postTranslation($admin, $page, $this->translationFields([ 'locale' => 'nl' ]));
        $this->assertTrue((session('errors') && session('errors')->any()) || session()->has('message'), 'The form says what is wrong.');
        $this->assertSame(1, PageTranslation::count());

        // Same path in another organisation is fine.
        $other = $this->createOrganisation();
        $otherAdmin = $this->createOrganisationAdmin($other);
        $otherPage = $this->emptyPage($other);
        $this->postTranslation($otherAdmin, $otherPage, $this->translationFields([ 'locale' => 'nl' ]))
            ->assertSessionHas('message', 'Vertaling toegevoegd.');
    }

    public function testInvalidBlockDataReRendersTheFormWithTheErrorsAndTheInput()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->emptyPage($organisation);

        $input = $this->translationFields([ 'locale' => 'nl' ]);
        $input['blocks'][0]['data']['title'] = '';
        $input['blocks'][0]['data']['buttons'][0]['url'] = 'javascript:alert(1)';
        $input['blocks'][1]['data']['html'] = '<p>Deze tekst mag niet verloren gaan</p>';

        $this->postTranslation($admin, $page, $input)
            ->assertRedirect('/admin/pages/' . $page->id . '/translations/create');

        $this->assertSame(0, PageTranslation::count());
        $this->assertTrue((session('errors') && session('errors')->any()) || session()->has('message'), 'The form says what is wrong.');

        $this->actingAs($admin)->get('/admin/pages/' . $page->id . '/translations/create')
            ->assertStatus(200)
            ->assertSee('alert alert-warning', false)
            ->assertSee('Deze tekst mag niet verloren gaan')
            ->assertSee('value="Over ons"', false);
    }

    public function testImageOfAnotherOrganisationIsRejected()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->emptyPage($organisation);

        $foreign = $this->createOrganisationAsset($other);
        $legacy = $this->createOrganisationAsset(null);
        $mine = $this->createOrganisationAsset($organisation);

        foreach ([ $foreign, $legacy ] as $asset) {
            $input = $this->translationFields([ 'locale' => 'nl' ]);
            $input['blocks'][0]['data']['image_id'] = (string) $asset->id;
            $this->postTranslation($admin, $page, $input);
            $this->assertTrue((session('errors') && session('errors')->any()) || session()->has('message'), 'The form says what is wrong.');

            $this->postTranslation($admin, $page, $this->translationFields([ 'locale' => 'nl', 'og_image_id' => (string) $asset->id ]));
            $this->assertTrue((session('errors') && session('errors')->any()) || session()->has('message'), 'The form says what is wrong.');
        }

        $this->assertSame(0, PageTranslation::count());

        $input = $this->translationFields([ 'locale' => 'nl', 'og_image_id' => (string) $mine->id ]);
        $input['blocks'][0]['data']['image_id'] = (string) $mine->id;
        $this->postTranslation($admin, $page, $input)->assertSessionHas('message', 'Vertaling toegevoegd.');

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

        $this->putTranslation($admin, $parent, 'nl', $this->translationFields([ 'slug' => 'wie-zijn-we' ]))
            ->assertSessionHas('message', 'Opgeslagen.');

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

        // The parent select offers the other pages, not the page itself.
        $form = $this->actingAs($admin)->get('/admin/pages/' . $page->id . '/edit')->assertStatus(200)->getContent();
        $this->assertStringContainsString('<option value="' . $parent->id . '"', $form);
        $this->assertStringNotContainsString('<option value="' . $page->id . '"', $form);

        $this->actingAs($admin)
            ->put('/admin/pages/' . $page->id, [ 'fields' => $this->pageFields([ 'parent_id' => (string) $parent->id ]) ])
            ->assertSessionHas('message', 'Opgeslagen.');

        $this->assertSame('over-ons/team', $page->fresh()->translation('nl')->path);

        // A page cannot hang under itself or its own descendants.
        $this->actingAs($admin)
            ->put('/admin/pages/' . $parent->id, [ 'fields' => $this->pageFields([ 'parent_id' => (string) $page->id ]) ]);
        $this->assertTrue((session('errors') && session('errors')->any()) || session()->has('message'), 'The form says what is wrong.');
        $this->assertNull($parent->fresh()->parent_id);

        // Nor under another organisation's page.
        $foreign = $this->createPage($this->createOrganisation(), 'elders');
        $this->actingAs($admin)
            ->put('/admin/pages/' . $page->id, [ 'fields' => $this->pageFields([ 'parent_id' => (string) $foreign->id ]) ]);
        $this->assertTrue((session('errors') && session('errors')->any()) || session()->has('message'), 'The form says what is wrong.');
        $this->assertSame($parent->id, (int) $page->fresh()->parent_id);
    }

    public function testCopyFromDutchClonesBlocksWithFreshIds()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->createPage($organisation, 'over-ons', [
            [ 'id' => 'aaaaaa', 'type' => 'rich_text', 'data' => [ 'html' => '<p>Hallo</p>' ] ],
            [ 'id' => 'bbbbbb', 'type' => 'cta', 'data' => [ 'title' => 'Bel ons', 'background' => 'dark', 'buttons' => [] ] ],
        ]);

        // Nothing filled in but the language and what to copy from.
        $this->postTranslation($admin, $page, [ 'locale' => 'fr', 'copy_from' => 'nl', 'title' => '', 'slug' => '', 'is_published' => '0', 'blocks' => '' ])
            ->assertSessionHas('message', 'Vertaling toegevoegd.');

        $nl = $page->fresh()->translation('nl');
        $fr = $page->fresh()->translation('fr');

        $this->assertFalse($fr->is_published);
        $this->assertSame('over-ons', $fr->path);
        $this->assertSame($nl->title, $fr->title);
        $this->assertCount(2, $fr->blocks);
        $this->assertSame($nl->blocks[0]['data'], $fr->blocks[0]['data']);
        $this->assertSame('Bel ons', $fr->blocks[1]['data']['title']);

        foreach ($fr->blocks as $index => $block) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{6}$/', $block['id']);
            $this->assertNotSame($nl->blocks[$index]['id'], $block['id']);
        }

        // What is filled in wins over the copy.
        $this->postTranslation($admin, $page, [ 'locale' => 'en', 'copy_from' => 'nl', 'title' => 'About us', 'slug' => 'about-us', 'is_published' => '0', 'blocks' => '' ]);
        $en = $page->fresh()->translation('en');
        $this->assertSame('About us', $en->title);
        $this->assertSame('about-us', $en->path);
        $this->assertCount(2, $en->blocks);
    }

    public function testDeleteTranslation()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->createPage($organisation, 'over-ons');
        $en = $this->createPageTranslation($page, 'en', 'about-us');

        $this->actingAs($admin)
            ->delete('/admin/pages/' . $page->id . '/translations/' . $en->id)
            ->assertRedirect('/admin/pages/' . $page->id . '?tab=translations');

        $this->assertNull($page->fresh()->translation('en'));
        $this->assertNotNull($page->fresh()->translation('nl'));
    }

    public function testSavingForgetsTheSitemapCache()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->emptyPage($organisation);
        $key = SitemapController::cacheKey($organisation);

        Cache::put($key, [ 'stale' ], 3600);

        $this->postTranslation($admin, $page, $this->translationFields([ 'locale' => 'nl' ]))
            ->assertSessionHas('message', 'Vertaling toegevoegd.');

        $this->assertFalse(Cache::has($key));
    }

    public function testEditFormRendersEveryBlockType()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $page = $this->createPage($organisation, 'alles');

        $response = $this->actingAs($admin)->get('/admin/pages/' . $page->id . '/translations/' . $page->translation('nl')->id . '/edit');
        $response->assertStatus(200);

        foreach (array_keys(config('cms.blocks')) as $type) {
            $response->assertSee('data-block-type="' . $type . '"', false);
        }
    }

    public function testAPageCanBeAMenuLink()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $this->actingAs($admin)
            ->post('/admin/pages', [ 'fields' => $this->pageFields([ 'link' => [ 'kind' => '@calendar', 'url' => 'ignored' ] ]) ]);
        $page = Page::forOrganisation($organisation)->firstOrFail();
        $this->assertSame(Page::LINK_CALENDAR, $page->link);

        $form = $this->actingAs($admin)->get('/admin/pages/' . $page->id . '/edit')->assertStatus(200)->getContent();
        $this->assertMatchesRegularExpression('/<option value="@calendar"\s+selected/', $form);

        // Another address must be a safe one.
        $this->actingAs($admin)
            ->put('/admin/pages/' . $page->id, [ 'fields' => $this->pageFields([ 'link' => [ 'kind' => 'url', 'url' => 'javascript:alert(1)' ] ]) ]);
        $this->assertTrue((session('errors') && session('errors')->any()) || session()->has('message'), 'The form says what is wrong.');
        $this->assertSame(Page::LINK_CALENDAR, $page->fresh()->link);

        $this->actingAs($admin)
            ->put('/admin/pages/' . $page->id, [ 'fields' => $this->pageFields([ 'link' => [ 'kind' => 'url', 'url' => 'https://shop.example' ] ]) ])
            ->assertSessionHas('message', 'Opgeslagen.');
        $this->assertSame('https://shop.example', $page->fresh()->link);

        // Back to a page with content.
        $this->actingAs($admin)
            ->put('/admin/pages/' . $page->id, [ 'fields' => $this->pageFields() ])
            ->assertSessionHas('message', 'Opgeslagen.');
        $this->assertNull($page->fresh()->link);
    }

    public function testFixedPartsCanBeAddedAsMenuLinksOnce()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);
        $this->createPage($organisation, 'menu-kalender');

        $this->actingAs($admin)->get('/admin/pages')
            ->assertSee('Vaste onderdelen als menulinks toevoegen');

        $this->actingAs($admin)->post('/admin/pages/fixed-links')->assertRedirect('/admin/pages');

        $pages = Page::forOrganisation($organisation)->whereNotNull('link')->get()->keyBy('link');
        $this->assertEquals([ '@blog', '@calendar', '@series' ], $pages->keys()->sort()->values()->all());
        $this->assertSame(-10, (int) $pages['@calendar']->sort_order);
        $this->assertTrue((bool) $pages['@calendar']->show_in_menu);

        // Every language, published; a taken address gets a number.
        $calendar = $pages['@calendar']->translations->keyBy('locale');
        $this->assertEqualsCanonicalizing(config('cms.locales'), $calendar->keys()->all());
        $this->assertSame('menu-kalender-2', $calendar['nl']->slug);
        $this->assertSame('Calendar', $calendar['en']->title);
        $this->assertTrue((bool) $calendar['fr']->is_published);

        // A second click adds nothing, and the box is gone.
        $this->actingAs($admin)->post('/admin/pages/fixed-links');
        $this->assertSame(3, Page::forOrganisation($organisation)->whereNotNull('link')->count());
        $this->actingAs($admin)->get('/admin/pages')
            ->assertDontSee('Vaste onderdelen als menulinks toevoegen')
            ->assertSee('@calendar');
    }

    public function testFixedPartsNeedAnAdminOfTheOrganisation()
    {
        $organisation = $this->createOrganisation();
        $this->createUser(); // the first user ever created becomes admin (User::boot)
        $user = $this->createUser();

        $this->actingAs($user)->post('/admin/pages/fixed-links')->assertRedirect('/');
        $this->assertSame(0, Page::forOrganisation($organisation)->count());
    }
}
