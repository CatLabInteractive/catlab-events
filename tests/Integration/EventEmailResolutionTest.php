<?php

namespace Tests\Integration;

use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use App\Services\EmailTemplates;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * Which version of a mail goes out. The first one that exists wins: the
 * event's own text, the library template picked for the event, the
 * organisation's version, the default.
 */
class EventEmailResolutionTest extends IntegrationTestCase
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

    private function createOrganisationVersion(string $type, string $subject, string $content): EmailTemplate
    {
        return $this->organisation->emailTemplates()->create([
            'type' => $type,
            'name' => '',
            'subject' => $subject,
            'content' => $content
        ]);
    }

    private function createLibraryTemplate(string $type, string $name, string $subject, string $content): EmailTemplate
    {
        return $this->organisation->emailTemplates()->create([
            'type' => $type,
            'name' => $name,
            'subject' => $subject,
            'content' => $content
        ]);
    }

    /**
     * Registers a guest for the event and returns the confirmation mail.
     *
     * @param Event $event
     * @return array
     */
    private function sendConfirmation(Event $event): array
    {
        $category = $this->createTicketCategory($event, 10.0);
        $category->eventDates()->attach($event->eventDates()->first());

        $this->actingAs($this->admin)->post("/admin/events/{$event->id}/guests", [
            'name' => 'Famous Person',
            'email' => 'famous@example.com',
            'ticketCategory' => $category->id
        ]);

        $this->assertCount(1, $this->catlabApi->sendEmailCalls);
        $mail = $this->catlabApi->sendEmailCalls[0];
        $this->catlabApi->sendEmailCalls = [];

        return $mail;
    }

    public function testTheEventsOwnTextWinsOverEverything()
    {
        $event = $this->createEvent($this->organisation);

        $this->createOrganisationVersion(EmailTemplates::CONFIRMATION, 'Organisatie', '<p>Tekst van de organisatie</p>');
        $library = $this->createLibraryTemplate(
            EmailTemplates::CANCELLATION,
            'Annulatie',
            'Sjabloon',
            '<p>Tekst van het sjabloon</p>'
        );

        $event->emails()->create([ 'type' => EmailTemplates::CANCELLATION, 'email_template_id' => $library->id ]);
        $event->emails()->create([
            'type' => EmailTemplates::CONFIRMATION,
            'subject' => 'Eigen onderwerp voor {{ event.name }}',
            'content' => '<p>{{ greeting }} Eigen tekst voor dit evenement</p>'
        ]);

        $mail = $this->sendConfirmation($event);

        $this->assertSame('Eigen onderwerp voor ' . $event->name, $mail['subject']);
        $this->assertStringContainsString('Hallo! Eigen tekst voor dit evenement', $mail['body']);
        $this->assertStringNotContainsString('Tekst van de organisatie', $mail['body']);
        $this->assertStringNotContainsString('Tekst van het sjabloon', $mail['body']);
    }

    public function testALibraryTemplateWinsOverTheOrganisationVersion()
    {
        $event = $this->createEvent($this->organisation);

        $this->createOrganisationVersion(EmailTemplates::CONFIRMATION, 'Organisatie', '<p>Tekst van de organisatie</p>');
        $library = $this->createLibraryTemplate(
            EmailTemplates::CONFIRMATION,
            'Thuisquiz',
            'Sjabloon {{ event.name }}',
            '<p>Tekst van het sjabloon</p>'
        );

        $event->emails()->create([ 'type' => EmailTemplates::CONFIRMATION, 'email_template_id' => $library->id ]);

        $mail = $this->sendConfirmation($event);

        $this->assertSame('Sjabloon ' . $event->name, $mail['subject']);
        $this->assertStringContainsString('Tekst van het sjabloon', $mail['body']);
        $this->assertStringNotContainsString('Tekst van de organisatie', $mail['body']);
    }

    public function testTheOldConfirmationEmailFieldNoLongerChangesTheMail()
    {
        $event = $this->createEvent($this->organisation);
        $event->confirmation_email = 'emails.tickets.confirmation';
        $event->save();

        $this->createOrganisationVersion(EmailTemplates::CONFIRMATION, 'Organisatie', '<p>Tekst van de organisatie</p>');

        $mail = $this->sendConfirmation($event);

        $this->assertSame('Organisatie', $mail['subject']);
        $this->assertStringContainsString('Tekst van de organisatie', $mail['body']);
        $this->assertStringNotContainsString('We zijn er bij!', $mail['body']);
    }

    public function testTheOrganisationVersionGoesOutWhenTheEventHasNothing()
    {
        $event = $this->createEvent($this->organisation);

        $this->createOrganisationVersion(
            EmailTemplates::CONFIRMATION,
            'Organisatie {{ event.name }}',
            '<p>Tekst van de organisatie</p>'
        );

        $mail = $this->sendConfirmation($event);

        $this->assertSame('Organisatie ' . $event->name, $mail['subject']);
        $this->assertStringContainsString('Tekst van de organisatie', $mail['body']);
    }

    public function testTheDefaultGoesOutWhenNobodyChangedAnything()
    {
        $event = $this->createEvent($this->organisation);

        $mail = $this->sendConfirmation($event);

        $this->assertSame($event->name . ': We zijn er bij!', $mail['subject']);
        $this->assertStringContainsString('We zijn er bij!', $mail['body']);
    }

    public function testAnotherEventsTextDoesNotLeak()
    {
        $eventA = $this->createEvent($this->organisation);
        $eventA->emails()->create([
            'type' => EmailTemplates::CONFIRMATION,
            'subject' => 'Alleen voor A',
            'content' => '<p>Tekst van evenement A</p>'
        ]);

        $eventB = $this->createEvent($this->organisation);
        $eventB->name = 'Quiz B';
        $eventB->save();

        $mail = $this->sendConfirmation($eventB);

        $this->assertSame('Quiz B: We zijn er bij!', $mail['subject']);
        $this->assertStringContainsString('We zijn er bij!', $mail['body']);
        $this->assertStringNotContainsString('Tekst van evenement A', $mail['body']);
    }

    public function testTheWaitingListEditorStartsFromTheEventsText()
    {
        $event = $this->createEvent($this->organisation);
        $this->createTicketCategory($event, 0.0);

        $this->createOrganisationVersion(
            EmailTemplates::WAITING_LIST_INVITATION,
            'Organisatie',
            '<p>Tekst van de organisatie</p>'
        );
        $event->emails()->create([
            'type' => EmailTemplates::WAITING_LIST_INVITATION,
            'subject' => 'Eigen wachtlijst {{ event.name }}',
            'content' => '<p>Dag {{ user.name }}, eigen tekst. <a href="{{ invitation.url }}">Bestel</a></p>'
        ]);

        $user = $this->createUser();
        $user->username = 'Wachtende';
        $user->save();
        $event->registerToWaitingList($user);

        $this->actingAs($this->admin)
            ->post("/admin/events/{$event->id}/waitinglist/invite/{$user->id}/generate");

        $token = \DB::table('event_waitinglist')
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->value('access_token');

        $response = $this->actingAs($this->admin)
            ->get("/admin/events/{$event->id}/waitinglist/invite/{$user->id}/edit");

        $response->assertStatus(200);
        $response->assertSee('Eigen wachtlijst ' . $event->name, false);
        $response->assertSee('Dag Wachtende, eigen tekst.', false);
        $response->assertSee('wt=' . $token, false);
        $response->assertDontSee('Tekst van de organisatie', false);
        $response->assertDontSee('{{ user.name }}', false);
    }
}
