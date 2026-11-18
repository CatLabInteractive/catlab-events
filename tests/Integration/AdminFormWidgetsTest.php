<?php

namespace Tests\Integration;

use App\Models\Organisation;
use App\Models\Series;
use App\Models\User;
use CatLab\CentralStorage\Client\Models\Asset;
use Illuminate\Http\UploadedFile;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * The admin forms are built from charon-frontend's widgets. Relationships to
 * an Asset (posters, logos, headers, ...) use its FileWidget, storing uploads
 * through CentralStorageUploadHandler (see BaseAdminController); linkable
 * options come from the related controllers' api indexes.
 */
class AdminFormWidgetsTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    private function adminOf(Organisation $organisation): User
    {
        $admin = $this->createUser();
        $admin->admin = true;
        $admin->save();

        $organisation->users()->attach($admin, [ 'role' => 10 ]);

        return $admin;
    }

    private function createAsset(string $name): Asset
    {
        $asset = new Asset();
        $asset->name = $name;
        $asset->mimetype = 'image/png';
        $asset->type = 'image';
        $asset->asset_key = 'key-' . $name;
        $asset->width = 800;
        $asset->height = 1200;
        $asset->save();

        return $asset;
    }

    private function createSeries(Organisation $organisation): Series
    {
        $series = new Series();
        $series->organisation()->associate($organisation);
        $series->name = 'Test series';
        $series->slug = 'test-series-' . uniqid();
        $series->save();

        return $series;
    }

    /**
     * The series form as the browser would post it, with $assets as the
     * file fields' input.
     */
    private function updateSeries(User $admin, Series $series, array $assets)
    {
        return $this->actingAs($admin)
            ->from('/admin/series/' . $series->id . '/edit')
            ->put('/admin/series/' . $series->id, [
                'fields' => array_merge([
                    'slug' => $series->slug,
                    'name' => $series->name,
                    'active' => '1',
                ], $assets),
            ]);
    }

    public function testFormRendersAssetRelationshipsAsFileFields()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->adminOf($organisation);
        $series = $this->createSeries($organisation);
        $older = $this->createAsset('older.png');
        $newer = $this->createAsset('newer.png');
        $series->logo()->associate($older);
        $series->save();

        $response = $this->actingAs($admin)->get('/admin/series/' . $series->id . '/edit');
        $response->assertStatus(200);

        $response->assertSee('enctype="multipart/form-data"', false);
        $response->assertSee('name="fields[logo][file]"', false);
        $response->assertSee('name="fields[header][id]"', false);
        $response->assertSee('name="fields[logo][remove]"', false);
        $response->assertSee('Youtube thumbnail');
        $response->assertSee('src="' . $older->getUrl([ 'width' => 320 ]) . '"', false);

        // The current file is selected, newest assets are listed first.
        $response->assertSee('<option value="' . $older->id . '" selected>', false);
        $response->assertSeeInOrder([ 'newer.png (800×1200', 'older.png (800×1200' ]);

        // The create form gets the same fields.
        $this->actingAs($admin)->get('/admin/series/create')
            ->assertStatus(200)
            ->assertSee('name="fields[header][file]"', false);
    }

    public function testFormsWithoutAssetRelationshipsAreUntouched()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->adminOf($organisation);

        $this->actingAs($admin)->get('/admin/venues/create')
            ->assertStatus(200)
            ->assertDontSee('multipart/form-data', false);
    }

    public function testUploadingAFileStoresAndLinksIt()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->adminOf($organisation);
        $series = $this->createSeries($organisation);

        $stored = new Asset();
        $stored->name = 'poster.png';
        $stored->asset_key = 'uploaded';
        \CentralStorage::shouldReceive('store')->once()->andReturn($stored);

        $this->updateSeries($admin, $series, [
            'logo' => [ 'id' => '', 'file' => UploadedFile::fake()->image('poster.png', 600, 900) ],
        ])->assertRedirect();

        $this->assertNotNull($stored->id);
        $this->assertSame($stored->id, $series->fresh()->logo_asset_id);
    }

    public function testPickingAnExistingFileLinksIt()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->adminOf($organisation);
        $series = $this->createSeries($organisation);
        $asset = $this->createAsset('header.png');

        $this->updateSeries($admin, $series, [
            'header' => [ 'id' => (string) $asset->id ],
        ])->assertRedirect();

        $this->assertSame($asset->id, $series->fresh()->header_asset_id);
    }

    public function testUntouchedFieldKeepsItsFileAndRemoveUnlinks()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->adminOf($organisation);
        $series = $this->createSeries($organisation);
        $logo = $this->createAsset('logo.png');
        $header = $this->createAsset('header.png');
        $series->logo()->associate($logo);
        $series->header()->associate($header);
        $series->save();

        $this->updateSeries($admin, $series, [
            'logo' => [ 'id' => (string) $logo->id ],
            'header' => [ 'id' => (string) $header->id, 'remove' => '1' ],
        ])->assertRedirect();

        $series = $series->fresh();
        $this->assertSame($logo->id, $series->logo_asset_id);
        $this->assertNull($series->header_asset_id);
    }

    public function testOnlyImagesCanBeUploaded()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->adminOf($organisation);
        $series = $this->createSeries($organisation);

        \CentralStorage::shouldReceive('store')->never();

        $this->updateSeries($admin, $series, [
            'logo' => [ 'id' => '', 'file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf') ],
        ])->assertRedirect()->assertSessionHasErrors('fields.logo.file');

        $this->assertNull($series->fresh()->logo_asset_id);
    }

    /**
     * The event form's series/competition/people options come from those
     * controllers' api indexes, which only list the active organisation's
     * records. They used to list every organisation's.
     */
    public function testLinkableOptionsOnlyListTheActiveOrganisationsRecords()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->adminOf($organisation);
        $event = $this->createEvent($organisation);
        $ours = $this->createSeries($organisation);
        $ours->name = 'Our series';
        $ours->save();

        $other = $this->createOrganisation();
        $theirs = $this->createSeries($other);
        $theirs->name = 'Somebody elses series';
        $theirs->save();

        $response = $this->actingAs($admin)->get('/admin/events/' . $event->id . '/edit');

        $response->assertStatus(200);
        $response->assertSee('<option value="' . $ours->id . '">Our series</option>', false);
        $response->assertDontSee('Somebody elses series');
    }

    /**
     * An order can only move to a ticket category of its own event.
     */
    public function testOrderFormOffersTheTicketCategoriesOfItsEvent()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->adminOf($organisation);
        $event = $this->createEvent($organisation);
        $category = $this->createTicketCategory($event, 10.0);
        $otherEvent = $this->createEvent($organisation);
        $otherCategory = $this->createTicketCategory($otherEvent, 12.0);
        $otherCategory->name = 'Other event ticket';
        $otherCategory->save();

        $order = new \App\Models\Order();
        $order->event()->associate($event);
        $order->user()->associate($admin);
        $order->ticketCategory()->associate($category);
        $order->state = \App\Models\Order::STATE_ACCEPTED;
        $order->save();

        $response = $this->actingAs($admin)->get('/admin/orders/' . $order->id . '/edit');

        $response->assertStatus(200);
        $response->assertSee('<option value="' . $category->id . '" selected>' . $category->name . '</option>', false);
        $response->assertDontSee('Other event ticket');
    }

    /**
     * The public "create a team" form is rendered by charon-frontend's
     * form-fields too.
     */
    public function testPublicGroupFormRendersAndCreatesATeam()
    {
        $this->createOrganisation();
        $user = $this->createUser();

        $this->actingAs($user)->get('/groups/create')
            ->assertStatus(200)
            ->assertSee('name="fields[name]"', false);

        $this->actingAs($user)->post('/groups', [ 'fields' => [ 'name' => 'De Quizvogels' ] ])
            ->assertRedirect();

        $this->assertDatabaseHas('groups', [ 'name' => 'De Quizvogels' ]);
    }

    /**
     * People have no name field; the event form lists them by their first
     * and last name, not as "#12".
     */
    public function testPeopleAreListedByName()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->adminOf($organisation);
        $event = $this->createEvent($organisation);
        $person = $this->createPerson($organisation, $admin, 'Anna', 'Peeters');

        $this->actingAs($admin)->get('/admin/events/' . $event->id . '/edit')
            ->assertStatus(200)
            ->assertSee('>Anna Peeters</label>', false)
            ->assertDontSee('>#' . $person->id . '</label>', false);
    }

    /**
     * With more than a handful of people, the producers, authors, ... are a
     * searchable picker, not a wall of checkboxes.
     */
    public function testLongPeopleListsUseThePicker()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->adminOf($organisation);
        $event = $this->createEvent($organisation);
        for ($i = 1; $i <= 8; $i++) {
            $this->createPerson($organisation, $admin, 'Persoon', (string) $i);
        }

        $this->actingAs($admin)->get('/admin/events/' . $event->id . '/edit')
            ->assertStatus(200)
            ->assertSee('data-charon-picker', false)
            ->assertSee('>Persoon 8</option>', false)
            ->assertDontSee('>Persoon 8</label>', false);
    }

    private function createPerson(Organisation $organisation, $user, string $firstName, string $lastName): \App\Models\Person
    {
        $person = new \App\Models\Person();
        $person->first_name = $firstName;
        $person->last_name = $lastName;
        $person->organisation()->associate($organisation);
        $person->user()->associate($user);
        $person->save();

        return $person;
    }

    /**
     * Descriptions are html() fields: a rich textarea, and the HTML is
     * cleaned on save (they're printed unescaped on the public pages).
     */
    public function testDescriptionsAreHtmlFields()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->adminOf($organisation);
        $series = $this->createSeries($organisation);

        $this->actingAs($admin)->get('/admin/series/' . $series->id . '/edit')
            ->assertStatus(200)
            ->assertSee('class="form-control html-richtext-input"', false);

        $this->actingAs($admin)->put('/admin/series/' . $series->id, [
            'fields' => [
                'slug' => $series->slug,
                'name' => $series->name,
                'active' => '1',
                'description' => '<p onclick="alert(1)">Hallo <a href="https://example.org" target="_blank">daar</a></p><script>alert(2)</script>',
            ],
        ])->assertRedirect();

        $description = $series->fresh()->description;
        $this->assertStringContainsString('Hallo <a href="https://example.org" target="_blank"', $description);
        $this->assertStringNotContainsString('script', $description);
        $this->assertStringNotContainsString('onclick', $description);
    }

    /**
     * Existing content survives a save: anchors (the series FAQ link looks
     * for id="faq"), embedded players; an emptied editor is stored empty.
     */
    public function testHtmlFieldsKeepAnchorsAndEmbedsAndStoreEmptyAsEmpty()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->adminOf($organisation);
        $series = $this->createSeries($organisation);

        $save = function (string $description) use ($admin, $series) {
            $this->actingAs($admin)->put('/admin/series/' . $series->id, [
                'fields' => [
                    'slug' => $series->slug,
                    'name' => $series->name,
                    'active' => '1',
                    'description' => $description,
                ],
            ])->assertRedirect();
            return $series->fresh()->description;
        };

        $description = $save('<h2 id="faq">FAQ</h2><iframe src="https://www.youtube.com/embed/abc"></iframe><iframe src="https://evil.example/x"></iframe>');
        $this->assertStringContainsString('<h2 id="faq">', $description);
        $this->assertStringContainsString('src="https://www.youtube.com/embed/abc"', $description);
        $this->assertStringNotContainsString('evil.example', $description);
        $this->assertTrue($series->fresh()->hasFaq());

        $this->assertSame('', (string) $save('<p><br></p>'));
        $this->assertSame('', (string) $save(''));
    }

    /**
     * The public team page lists members and scores as tabs.
     */
    public function testTheTeamPageRenders()
    {
        $this->createOrganisation();
        $user = $this->createUser();

        $this->actingAs($user)->post('/groups', [ 'fields' => [ 'name' => 'De Quizvogels' ] ]);
        $group = \App\Models\Group::where('name', 'De Quizvogels')->firstOrFail();

        $this->actingAs($user)->get('/groups/' . $group->id)
            ->assertStatus(200)
            ->assertSee('De Quizvogels')
            ->assertSee('?tab=members', false);

        $this->actingAs($user)->get('/groups/' . $group->id . '?tab=members')
            ->assertStatus(200);
    }
}
