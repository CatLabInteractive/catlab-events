<?php

namespace Tests\Integration;

use App\Models\EmailTemplate;
use App\Models\EventEmail;
use App\Models\Organisation;
use App\Models\User;
use App\Services\EmailTemplates;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * Admins keep a library of named mail templates that events can pick.
 */
class EmailLibraryTest extends IntegrationTestCase
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
        $this->admin->admin = true;
        $this->admin->save();

        $this->organisation->users()->attach($this->admin, [ 'role' => Organisation::ROLE_ADMIN ]);
    }

    private function createTemplate(Organisation $organisation, string $name = 'Warm welcome'): EmailTemplate
    {
        $template = new EmailTemplate([
            'type' => EmailTemplates::CONFIRMATION,
            'name' => $name,
            'subject' => 'Welkom',
            'content' => '<p>Hallo</p>'
        ]);
        $template->organisation()->associate($organisation);
        $template->save();

        return $template;
    }

    private function useTemplate(EmailTemplate $template, $event): EventEmail
    {
        $eventEmail = new EventEmail([ 'type' => $template->type, 'email_template_id' => $template->id ]);
        $eventEmail->event()->associate($event);
        $eventEmail->save();

        return $eventEmail;
    }

    public function testAnAdminCreatesEditsAndDeletesATemplate()
    {
        $this->actingAs($this->admin)->get('/admin/emails/templates')->assertStatus(200);
        $this->actingAs($this->admin)->get('/admin/emails/templates/create?type=' . EmailTemplates::CANCELLATION)
            ->assertStatus(200)
            ->assertSee('name="fields[name]"', false);

        $this->actingAs($this->admin)->post('/admin/emails/templates', [ 'fields' => [
            'type' => EmailTemplates::CONFIRMATION,
            'name' => 'Warm welcome',
            'subject' => 'Welkom!',
            'content' => '<p>Tot dan</p>'
        ] ])->assertRedirect(action('Admin\EmailLibraryController@index'));

        $template = $this->organisation->emailTemplates()->library()->first();
        $this->assertNotNull($template);
        $this->assertEquals('Warm welcome', $template->name);

        $this->actingAs($this->admin)->get("/admin/emails/templates/{$template->id}/edit")
            ->assertStatus(200)
            ->assertSee('Warm welcome');

        $this->actingAs($this->admin)->put("/admin/emails/templates/{$template->id}", [ 'fields' => [
            'name' => 'Warmer welcome',
            'subject' => 'Nieuw onderwerp',
            'content' => '<p>Nieuw</p>'
        ] ])->assertRedirect(action('Admin\EmailLibraryController@index'));

        $template->refresh();
        $this->assertEquals('Warmer welcome', $template->name);
        $this->assertEquals('Nieuw onderwerp', $template->subject);
        $this->assertEquals(EmailTemplates::CONFIRMATION, $template->type);

        $this->actingAs($this->admin)->get('/admin/emails/templates')->assertSee('Warmer welcome');

        $this->actingAs($this->admin)->delete("/admin/emails/templates/{$template->id}")
            ->assertRedirect(action('Admin\EmailLibraryController@index'));
        $this->assertNull(EmailTemplate::find($template->id));
    }

    public function testATemplateNameIsUniquePerType()
    {
        $this->createTemplate($this->organisation);

        $this->actingAs($this->admin)->post('/admin/emails/templates', [ 'fields' => [
            'type' => EmailTemplates::CONFIRMATION,
            'name' => 'Warm welcome',
            'subject' => 'Welkom',
            'content' => '<p>x</p>'
        ] ])->assertSessionHas('message', function ($message) {
            return (string) $message === 'Er is al een sjabloon met deze naam voor deze mail.';
        });
        $this->assertEquals(1, $this->organisation->emailTemplates()->library()->count());

        // Same name for another type is fine.
        $this->actingAs($this->admin)->post('/admin/emails/templates', [ 'fields' => [
            'type' => EmailTemplates::CANCELLATION,
            'name' => 'Warm welcome',
            'subject' => 'Welkom',
            'content' => '<p>x</p>'
        ] ])->assertRedirect(action('Admin\EmailLibraryController@index'));
        $this->assertEquals(2, $this->organisation->emailTemplates()->library()->count());
    }

    public function testAGroupInviteTemplateCannotBeCreated()
    {
        $this->actingAs($this->admin)->post('/admin/emails/templates', [ 'fields' => [
            'type' => EmailTemplates::GROUP_INVITE,
            'name' => 'Invite',
            'subject' => 'Welkom',
            'content' => '<p>x</p>'
        ] ])->assertSessionHas('message');

        $this->assertEquals(0, $this->organisation->emailTemplates()->library()->count());
    }

    public function testATemplateInUseCannotBeDeleted()
    {
        $template = $this->createTemplate($this->organisation);
        $event = $this->createEvent($this->organisation);
        $this->useTemplate($template, $event);

        $this->actingAs($this->admin)->delete("/admin/emails/templates/{$template->id}")
            ->assertSessionHas('message', function ($message) {
                return (string) $message === 'Dit sjabloon wordt nog gebruikt door: Test quiz night.';
            });

        $this->assertNotNull(EmailTemplate::find($template->id));
    }

    public function testATemplateUsedOnlyByADeletedEventCanBeDeleted()
    {
        $template = $this->createTemplate($this->organisation);
        $event = $this->createEvent($this->organisation);
        $this->useTemplate($template, $event);
        $event->delete();

        $this->actingAs($this->admin)->delete("/admin/emails/templates/{$template->id}")
            ->assertRedirect(action('Admin\EmailLibraryController@index'));

        $this->assertNull(EmailTemplate::find($template->id));
        $this->assertEquals(0, EventEmail::where('email_template_id', $template->id)->count());
    }

    public function testAnotherOrganisationsTemplateIsNotFound()
    {
        $other = new Organisation();
        $other->name = 'Other';
        $other->save();
        $template = $this->createTemplate($other);

        $this->actingAs($this->admin)->get("/admin/emails/templates/{$template->id}/edit")->assertStatus(404);
        $this->actingAs($this->admin)->put("/admin/emails/templates/{$template->id}", [ 'fields' => [
            'name' => 'Hijack', 'subject' => 'x', 'content' => '<p>x</p>'
        ] ])->assertStatus(404);
        $this->actingAs($this->admin)->delete("/admin/emails/templates/{$template->id}")->assertStatus(404);

        // Over the api the policy answers.
        $this->actingAs($this->admin)->putJson("/api/v1/emailTemplates/{$template->id}", [ 'name' => 'Hijack' ])->assertStatus(403);
        $this->actingAs($this->admin)->deleteJson("/api/v1/emailTemplates/{$template->id}")->assertStatus(403);
        $this->actingAs($this->admin)->get("/admin/emails/templates/{$template->id}/preview")->assertStatus(404);

        $this->assertEquals('Warm welcome', $template->fresh()->name);
        $this->assertNotNull(EmailTemplate::find($template->id));
    }

    public function testTheOrganisationsOwnVersionOfAMailIsNotALibraryTemplate()
    {
        $own = new EmailTemplate([
            'type' => EmailTemplates::CONFIRMATION, 'name' => '', 'subject' => 'Eigen', 'content' => '<p>Eigen</p>'
        ]);
        $own->organisation()->associate($this->organisation);
        $own->save();

        $this->actingAs($this->admin)->get("/admin/emails/templates/{$own->id}/edit")->assertStatus(404);
        $this->actingAs($this->admin)->delete("/admin/emails/templates/{$own->id}")->assertStatus(404);
        $this->actingAs($this->admin)->getJson("/api/v1/emailTemplates/{$own->id}")->assertStatus(404);
        $this->actingAs($this->admin)->patchJson("/api/v1/emailTemplates/{$own->id}", [ 'name' => 'x' ])->assertStatus(404);
        $this->actingAs($this->admin)->deleteJson("/api/v1/emailTemplates/{$own->id}")->assertStatus(404);

        $this->assertSame('', $own->fresh()->name);
    }

    public function testTheLibraryIsListedByName()
    {
        foreach ([ 'Zebra', 'Alfa', 'Midden' ] as $name) {
            $this->createTemplate($this->organisation, $name);
        }

        $this->actingAs($this->admin)->get('/admin/emails/templates')
            ->assertSeeInOrder([ 'Alfa', 'Midden', 'Zebra' ]);
    }

    public function testTheLibraryApiIsForAdminsOfTheOrganisation()
    {
        $template = $this->createTemplate($this->organisation);
        $base = '/api/v1/organisations/' . $this->organisation->id . '/emailTemplates';

        $member = $this->createUser();
        $member->admin = true;
        $member->save();
        $this->organisation->users()->attach($member, [ 'role' => 1 ]);

        $this->actingAs($member)->getJson($base)->assertStatus(403);
        $this->actingAs($member)->postJson($base, [
            'type' => EmailTemplates::CONFIRMATION, 'name' => 'X', 'subject' => 'x', 'content' => '<p>x</p>'
        ])->assertStatus(403);
        $this->actingAs($member)->getJson("/api/v1/emailTemplates/{$template->id}")->assertStatus(403);
        $this->actingAs($member)->deleteJson("/api/v1/emailTemplates/{$template->id}")->assertStatus(403);
        $this->actingAs($member)->getJson('/api/v1/organisations/' . $this->organisation->id . '/standardMails')->assertStatus(403);
        $this->actingAs($member)->getJson('/api/v1/organisations/' . $this->organisation->id . '/emailLayout')->assertStatus(403);

        $this->actingAs($this->admin)->getJson($base)->assertStatus(200)->assertJsonPath('items.0.name', 'Warm welcome');
        $this->actingAs($this->admin)->deleteJson($base)->assertStatus(405);

        // The mail a template is for is fixed.
        $this->actingAs($this->admin)->putJson("/api/v1/emailTemplates/{$template->id}", [
            'type' => EmailTemplates::CANCELLATION, 'name' => 'Warm welcome', 'subject' => 'Welkom', 'content' => '<p>Hallo</p>'
        ]);
        $this->assertSame(EmailTemplates::CONFIRMATION, $template->fresh()->type);
    }

    public function testTheLibraryPreviewIsSandboxed()
    {
        $template = $this->createTemplate($this->organisation);

        $stored = $this->actingAs($this->admin)->get("/admin/emails/templates/{$template->id}/preview");
        $stored->assertStatus(200);
        $stored->assertHeader('Content-Security-Policy', 'sandbox');

        $draft = $this->actingAs($this->admin)->put("/admin/emails/templates/{$template->id}/preview", [ 'fields' => [
            'subject' => 'Draft', 'content' => '<p>Concept tekst</p>'
        ] ]);
        $draft->assertStatus(200);
        $draft->assertHeader('Content-Security-Policy', 'sandbox');
        $this->assertStringContainsString('Concept tekst', $draft->getContent());

        $new = $this->actingAs($this->admin)->post('/admin/emails/templates/create/preview?type=' . EmailTemplates::CONFIRMATION, [ 'fields' => [
            'type' => EmailTemplates::CONFIRMATION, 'subject' => 'Draft', 'content' => '<p>Nieuw concept</p>'
        ] ]);
        $new->assertStatus(200);
        $new->assertHeader('Content-Security-Policy', 'sandbox');
        $this->assertStringContainsString('Nieuw concept', $new->getContent());
    }

    public function testTheNewTemplatePageEmbedsAWorkingInitialPreview()
    {
        $page = $this->actingAs($this->admin)->get('/admin/emails/templates/create?type=' . EmailTemplates::CONFIRMATION);
        $page->assertStatus(200);

        $this->assertSame(1, preg_match('/<iframe[^>]*id="email-preview"[^>]*\ssrc="([^"]+)"/s', $page->getContent(), $match));
        $src = html_entity_decode($match[1]);

        $preview = $this->actingAs($this->admin)->get($src);
        $preview->assertStatus(200);
        $preview->assertHeader('Content-Security-Policy', 'sandbox');
    }
}
