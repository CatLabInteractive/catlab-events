<?php

namespace Tests\Integration;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * organisations.footer_html is printed unescaped in the footer of every
 * page, so it goes through the CMS sanitiser whenever it is written.
 */
class OrganisationFooterSanitiseTest extends IntegrationTestCase
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

    public function testFooterIsSanitisedOnModelSave()
    {
        $organisation = $this->createOrganisation();
        $organisation->footer_html = '<p onclick="x()">Hallo <a href="javascript:alert(1)">klik</a></p><script>alert(1)</script>';
        $organisation->save();

        $stored = DB::table('organisations')->where('id', $organisation->id)->value('footer_html');
        $this->assertStringNotContainsString('script', $stored);
        $this->assertStringNotContainsString('onclick', $stored);
        $this->assertStringNotContainsString('javascript', $stored);
        $this->assertStringContainsString('Hallo', $stored);
    }

    public function testFooterIsSanitisedWhenSavedThroughTheAdmin()
    {
        $organisation = $this->createOrganisation();
        $admin = $this->adminOf($organisation);

        // The charon-frontend form posts every field as fields[name][input][0][value].
        $field = function ($value) {
            return [ 'type' => 'string', 'multiple' => 0, 'input' => [ [ 'value' => $value ] ] ];
        };

        $this->actingAs($admin)
            ->put('/admin/organisations/' . $organisation->id, [
                'fields' => [
                    'name' => $field($organisation->name),
                    'footer_html' => $field('<p>Volg ons</p><script>alert(document.cookie)</script><img src="x" onerror="alert(1)">'),
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHas('message', 'Saved.');

        $stored = DB::table('organisations')->where('id', $organisation->id)->value('footer_html');
        $this->assertSame('<p>Volg ons</p>', $stored);
    }

    public function testEmptyFooterStaysEmpty()
    {
        $organisation = $this->createOrganisation();
        $organisation->footer_html = null;
        $organisation->save();

        $this->assertNull($organisation->fresh()->footer_html);
    }

    public function testMigrationCleansStoredFooters()
    {
        $organisation = $this->createOrganisation();
        DB::table('organisations')->where('id', $organisation->id)
            ->update([ 'footer_html' => '<p>Ok</p><script>alert(1)</script>' ]);

        require_once base_path('database/migrations/2026_10_01_100500_sanitise_organisation_footer_html.php');
        (new \SanitiseOrganisationFooterHtml())->up();

        $this->assertSame(
            '<p>Ok</p>',
            DB::table('organisations')->where('id', $organisation->id)->value('footer_html')
        );
    }
}
