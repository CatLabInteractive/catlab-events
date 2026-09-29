<?php

namespace Tests\Integration\Cms;

use CatLab\CentralStorage\Client\Models\Asset;
use Illuminate\Http\UploadedFile;
use Tests\Integration\Concerns\CreatesCmsFixtures;
use Tests\Integration\Concerns\CreatesEventFixtures;
use Tests\Integration\Fakes\FakeCentralStorage;
use Tests\Integration\IntegrationTestCase;

/**
 * Image upload for the page editor (TinyMCE and the block image picker) and
 * the picker's list of the organisation's images.
 */
class AdminUploadTest extends IntegrationTestCase
{
    use CreatesEventFixtures;
    use CreatesCmsFixtures;

    public function testAnonymousUploadIsSentToTheLogin()
    {
        $this->post('/admin/cms/upload', [ 'file' => UploadedFile::fake()->image('foto.png') ])
            ->assertRedirect('/login');
        $this->get('/admin/cms/assets')->assertRedirect('/login');

        $this->assertCount(0, $this->centralStorage->stored);
        $this->assertSame(0, Asset::count());
    }

    public function testNonAdminsAreSentAway()
    {
        $this->createOrganisation();
        $this->createUser(); // the first user ever created becomes admin (User::boot)
        $user = $this->createUser();

        $this->actingAs($user)
            ->post('/admin/cms/upload', [ 'file' => UploadedFile::fake()->image('foto.png') ])
            ->assertRedirect('/');

        $this->assertCount(0, $this->centralStorage->stored);
    }

    public function testAdminUploadsAnImageForTheActiveOrganisation()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $response = $this->actingAs($admin)->post('/admin/cms/upload', [
            'file' => UploadedFile::fake()->image('zaal.png', 640, 480),
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([ 'id', 'location', 'thumbnail', 'name' ]);

        $this->assertCount(1, $this->centralStorage->stored);

        /** @var Asset $asset */
        $asset = Asset::findOrFail($response->json('id'));
        $this->assertSame($organisation->id, (int) $asset->organisation_id);
        $this->assertSame($admin->id, (int) $asset->user_id);
        $this->assertSame('image', $asset->type);
        $this->assertSame('zaal.png', $asset->name);
        $this->assertSame(640, (int) $asset->width);

        $this->assertStringStartsWith(FakeCentralStorage::FRONT . '/assets/', $response->json('location'));
        $this->assertSame($asset->getUrl(), $response->json('location'));
    }

    public function testNonImagesAreRejected()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $files = [
            UploadedFile::fake()->createWithContent('shell.php', '<?php echo "hi";'),
            UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ];

        foreach ($files as $file) {
            $response = $this->actingAs($admin)->post('/admin/cms/upload', [ 'file' => $file ]);
            $response->assertStatus(422);
            $response->assertJsonStructure([ 'message', 'errors' => [ 'file' ] ]);
        }

        $this->actingAs($admin)->post('/admin/cms/upload', [])->assertStatus(422);

        $this->assertCount(0, $this->centralStorage->stored);
        $this->assertSame(0, Asset::count());
    }

    public function testPickerListsOnlyTheActiveOrganisationsImages()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $older = $this->createOrganisationAsset($organisation, 'zaal.jpg');
        $newer = $this->createOrganisationAsset($organisation, 'quizmaster.jpg');
        $foreign = $this->createOrganisationAsset($other, 'buren.jpg');
        $legacy = $this->createOrganisationAsset(null, 'oud.jpg');

        $document = $this->createOrganisationAsset($organisation, 'reglement.pdf');
        $document->type = 'document';
        $document->mimetype = 'application/pdf';
        $document->save();

        $response = $this->actingAs($admin)->get('/admin/cms/assets');
        $response->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([ $newer->id, $older->id ], $ids);
        $this->assertNotContains($foreign->id, $ids);
        $this->assertNotContains($legacy->id, $ids);
        $this->assertNotContains($document->id, $ids);

        $this->assertSame($newer->getUrl(), $response->json('data.0.location'));

        // Search on the name.
        $response = $this->actingAs($admin)->get('/admin/cms/assets?q=zaal');
        $this->assertSame([ $older->id ], array_column($response->json('data'), 'id'));
    }

    public function testAnUploadedImageCanBeUsedInABlock()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $id = $this->actingAs($admin)
            ->post('/admin/cms/upload', [ 'file' => UploadedFile::fake()->image('zaal.png') ])
            ->json('id');

        $this->actingAs($admin)->post('/admin/pages', [
            'slug' => 'zaal',
            'title' => 'De zaal',
            'is_published' => '1',
            'blocks' => [
                [ 'type' => 'text_image', 'id' => 'a1b2c3', 'data' => [
                    'html' => '<p>Onze zaal.</p>', 'image_id' => (string) $id, 'image_position' => 'right',
                ] ],
            ],
        ])->assertSessionHasNoErrors();
    }

    public function testTheLegacyAssetUploadAlsoSetsTheOrganisation()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $this->actingAs($admin)
            ->post('/admin/assets', [ 'file' => UploadedFile::fake()->image('oud.png') ])
            ->assertRedirect();

        $this->assertSame($organisation->id, (int) Asset::firstOrFail()->organisation_id);
    }

    public function testTheEditorKnowsTheUploadAndPickerUrls()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->createOrganisationAdmin($organisation);

        $response = $this->actingAs($admin)->get('/admin/pages/create');
        $response->assertStatus(200);
        $response->assertSee('data-cms-upload-url="' . url('admin/cms/upload') . '"', false);
        $response->assertSee('data-cms-assets-url="' . url('admin/cms/assets') . '"', false);
        $response->assertSee('data-cms-image-picker', false);
    }
}
