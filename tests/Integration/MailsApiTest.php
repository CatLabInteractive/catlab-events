<?php

namespace Tests\Integration;

use App\Models\EmailTemplate;
use App\Models\Organisation;
use App\Models\User;
use App\Services\EmailTemplates;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * The automatic mails over the api: the organisation's versions
 * (standardMails), its layout (emailLayout) and each event's choice (mails).
 */
class MailsApiTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    /** @var Organisation */
    private $organisation;

    /** @var User */
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = $this->createOrganisation();
        $this->admin = $this->createUser();
        $this->organisation->users()->attach($this->admin, [ 'role' => Organisation::ROLE_ADMIN ]);
    }

    private function stranger(): User
    {
        return $this->createUser();
    }

    public function testStandardMailsListEveryMailAndFollowTheStandardUntilWritten()
    {
        $base = '/api/v1/organisations/' . $this->organisation->id . '/standardMails';

        $this->getJson($base)->assertStatus(401);
        $this->actingAs($this->stranger())->getJson($base)->assertStatus(403);

        $list = $this->actingAs($this->admin)->getJson($base)->assertStatus(200);
        $this->assertEquals(EmailTemplates::ALL_TYPES, array_column($list->json('items'), 'type'));
        $this->assertEquals('Standaard', $list->json('items.0.version'));

        $filtered = $this->actingAs($this->admin)->getJson($base . '?type=' . EmailTemplates::GROUP_INVITE)->assertStatus(200);
        $this->assertEquals([ EmailTemplates::GROUP_INVITE ], array_column($filtered->json('items'), 'type'));
        $sorted = $this->actingAs($this->admin)->getJson($base . '?sort=type')->assertStatus(200);
        $this->assertEquals(EmailTemplates::CANCELLATION, $sorted->json('items.0.type'));

        $url = $base . '/' . EmailTemplates::CANCELLATION;
        $view = $this->actingAs($this->admin)->getJson($url)->assertStatus(200);
        $view->assertJsonPath('customised', false);
        $this->assertEquals(app(EmailTemplates::class)->getDefaultSubject(EmailTemplates::CANCELLATION), $view->json('subject'));
        $this->assertEquals(0, EmailTemplate::count());

        $this->actingAs($this->admin)->putJson($url, [ 'subject' => '', 'content' => '<p>x</p>' ])->assertStatus(422);
        $this->assertEquals(0, EmailTemplate::count());

        $this->actingAs($this->admin)->putJson($url, [ 'subject' => 'Jammer', 'content' => '<p>Tot later</p>' ])
            ->assertStatus(200)
            ->assertJsonPath('customised', true)
            ->assertJsonPath('version', 'Aangepast')
            ->assertJsonPath('subject', 'Jammer');

        $override = app(EmailTemplates::class)->getOverride($this->organisation, EmailTemplates::CANCELLATION);
        $this->assertEquals('<p>Tot later</p>', $override->content);

        // Writing again changes the same version.
        $this->actingAs($this->admin)->putJson($url, [ 'subject' => 'Jammer!', 'content' => '<p>Tot later</p>' ])->assertStatus(200);
        $this->assertEquals(1, EmailTemplate::count());

        $this->actingAs($this->stranger())->putJson($url, [ 'subject' => 'Hijack', 'content' => '<p>x</p>' ])->assertStatus(403);
        $this->actingAs($this->stranger())->deleteJson($url)->assertStatus(403);

        $this->actingAs($this->admin)->deleteJson($url)->assertStatus(200);
        $this->assertEquals(0, EmailTemplate::count());
        $this->actingAs($this->admin)->getJson($url)->assertJsonPath('customised', false);

        $this->actingAs($this->admin)->getJson($base . '/nonsense')->assertStatus(404);
    }

    public function testAStandardMailLeavesTheLibraryAlone()
    {
        $template = new EmailTemplate([
            'type' => EmailTemplates::CONFIRMATION, 'name' => 'Warm', 'subject' => 'Warm', 'content' => '<p>Warm</p>'
        ]);
        $template->organisation()->associate($this->organisation);
        $template->save();

        $url = '/api/v1/organisations/' . $this->organisation->id . '/standardMails/' . EmailTemplates::CONFIRMATION;

        $this->actingAs($this->admin)->getJson($url)->assertJsonPath('customised', false);
        $this->actingAs($this->admin)->putJson($url, [ 'subject' => 'Eigen', 'content' => '<p>Eigen</p>' ])->assertStatus(200);
        $this->actingAs($this->admin)->deleteJson($url)->assertStatus(200);

        $this->assertEquals('Warm', $template->fresh()->subject);
    }

    public function testTheLayoutIsReadAndWritten()
    {
        $url = '/api/v1/organisations/' . $this->organisation->id . '/emailLayout';

        $this->actingAs($this->stranger())->getJson($url)->assertStatus(403);
        $this->actingAs($this->admin)->getJson($url)
            ->assertStatus(200)
            ->assertJsonPath('accent_color', null)
            ->assertJsonPath('custom_html', null);

        $this->actingAs($this->admin)->putJson($url, [ 'accent_color' => 'red' ])->assertStatus(422);
        $this->actingAs($this->admin)->putJson($url, [ 'custom_html' => '<p>No placeholder</p>' ])->assertStatus(422);
        $this->assertNull($this->organisation->emailLayout()->first());

        $this->actingAs($this->admin)->putJson($url, [
            'accent_color' => '#112233',
            'footer_text' => 'Tot ziens',
            'custom_html' => "<div>{{ content }}</div>"
        ])->assertStatus(200)->assertJsonPath('accent_color', '#112233');

        $layout = $this->organisation->emailLayout()->first();
        $this->assertEquals('Tot ziens', $layout->footer_text);
        $this->assertEquals('<div>{{ content }}</div>', $layout->custom_html);

        // The standard layout itself is not stored.
        $this->actingAs($this->admin)->patchJson($url, [
            'custom_html' => app(\App\Services\EmailLayouts::class)->getDefaultHtml()
        ])->assertStatus(200)->assertJsonPath('custom_html', null)->assertJsonPath('accent_color', '#112233');

        $this->actingAs($this->stranger())->putJson($url, [ 'footer_text' => 'Hijack' ])->assertStatus(403);
        $this->assertEquals('Tot ziens', $layout->fresh()->footer_text);
    }

    public function testAnEventChoosesATemplateOrAnOwnText()
    {
        $event = $this->createEvent($this->organisation);
        $base = '/api/v1/events/' . $event->id . '/mails';

        $this->actingAs($this->stranger())->getJson($base)->assertStatus(403);

        $list = $this->actingAs($this->admin)->getJson($base)->assertStatus(200);
        $this->assertEquals(EmailTemplates::getEventTypes($event), array_column($list->json('items'), 'type'));
        $this->assertEquals('Standaard van de organisatie', $list->json('items.0.version'));

        $template = new EmailTemplate([
            'type' => EmailTemplates::CONFIRMATION, 'name' => 'Warm', 'subject' => 'Warm onderwerp', 'content' => '<p>Warm</p>'
        ]);
        $template->organisation()->associate($this->organisation);
        $template->save();

        $url = $base . '/' . EmailTemplates::CONFIRMATION;

        // A template of another mail is refused.
        $other = new EmailTemplate([
            'type' => EmailTemplates::CANCELLATION, 'name' => 'Koud', 'subject' => 'Koud', 'content' => '<p>Koud</p>'
        ]);
        $other->organisation()->associate($this->organisation);
        $other->save();
        $this->actingAs($this->admin)->putJson($url, [ 'email_template_id' => $other->id ])->assertStatus(422);
        $this->assertEquals(0, $event->emails()->count());

        $this->actingAs($this->admin)->putJson($url, [ 'email_template_id' => $template->id, 'subject' => 'Ignored', 'content' => '<p>Ignored</p>' ])
            ->assertStatus(200)
            ->assertJsonPath('version', 'Sjabloon: Warm')
            ->assertJsonPath('subject', 'Warm onderwerp');

        $choice = $event->emails()->first();
        $this->assertEquals($template->id, $choice->email_template_id);
        $this->assertNull($choice->subject);

        $this->actingAs($this->admin)->putJson($url, [ 'email_template_id' => null, 'subject' => 'Eigen', 'content' => '<p>Eigen</p>' ])
            ->assertStatus(200)
            ->assertJsonPath('version', 'Eigen tekst')
            ->assertJsonPath('email_template_id', null);

        $choice->refresh();
        $this->assertNull($choice->email_template_id);
        $this->assertEquals('Eigen', $choice->subject);
        $this->assertEquals(1, $event->emails()->count());

        $this->actingAs($this->admin)->putJson($url, [ 'email_template_id' => null, 'subject' => 'Eigen', 'content' => '' ])->assertStatus(422);

        $this->actingAs($this->stranger())->deleteJson($url)->assertStatus(403);
        $this->actingAs($this->admin)->deleteJson($url)->assertStatus(200);
        $this->assertEquals(0, $event->emails()->count());

        // A mail this event doesn't send.
        $hidden = array_values(array_diff(EmailTemplates::EVENT_TYPES, EmailTemplates::getEventTypes($event)))[0];
        $this->actingAs($this->admin)->getJson($base . '/' . $hidden)->assertStatus(404);
        $this->actingAs($this->admin)->getJson($base . '/' . EmailTemplates::GROUP_INVITE)->assertStatus(404);
    }
}
