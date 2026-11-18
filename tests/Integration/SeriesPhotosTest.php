<?php

namespace Tests\Integration;

use App\Models\Organisation;
use App\Models\Series;
use App\Models\SeriesPhoto;
use App\Models\User;
use CatLab\CentralStorage\Client\Models\Asset;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * Series > Photos in the admin: the slideshow photos of a series hero.
 */
class SeriesPhotosTest extends IntegrationTestCase
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
        $asset->mimetype = 'image/jpeg';
        $asset->type = 'image';
        $asset->asset_key = 'key-' . $name . uniqid();
        $asset->width = 1600;
        $asset->height = 1000;
        $asset->save();
        return $asset;
    }

    private function createSeries(Organisation $organisation): Series
    {
        $series = new Series();
        $series->organisation()->associate($organisation);
        $series->name = 'Fotoreeks';
        $series->slug = 'fotoreeks-' . uniqid();
        $series->save();
        return $series;
    }

    public function testAdminCanAddAPhotoToASeries()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->adminOf($organisation);
        $series = $this->createSeries($organisation);
        $asset = $this->createAsset('zaal.jpg');

        $this->actingAs($admin)->get('/admin/series/' . $series->id . '/photos')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/series/' . $series->id . '/photos/create')
            ->assertStatus(200)
            ->assertSee('name="fields[photo][file]"', false);

        $this->actingAs($admin)
            ->from('/admin/series/' . $series->id . '/photos/create')
            ->post('/admin/series/' . $series->id . '/photos', [
                'fields' => [
                    'photo' => [ 'id' => (string) $asset->id ],
                    'sort' => '2',
                ],
            ])
            ->assertRedirect();

        $photo = SeriesPhoto::where('series_id', $series->id)->first();
        $this->assertNotNull($photo);
        $this->assertSame($asset->id, $photo->photo_asset_id);
        $this->assertSame(2, (int) $photo->sort);
    }

    public function testOtherOrganisationsCannotSeeThePhotos()
    {
        $series = $this->createSeries($this->createOrganisation());
        $outsider = $this->adminOf($this->createOrganisation());

        $this->actingAs($outsider)->get('/admin/series/' . $series->id . '/photos')->assertStatus(403);
    }

    public function testHeroPhotosFallBackToTheHeaderPhoto()
    {
        $series = $this->createSeries($this->createOrganisation());
        $this->assertSame([], $series->getHeroPhotos());

        $header = $this->createAsset('header.jpg');
        $series->header()->associate($header);
        $series->save();
        $this->assertSame([ $header->id ], array_map(fn ($a) => $a->id, $series->fresh()->getHeroPhotos()));

        $second = $this->createAsset('b.jpg');
        $first = $this->createAsset('a.jpg');
        foreach ([ [ $second, 2 ], [ $first, 1 ] ] as [ $asset, $sort ]) {
            $photo = new SeriesPhoto([ 'sort' => $sort ]);
            $photo->series()->associate($series);
            $photo->photo()->associate($asset);
            $photo->save();
        }
        $this->assertSame([ $first->id, $second->id ], array_map(fn ($a) => $a->id, $series->fresh()->getHeroPhotos()));
    }
}
