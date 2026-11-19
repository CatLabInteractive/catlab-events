<?php

namespace Tests\Integration;

use App\Models\EmailTemplate;
use App\Models\Organisation;
use App\Services\EmailTemplates;
use App\Services\MailHtml;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * Mail HTML an admin writes is cleaned when it is saved, and stored HTML is
 * cleaned before the editor shows it.
 */
class MailHtmlTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    const DIRTY = '<p onclick="steal()">{{ greeting }} <a href="{{ team.url }}" style="color:#ff0000">je team</a>'
        . '<a href="javascript:steal()">klik</a><script>steal()</script><img src="x" onerror="steal()"></p>';

    private $organisation;
    private $admin;
    private $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = $this->createOrganisation();
        $this->admin = $this->createUser();
        $this->admin->admin = true;
        $this->admin->save();
        $this->organisation->users()->attach($this->admin, [ 'role' => Organisation::ROLE_ADMIN ]);
        $this->event = $this->createEvent($this->organisation);
    }

    private function assertClean(string $html)
    {
        $this->assertStringNotContainsString('steal', $html);
        $this->assertStringNotContainsString('<script', $html);
        // Formatting and placeholders stay, also inside a link.
        $this->assertStringContainsString('{{ greeting }}', $html);
        $this->assertStringContainsString('<a href="{{ team.url }}" style="color:#ff0000;">je team</a>', $html);
    }

    public function testTheStandardTextsAreLeftAsTheyAre()
    {
        $normalise = function ($html) {
            return preg_replace('/\s+/', '', str_replace([ '<br />', '<br/>' ], '<br>', $html));
        };

        foreach (EmailTemplates::ALL_TYPES as $type) {
            $default = app(EmailTemplates::class)->getDefaultContent($type);
            $this->assertSame($normalise($default), $normalise(MailHtml::clean($default)), $type);
        }
    }

    public function testManyPlaceholdersComeBackInTheirOwnPlace()
    {
        $html = '';
        for ($i = 0; $i < 12; $i++) {
            $html .= '<p>{{ field_' . chr(97 + $i) . ' }}</p>';
        }

        $this->assertSame($html, MailHtml::clean($html));
    }

    public function testAStandardMailIsCleanedOnSave()
    {
        $this->actingAs($this->admin)->put('/admin/emails/' . EmailTemplates::CONFIRMATION, [ 'fields' => [
            'subject' => 'Welkom',
            'content' => self::DIRTY
        ] ])->assertSessionHasNoErrors();

        $this->assertClean(app(EmailTemplates::class)->getOverride($this->organisation, EmailTemplates::CONFIRMATION)->content);
    }

    public function testALibraryTemplateIsCleanedOnSave()
    {
        $this->actingAs($this->admin)->post('/admin/emails/templates', [ 'fields' => [
            'type' => EmailTemplates::CONFIRMATION,
            'name' => 'Warm welcome',
            'subject' => 'Welkom',
            'content' => self::DIRTY
        ] ])->assertSessionHasNoErrors();

        $this->assertClean($this->organisation->emailTemplates()->library()->first()->content);
    }

    public function testAnEventsOwnTextIsCleanedOnSave()
    {
        $this->actingAs($this->admin)->put('/admin/events/' . $this->event->id . '/emails/' . EmailTemplates::CONFIRMATION, [ 'fields' => [
            'email_template_id' => '',
            'subject' => 'Welkom',
            'content' => self::DIRTY
        ] ])->assertSessionHasNoErrors();

        $this->assertClean($this->event->emails()->first()->content);
    }

    public function testOnlyAScriptIsNoContent()
    {
        $this->actingAs($this->admin)->put('/admin/emails/' . EmailTemplates::CONFIRMATION, [ 'fields' => [
            'subject' => 'Welkom',
            'content' => '<script>steal()</script>'
        ] ])->assertSessionHasErrors('fields.content');

        $this->assertNull(app(EmailTemplates::class)->getOverride($this->organisation, EmailTemplates::CONFIRMATION));
    }

    public function testTheEditorCleansWhatWasStoredBefore()
    {
        $template = new EmailTemplate([
            'type' => EmailTemplates::CONFIRMATION,
            'name' => '',
            'subject' => 'Welkom',
            'content' => self::DIRTY
        ]);
        $template->organisation()->associate($this->organisation);
        $template->save();

        $html = $this->actingAs($this->admin)->get('/admin/emails/' . EmailTemplates::CONFIRMATION . '/edit')
            ->assertStatus(200)
            ->getContent();

        $this->assertStringNotContainsString('steal', $html);
        $this->assertStringContainsString('je team</a>', $html);
    }
}
