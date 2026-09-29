<?php

namespace Tests\Integration;

use App\Models\EmailTemplate;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\User;
use App\Services\EmailTemplates;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * Admins preview the transactional mails with made-up data and can rewrite
 * them for their organisation. A rewritten mail replaces the default Blade
 * template for every event of that organisation until it is reset.
 */
class EmailTemplateTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    const TYPES = [
        EmailTemplates::CONFIRMATION,
        EmailTemplates::CONFIRMATION_PLAY_LINK,
        EmailTemplates::CANCELLATION,
        EmailTemplates::WAITING_LIST_INVITATION,
        EmailTemplates::GROUP_INVITE
    ];

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

    private function customise(string $type, string $subject, string $content): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/emails/{$type}", [ 'subject' => $subject, 'content' => $content ])
            ->assertRedirect(action('Admin\EmailTemplateController@show', [ $type ]));
    }

    public function testTheOverviewListsEveryMailAndIsLinkedFromTheMenu()
    {
        $response = $this->actingAs($this->admin)->get('/admin/emails');

        $response->assertStatus(200);
        $response->assertSee('Inschrijving bevestigd');
        $response->assertSee('Inschrijving geannuleerd');
        $response->assertSee(action('Admin\EmailTemplateController@index'), false);

        foreach (self::TYPES as $type) {
            $response->assertSee(action('Admin\EmailTemplateController@edit', [ $type ]), false);
        }
    }

    public function testEveryDefaultMailCanBePreviewedWithSampleData()
    {
        foreach (self::TYPES as $type) {
            $this->actingAs($this->admin)->get("/admin/emails/{$type}")->assertStatus(200);

            $preview = $this->actingAs($this->admin)->get("/admin/emails/{$type}/preview");
            $preview->assertStatus(200);
            $preview->assertHeader('Content-Security-Policy', 'sandbox');
            $this->assertStringContainsString('<html>', $preview->getContent());

            $this->actingAs($this->admin)->get("/admin/emails/{$type}/edit")
                ->assertStatus(200)
                ->assertSee('Beschikbare velden');
        }

        $this->actingAs($this->admin)->get('/admin/emails/' . EmailTemplates::CONFIRMATION . '/preview')
            ->assertSee('Voorbeeldquiz')
            ->assertSee('De Voorbeeldige Quizzers');

        $this->assertCount(0, $this->catlabApi->sendEmailCalls);
    }

    public function testEveryStarterTextPreviewsWithoutLeftoverPlaceholders()
    {
        $templates = app(EmailTemplates::class);

        foreach (self::TYPES as $type) {
            $body = $this->actingAs($this->admin)
                ->post("/admin/emails/{$type}/preview", [
                    'subject' => $templates->getDefaultSubject($type),
                    'content' => $templates->getDefaultContent($type)
                ])
                ->assertStatus(200)
                ->getContent();

            $this->assertStringNotContainsString('{{', $body, $type);
        }
    }

    public function testUnknownMailTypesAreNotFound()
    {
        $this->actingAs($this->admin)->get('/admin/emails/nope')->assertStatus(404);
        $this->actingAs($this->admin)
            ->post('/admin/emails/nope', [ 'subject' => 'a', 'content' => 'b' ])
            ->assertStatus(404);
    }

    public function testOnlyAdminsOfTheOrganisationCanEditItsMails()
    {
        $outsider = $this->createUser();
        $outsider->admin = true;
        $outsider->save();
        // A plain member (no admin role) of the organisation.
        $this->organisation->users()->attach($outsider, [ 'role' => 1 ]);

        $this->actingAs($outsider)->get('/admin/emails')->assertStatus(404);
        $this->actingAs($outsider)
            ->post('/admin/emails/' . EmailTemplates::CONFIRMATION, [ 'subject' => 'a', 'content' => 'b' ])
            ->assertStatus(404);

        $this->assertSame(0, EmailTemplate::query()->count());
    }

    public function testPreviewingADraftSavesNothing()
    {
        $body = $this->actingAs($this->admin)
            ->post('/admin/emails/' . EmailTemplates::CANCELLATION . '/preview', [
                'subject' => 'Jammer',
                'content' => '<p>Tot ziens op {{ event.name }}, {{ team.name }}!</p>'
            ])
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString('Tot ziens op Voorbeeldquiz, De Voorbeeldige Quizzers!', $body);
        $this->assertSame(0, EmailTemplate::query()->count());
    }

    public function testACustomisedConfirmationIsSentInsteadOfTheDefault()
    {
        $event = $this->createEvent($this->organisation);
        $event->name = 'Quiz <b>& co</b>';
        $event->save();
        $category = $this->createTicketCategory($event, 10.0);
        $category->eventDates()->attach($event->eventDates()->first());

        $this->customise(
            EmailTemplates::CONFIRMATION,
            'Tot op {{ event.name }}',
            '<p>{{ greeting }} Welkom op {{ event.name }}. {{ onbekend }}</p><div>{{ event.dates }}</div>'
        );

        $this->actingAs($this->admin)->post("/admin/events/{$event->id}/guests", [
            'name' => 'Famous Person',
            'email' => 'famous@example.com',
            'ticketCategory' => $category->id
        ]);

        $this->assertCount(1, $this->catlabApi->sendEmailCalls);
        $mail = $this->catlabApi->sendEmailCalls[0];

        // The subject is plain text; the body escapes whatever it fills in.
        $this->assertSame('Tot op Quiz <b>& co</b>', $mail['subject']);
        $this->assertStringContainsString('Hallo! Welkom op Quiz &lt;b&gt;&amp; co&lt;/b&gt;.', $mail['body']);
        $this->assertStringNotContainsString('<b>& co</b>', $mail['body']);
        // Unknown placeholders are left alone, so they stand out.
        $this->assertStringContainsString('{{ onbekend }}', $mail['body']);
        // Block placeholders render their own HTML.
        $this->assertStringContainsString('De quiz start stipt om', $mail['body']);
        $this->assertStringNotContainsString('We zijn er bij!', $mail['body']);
    }

    public function testACustomisedMailOnlyAppliesToItsOwnOrganisation()
    {
        $this->customise(EmailTemplates::CONFIRMATION, 'Eigen onderwerp', '<p>Eigen tekst</p>');

        $other = $this->createOrganisation();
        $event = $this->createEvent($other);
        $category = $this->createTicketCategory($event, 10.0);

        $order = new Order();
        $order->event()->associate($event);
        $order->ticketCategory()->associate($category);
        $order->state = Order::STATE_ACCEPTED;
        $order->is_guest = true;
        $order->guest_name = 'Gast';
        $order->guest_email = 'gast@example.com';
        $order->save();

        $this->actingAs($this->admin);
        event(new \App\Events\OrderConfirmed($order));

        $this->assertCount(1, $this->catlabApi->sendEmailCalls);
        $this->assertSame($event->name . ': We zijn er bij!', $this->catlabApi->sendEmailCalls[0]['subject']);
    }

    public function testResettingSendsTheDefaultAgain()
    {
        $this->customise(EmailTemplates::CANCELLATION, 'Eigen onderwerp', '<p>Eigen tekst</p>');
        $this->assertSame(1, EmailTemplate::query()->count());

        $this->actingAs($this->admin)
            ->get('/admin/emails/' . EmailTemplates::CANCELLATION . '/edit')
            ->assertSee('Eigen tekst', false);

        $this->actingAs($this->admin)
            ->post('/admin/emails/' . EmailTemplates::CANCELLATION . '/reset')
            ->assertRedirect(action('Admin\EmailTemplateController@show', [ EmailTemplates::CANCELLATION ]));

        $this->assertSame(0, EmailTemplate::query()->count());

        $event = $this->createEvent($this->organisation);
        $category = $this->createTicketCategory($event, 10.0);

        $this->actingAs($this->admin)->post("/admin/events/{$event->id}/guests", [
            'name' => 'Famous Person',
            'email' => 'famous@example.com',
            'ticketCategory' => $category->id
        ]);
        $order = Order::query()->first();

        $this->actingAs($this->admin)->post("/admin/events/{$event->id}/guests/{$order->id}/cancel");

        $mail = collect($this->catlabApi->sendEmailCalls)->last();
        $this->assertSame($event->name . ': We zijn er niet bij :(', $mail['subject']);
    }

    public function testACustomisedCancellationIsSent()
    {
        $this->customise(
            EmailTemplates::CANCELLATION,
            '{{ event.name }} gaat niet door',
            '<p>{{ greeting }} Jammer!</p>'
        );

        $event = $this->createEvent($this->organisation);
        $category = $this->createTicketCategory($event, 10.0);

        $this->actingAs($this->admin)->post("/admin/events/{$event->id}/guests", [
            'name' => 'Famous Person',
            'email' => 'famous@example.com',
            'ticketCategory' => $category->id
        ]);
        $order = Order::query()->first();

        $this->actingAs($this->admin)->post("/admin/events/{$event->id}/guests/{$order->id}/cancel");

        $mail = collect($this->catlabApi->sendEmailCalls)->last();
        $this->assertSame($event->name . ' gaat niet door', $mail['subject']);
        $this->assertStringContainsString('Hallo! Jammer!', $mail['body']);
    }

    public function testTheWaitingListInvitationStartsFromTheCustomisedVersion()
    {
        $this->customise(
            EmailTemplates::WAITING_LIST_INVITATION,
            'Snel zijn voor {{ event.name }}',
            '<p>Dag {{ user.name }}, klik <a href="{{ invitation.url }}">hier</a>.</p>'
        );

        $event = $this->createEvent($this->organisation);
        $this->createTicketCategory($event, 0.0);

        $user = $this->createUser();
        $user->username = 'Wachtende';
        $user->save();
        $event->registerToWaitingList($user);

        // The admin's own edit page starts from the organisation's text.
        $this->actingAs($this->admin)
            ->post("/admin/events/{$event->id}/waitinglist/invite/{$user->id}/generate");
        $this->actingAs($this->admin)
            ->get("/admin/events/{$event->id}/waitinglist/invite/{$user->id}/edit")
            ->assertSee('Snel zijn voor ' . $event->name)
            ->assertSee('Dag Wachtende, klik', false);

        $this->actingAs($this->admin)
            ->post("/admin/events/{$event->id}/waitinglist/invite/{$user->id}");

        $this->assertCount(1, $this->catlabApi->sendEmailCalls);
        $mail = $this->catlabApi->sendEmailCalls[0];
        $this->assertSame('Snel zijn voor ' . $event->name, $mail['subject']);
        $this->assertStringContainsString('Dag Wachtende, klik <a href="', $mail['body']);
        $this->assertStringContainsString('wt=', $mail['body']);
    }
}
