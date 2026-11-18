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

        // The charon-frontend form posts every field as fields[name].
        $field = function ($value) {
            return $value;
        };

        $this->actingAs($admin)
            ->put('/admin/organisations/' . $organisation->id, [
                'fields' => [
                    'name' => $field($organisation->name),
                    // Fields left out of the form are saved as empty.
                    'private_events_count' => $field((int) $organisation->private_events_count),
                    'footer_html' => $field('<p>Volg ons</p><script>alert(document.cookie)</script><img src="x" onerror="alert(1)">'),
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHas('message', 'Opgeslagen.');

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

    public function testStoredFootersAreCleanedOnDisplayNotRewritten()
    {
        $organisation = $this->createOrganisation();
        DB::table('organisations')->where('id', $organisation->id)
            ->update([ 'footer_html' => '<p>Ok</p><script>alert(1)</script>' ]);

        $this->assertSame('<p>Ok</p>', $organisation->fresh()->getSafeFooterHtml());

        // The stored value is left alone (the database is shared with production).
        $this->assertSame(
            '<p>Ok</p><script>alert(1)</script>',
            DB::table('organisations')->where('id', $organisation->id)->value('footer_html')
        );
    }
}
