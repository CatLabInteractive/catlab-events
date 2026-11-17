<?php

namespace Tests\Integration;

use App\Models\Organisation;
use App\Models\Series;
use App\Models\User;
use CatLab\CentralStorage\Client\Models\Asset;
use Illuminate\Http\UploadedFile;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * Relationships to an Asset (posters, logos, headers, ...) are rendered in the
 * admin forms as a file field: preview, upload, pick existing, remove. See
 * BaseAdminController.
 */
class AdminAssetFieldTest extends IntegrationTestCase
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
        $field = function ($type, $value) {
            return [ 'type' => $type, 'multiple' => '0', 'input' => [ [ 'value' => $value ] ] ];
        };

        return $this->actingAs($admin)->put('/admin/series/' . $series->id, [
            'fields' => [
                'slug' => $field('string', $series->slug),
                'name' => $field('string', $series->name),
            ],
            'linkableFields' => '',
            'assetFields' => 'youtube_thumbnail,logo,header',
            'assets' => $assets,
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
        $response->assertSee('name="assets[logo][file]"', false);
        $response->assertSee('name="assets[header][id]"', false);
        $response->assertSee('name="assets[logo][remove]"', false);
        $response->assertSee('value="youtube_thumbnail,logo,header"', false);
        $response->assertSee('Youtube thumbnail');

        // Charon's own select for these relationships is gone.
        $response->assertDontSee('name="linkable[logo][id]"', false);

        // The current file is selected, newest assets are listed first.
        $response->assertSee('<option value="' . $older->id . '" selected>', false);
        $response->assertSeeInOrder([ 'newer.png (800×1200', 'older.png (800×1200' ]);

        // The create form gets the same fields.
        $this->actingAs($admin)->get('/admin/series/create')
            ->assertStatus(200)
            ->assertSee('name="assets[header][file]"', false);
    }

    public function testFormsWithoutAssetRelationshipsAreUntouched()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->adminOf($organisation);

        $this->actingAs($admin)->get('/admin/venues/create')
            ->assertStatus(200)
            ->assertDontSee('multipart/form-data', false)
            ->assertDontSee('assetFields', false);
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
        ])->assertRedirect()->assertSessionHasErrors('assets.logo.file');

        $this->assertNull($series->fresh()->logo_asset_id);
    }
}
