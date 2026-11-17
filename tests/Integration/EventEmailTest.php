<?php

namespace Tests\Integration;

use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\EventEmail;
use App\Models\Organisation;
use App\Models\User;
use App\Services\EmailTemplates;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * Admins choose, per event and per mail, between the organisation's version,
 * a library template and the event's own text.
 */
class EventEmailTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    /** @var Organisation */
    private $organisation;

    /** @var User */
    private $admin;

    /** @var Event */
    private $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = $this->createOrganisation();
        $this->admin = $this->adminOf($this->organisation);
        $this->event = $this->createEvent($this->organisation);
    }

    private function adminOf(Organisation $organisation): User
    {
        $admin = $this->createUser();
        $admin->admin = true;
        $admin->save();

        $organisation->users()->attach($admin, [ 'role' => Organisation::ROLE_ADMIN ]);

        return $admin;
    }

    private function createLibraryTemplate(
        Organisation $organisation,
        string $type,
        string $name,
        string $content = '<p>Tekst van het sjabloon</p>'
    ): EmailTemplate {
        return $organisation->emailTemplates()->create([
            'type' => $type,
            'name' => $name,
            'subject' => 'Sjabloon {{ event.name }}',
            'content' => $content
        ]);
    }

    private function url(string $suffix = ''): string
    {
        return '/admin/events/' . $this->event->id . '/emails' . $suffix;
    }

    /**
     * Registers a guest for the event and returns the confirmation mail.
     *
     * @return array
     */
    private function sendConfirmation(): array
    {
        $category = $this->createTicketCategory($this->event, 10.0);
        $category->eventDates()->attach($this->event->eventDates()->first());

        $this->actingAs($this->admin)->post("/admin/events/{$this->event->id}/guests", [
            'name' => 'Famous Person',
            'email' => 'famous@example.com',
            'ticketCategory' => $category->id
        ]);

        $this->assertCount(1, $this->catlabApi->sendEmailCalls);
        $mail = $this->catlabApi->sendEmailCalls[0];
        $this->catlabApi->sendEmailCalls = [];

        return $mail;
    }

    public function testTheEventPageListsWhereEachMailComesFrom()
    {
        $template = $this->createLibraryTemplate($this->organisation, EmailTemplates::CANCELLATION, 'Droevig afscheid');

        $this->event->emails()->create([ 'type' => EmailTemplates::CANCELLATION, 'email_template_id' => $template->id ]);
        $this->event->emails()->create([
            'type' => EmailTemplates::CONFIRMATION,
            'subject' => 'Eigen',
            'content' => '<p>Eigen</p>'
        ]);

        $response = $this->actingAs($this->admin)->get($this->url());

        $response->assertStatus(200)
            ->assertSee('Inschrijving bevestigd')
            ->assertSee('Inschrijving geannuleerd')
            ->assertSee('Uitnodiging van de wachtlijst')
            ->assertDontSee('Uitnodiging voor een team')
            ->assertDontSee('Inschrijving bevestigd (thuisquiz)')
            ->assertSee('Eigen tekst')
            ->assertSee('Sjabloon: Droevig afscheid')
            ->assertSee('Standaard van de organisatie')
            ->assertSee($this->url('/' . EmailTemplates::CONFIRMATION));
    }

    public function testAnAdminGivesAnEventItsOwnText()
    {
        $this->actingAs($this->admin)->get($this->url('/' . EmailTemplates::CONFIRMATION))
            ->assertStatus(200)
            ->assertSee('Dit evenement heeft nog geen eigen tekst voor deze mail.')
            ->assertDontSee('Welke mail krijgt dit evenement?');

        $this->actingAs($this->admin)->post($this->url('/' . EmailTemplates::CONFIRMATION), [
            'subject' => 'Speciaal voor {{ event.name }}',
            'content' => '<p>{{ greeting }} Alleen voor dit evenement</p>'
        ])->assertRedirect(action('Admin\EventEmailController@index', [ $this->event->id ]));

        // The editor of an own text offers the way back.
        $this->actingAs($this->admin)->get($this->url('/' . EmailTemplates::CONFIRMATION))
            ->assertSee($this->url('/' . EmailTemplates::CONFIRMATION . '/reset'));

        $eventEmail = $this->event->emails()->where('type', EmailTemplates::CONFIRMATION)->first();
        $this->assertNotNull($eventEmail);
        $this->assertNull($eventEmail->email_template_id);
        $this->assertTrue($eventEmail->hasOwnText());

        $mail = $this->sendConfirmation();
        $this->assertSame('Speciaal voor ' . $this->event->name, $mail['subject']);
        $this->assertStringContainsString('Alleen voor dit evenement', $mail['body']);
    }

    public function testOwnTextNeedsASubjectAndContent()
    {
        $this->actingAs($this->admin)->post($this->url('/' . EmailTemplates::CONFIRMATION), [
            'subject' => '',
            'content' => ''
        ])->assertSessionHasErrors([ 'subject', 'content' ]);

        $this->assertEquals(0, EventEmail::count());
    }

    public function testAnAdminPicksALibraryTemplate()
    {
        $template = $this->createLibraryTemplate($this->organisation, EmailTemplates::CONFIRMATION, 'Thuisquiz bevestiging');

        $this->actingAs($this->admin)->get($this->url())
            ->assertStatus(200)
            ->assertSee('Thuisquiz bevestiging')
            ->assertSee($this->url('/' . EmailTemplates::CONFIRMATION . '/template'));

        // An earlier own text is replaced by the template.
        $this->event->emails()->create([
            'type' => EmailTemplates::CONFIRMATION,
            'subject' => 'Eigen',
            'content' => '<p>Eigen</p>'
        ]);

        $this->actingAs($this->admin)->post($this->url('/' . EmailTemplates::CONFIRMATION . '/template'), [
            'email_template_id' => $template->id
        ])->assertRedirect(action('Admin\EventEmailController@index', [ $this->event->id ]));

        $this->assertEquals(1, EventEmail::count());
        $eventEmail = $this->event->emails()->first();
        $this->assertEquals($template->id, $eventEmail->email_template_id);
        $this->assertNull($eventEmail->subject);
        $this->assertNull($eventEmail->content);

        $this->actingAs($this->admin)->get($this->url())->assertSee('Sjabloon: Thuisquiz bevestiging');
    }

    public function testChoosingTheOrganisationVersionRemovesTheEventsChoice()
    {
        $this->event->emails()->create([
            'type' => EmailTemplates::CONFIRMATION,
            'subject' => 'Eigen',
            'content' => '<p>Eigen</p>'
        ]);

        $this->actingAs($this->admin)->get($this->url())
            ->assertSee($this->url('/' . EmailTemplates::CONFIRMATION . '/reset'));

        $this->actingAs($this->admin)->post($this->url('/' . EmailTemplates::CONFIRMATION . '/reset'))->assertRedirect(action('Admin\EventEmailController@index', [ $this->event->id ]));

        $this->assertEquals(0, EventEmail::count());
    }

    public function testATemplateOfAnotherOrganisationOrTypeIsRefused()
    {
        $other = $this->createOrganisation();
        $foreign = $this->createLibraryTemplate($other, EmailTemplates::CONFIRMATION, 'Van een ander');
        $wrongType = $this->createLibraryTemplate($this->organisation, EmailTemplates::CANCELLATION, 'Annulatie');

        $this->actingAs($this->admin)->post($this->url('/' . EmailTemplates::CONFIRMATION . '/template'), [
            'email_template_id' => $foreign->id
        ])->assertSessionHasErrors('email_template_id');

        $this->actingAs($this->admin)->post($this->url('/' . EmailTemplates::CONFIRMATION . '/template'), [
            'email_template_id' => $wrongType->id
        ])->assertSessionHasErrors('email_template_id');

        $this->assertEquals(0, EventEmail::count());
    }

    public function testAnAdminOfAnotherOrganisationGetsNotFound()
    {
        $other = $this->createOrganisation();
        $stranger = $this->adminOf($other);

        $this->actingAs($stranger)->get($this->url())->assertStatus(404);
        $this->actingAs($stranger)->get($this->url('/' . EmailTemplates::CONFIRMATION))->assertStatus(404);
        $this->actingAs($stranger)->get($this->url('/' . EmailTemplates::CONFIRMATION . '/preview'))->assertStatus(404);
        $this->actingAs($stranger)->post($this->url('/' . EmailTemplates::CONFIRMATION), [
            'subject' => 'x',
            'content' => 'x'
        ])->assertStatus(404);
        $this->actingAs($stranger)->post($this->url('/' . EmailTemplates::CONFIRMATION . '/reset'))->assertStatus(404);

        $this->assertEquals(0, EventEmail::count());
    }

    public function testAnEventOfANonActiveOrganisationGetsNotFound()
    {
        $other = $this->createOrganisation();
        $otherEvent = $this->createEvent($other);
        $other->users()->attach($this->admin, [ 'role' => Organisation::ROLE_ADMIN ]);

        // Admin of both, but the first organisation is the active one.
        $this->withSession([ 'activeOrganisationId' => $this->organisation->id ]);

        $base = '/admin/events/' . $otherEvent->id . '/emails';
        $this->actingAs($this->admin)->get($base)->assertStatus(404);
        $this->actingAs($this->admin)->get($base . '/' . EmailTemplates::CONFIRMATION)->assertStatus(404);
        $this->actingAs($this->admin)->get($base . '/' . EmailTemplates::CONFIRMATION . '/preview')->assertStatus(404);

        $this->actingAs($this->admin)->get($this->url())->assertStatus(200);
    }

    public function testTheGroupInviteHasNoEventPage()
    {
        $this->actingAs($this->admin)->get($this->url('/' . EmailTemplates::GROUP_INVITE))->assertStatus(404);
    }

    public function testTheEventPreviewUsesTheRealEvent()
    {
        $venue = $this->createVenue($this->admin);
        $this->event->venue()->associate($venue);
        $this->event->save();

        $response = $this->actingAs($this->admin)->get($this->url('/' . EmailTemplates::CONFIRMATION . '/preview'));

        $response->assertStatus(200)
            ->assertHeader('Content-Security-Policy', 'sandbox')
            ->assertSee($this->event->name)
            ->assertSee($venue->name)
            ->assertDontSee('Voorbeeldquiz');
    }

    public function testThePostedPreviewShowsTheUnsavedOwnText()
    {
        $this->event->emails()->create([
            'type' => EmailTemplates::CONFIRMATION,
            'subject' => 'Eigen',
            'content' => '<p>Opgeslagen eigen tekst</p>'
        ]);

        $preview = $this->url('/' . EmailTemplates::CONFIRMATION . '/preview');

        $this->actingAs($this->admin)->get($preview)
            ->assertSee('Opgeslagen eigen tekst');

        $this->actingAs($this->admin)->post($preview, [
            'subject' => 'Nieuw',
            'content' => '<p>Nog niet opgeslagen voor {{ event.name }}</p>'
        ])->assertStatus(200)
            ->assertHeader('Content-Security-Policy', 'sandbox')
            ->assertSee('Nog niet opgeslagen voor ' . $this->event->name, false);

        // Nothing was saved.
        $this->assertEquals('<p>Opgeslagen eigen tekst</p>', $this->event->emails()->first()->content);
    }

    public function testAnEventWithoutCampaignHasNoThuisquizMail()
    {
        $this->actingAs($this->admin)->get($this->url('/' . EmailTemplates::CONFIRMATION_PLAY_LINK))->assertStatus(404);
        $this->actingAs($this->admin)->post($this->url('/' . EmailTemplates::CONFIRMATION_PLAY_LINK), [
            'subject' => 'x',
            'content' => 'x'
        ])->assertStatus(404);

        $this->assertEquals(0, EventEmail::count());
    }

    public function testACampaignEventOnlyShowsTheThuisquizConfirmation()
    {
        $this->event->campaign_id = 12345;
        $this->event->save();

        $this->actingAs($this->admin)->get($this->url())
            ->assertStatus(200)
            ->assertSee('Inschrijving bevestigd (thuisquiz)')
            ->assertDontSee($this->url('/' . EmailTemplates::CONFIRMATION . '"'), false)
            ->assertSee('Inschrijving geannuleerd');

        $this->actingAs($this->admin)->get($this->url('/' . EmailTemplates::CONFIRMATION))->assertStatus(404);
        $this->actingAs($this->admin)->get($this->url('/' . EmailTemplates::CONFIRMATION_PLAY_LINK))->assertStatus(200);
    }

    public function testAChoiceForAMailTheEventNoLongerSendsCanBeRemoved()
    {
        $this->event->emails()->create([
            'type' => EmailTemplates::CONFIRMATION_PLAY_LINK,
            'subject' => 'Eigen',
            'content' => '<p>Eigen</p>'
        ]);

        $this->actingAs($this->admin)->get($this->url())
            ->assertSee('Inschrijving bevestigd (thuisquiz)')
            ->assertSee('Dit evenement verstuurt deze mail niet.')
            ->assertSee($this->url('/' . EmailTemplates::CONFIRMATION_PLAY_LINK . '/reset'));

        $this->actingAs($this->admin)->post($this->url('/' . EmailTemplates::CONFIRMATION_PLAY_LINK . '/reset'))
            ->assertRedirect(action('Admin\EventEmailController@index', [ $this->event->id ]));

        $this->assertEquals(0, EventEmail::count());

        // The flash message still names the mail, so look for its row.
        $this->actingAs($this->admin)->get($this->url())
            ->assertDontSee('Dit evenement verstuurt deze mail niet.')
            ->assertDontSee($this->url('/' . EmailTemplates::CONFIRMATION_PLAY_LINK . '/reset'));
    }

    public function testTheEventsMenuLinksToItsMails()
    {
        $this->actingAs($this->admin)->get('/admin/events')
            ->assertStatus(200)
            ->assertSee(action('Admin\EventEmailController@index', [ $this->event->id ]));
    }

    public function testTheLibraryLinksToTheEventsThatUseATemplate()
    {
        $template = $this->createLibraryTemplate($this->organisation, EmailTemplates::CONFIRMATION, 'Thuisquiz');
        $this->event->emails()->create([ 'type' => EmailTemplates::CONFIRMATION, 'email_template_id' => $template->id ]);

        $this->actingAs($this->admin)->get('/admin/emails/templates')
            ->assertStatus(200)
            ->assertSee('href="' . action('Admin\EventEmailController@index', [ $this->event->id ]) . '"', false);
    }

    public function testDeletingAnEventKeepsWorking()
    {
        $this->event->emails()->create([
            'type' => EmailTemplates::CONFIRMATION,
            'subject' => 'Eigen',
            'content' => '<p>Eigen</p>'
        ]);

        $this->assertTrue((bool) $this->event->delete());

        // event_dates has a restricting foreign key of its own (older than
        // this feature); only the mails must not stand in the way.
        \DB::table('event_dates')->where('event_id', $this->event->id)->delete();
        $this->assertTrue((bool) $this->event->forceDelete());

        $this->assertNull(Event::withTrashed()->find($this->event->id));
        $this->assertEquals(0, EventEmail::count());
    }

    public function testTheStartingContentIsTheVersionInUse()
    {
        $templates = app(EmailTemplates::class);
        $type = EmailTemplates::CONFIRMATION;

        // DEFAULT
        $this->assertSame(
            $templates->getDefaultContent($type),
            $templates->getStartingContent($this->organisation, $this->event, $type)
        );

        // ORGANISATION
        $this->organisation->emailTemplates()->create([
            'type' => $type,
            'name' => '',
            'subject' => 'Organisatie',
            'content' => '<p>Tekst van de organisatie</p>'
        ]);
        $this->assertSame(
            '<p>Tekst van de organisatie</p>',
            $templates->getStartingContent($this->organisation, $this->event, $type)
        );

        // LIBRARY
        $template = $this->createLibraryTemplate($this->organisation, $type, 'Thuisquiz', '<p>Tekst van het sjabloon</p>');
        $eventEmail = $this->event->emails()->create([ 'type' => $type, 'email_template_id' => $template->id ]);
        $this->assertSame(
            '<p>Tekst van het sjabloon</p>',
            $templates->getStartingContent($this->organisation, $this->event, $type)
        );

        // EVENT
        $eventEmail->update([ 'email_template_id' => null, 'subject' => 'Eigen', 'content' => '<p>Eigen tekst</p>' ]);
        $this->assertSame(
            '<p>Eigen tekst</p>',
            $templates->getStartingContent($this->organisation, $this->event, $type)
        );
    }
}
