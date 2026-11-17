<?php

namespace Tests\Integration;

use App\Models\EmailLayout;
use App\Models\Organisation;
use App\Models\User;
use App\Services\EmailTemplates;
use CatLab\CentralStorage\Client\Models\Asset;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * Every mail goes out inside one layout: the organisation's logo (or name)
 * above a white card, and a footer with what the organisation filled in.
 * An organisation can change the accent color and footer text, or replace
 * the whole layout with its own HTML.
 */
class EmailLayoutTest extends IntegrationTestCase
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

    private function preview(string $type): string
    {
        return $this->actingAs($this->admin)
            ->get("/admin/emails/{$type}/preview")
            ->assertStatus(200)
            ->getContent();
    }

    /**
     * The header block, from its <td> to the end of that cell.
     */
    private function header(string $body): string
    {
        $this->assertSame(1, preg_match('/<td[^>]*data-mail-header[^>]*>(.*?)<\/td>/s', $body, $matches));
        return $matches[1];
    }

    public function testTheDefaultLayoutShowsTheLogoAboveTheCard()
    {
        // A real asset row: the preview loads the organisation (and its logo)
        // from the database. Its URL is built locally from the asset key, so
        // no storage server is involved.
        $asset = new Asset();
        $asset->setName('logo.png');
        $asset->setAssetKey('test-logo-key');
        $asset->save();

        $this->organisation->logo()->associate($asset);
        $this->organisation->save();

        $body = $this->preview(EmailTemplates::CONFIRMATION);

        $this->assertStringContainsString('test-logo-key', $asset->getUrl());
        $this->assertStringContainsString('src="' . e($asset->getUrl()) . '"', $this->header($body));
        $this->assertStringContainsString('border-top:4px solid #4cb050', $body);
        $this->assertStringContainsString('width="600"', $body);
        $this->assertStringNotContainsString('stat-pulse.com', $body);
    }

    public function testWithoutALogoTheHeaderShowsTheName()
    {
        $body = $this->preview(EmailTemplates::CONFIRMATION);

        $this->assertStringContainsString('Test organisation', $this->header($body));
        $this->assertStringNotContainsString('<img', $body);
    }

    public function testTheFooterShowsOnlyWhatTheOrganisationFilledIn()
    {
        $this->organisation->support_email = 'help@example.com';
        $this->organisation->facebook_url = 'https://facebook.example/quiz';
        $this->organisation->save();

        $body = $this->preview(EmailTemplates::CONFIRMATION);

        $this->assertStringContainsString('mailto:help@example.com', $body);
        $this->assertStringContainsString('https://facebook.example/quiz', $body);
        $this->assertStringContainsString('Facebook', $body);
        $this->assertStringNotContainsString('Instagram', $body);
        $this->assertStringNotContainsString('&middot; </p>', $body);
    }

    public function testTheFooterTextAndAccentColorApply()
    {
        $layout = new EmailLayout([
            'accent_color' => '#7b3fbf',
            'footer_text' => "Vragen?\n<b>Mail ons</b>"
        ]);
        $layout->organisation()->associate($this->organisation);
        $layout->save();

        $body = $this->preview(EmailTemplates::WAITING_LIST_INVITATION);

        $this->assertStringContainsString('border-top:4px solid #7b3fbf', $body);
        // The "Bestel je ticket" button; the top line is a border, not a background.
        $this->assertStringContainsString('background:#7b3fbf', $body);
        $this->assertStringNotContainsString('#4cb050', $body);
        $this->assertStringContainsString('Vragen?<br', $body);
        $this->assertStringContainsString('&lt;b&gt;Mail ons', $body);
    }

    public function testEveryMailTypeGoesOutInsideTheLayout()
    {
        foreach (self::TYPES as $type) {
            $this->assertStringContainsString('data-mail-header', $this->preview($type), $type);
        }

        $this->assertStringContainsString('We zijn er bij!', $this->preview(EmailTemplates::CONFIRMATION));
    }

    public function testTheTeamInvitationUsesTheCustomLayout()
    {
        $layout = new EmailLayout([ 'custom_html' => '<div id="eigen">{{ content }}</div>' ]);
        $layout->organisation()->associate($this->organisation);
        $layout->save();

        $body = $this->preview(EmailTemplates::GROUP_INVITE);

        $this->assertStringContainsString('<div id="eigen">', $body);
        $this->assertStringContainsString('Accepteren', $body);
        $this->assertStringNotContainsString('data-mail-header', $body);
    }

    private function stored(): ?EmailLayout
    {
        return EmailLayout::query()->where('organisation_id', $this->organisation->id)->first();
    }

    public function testAnAdminSetsTheAccentColorAndFooterText()
    {
        $this->actingAs($this->admin)
            ->get('/admin/emails/layout')
            ->assertStatus(200)
            ->assertSee('Geavanceerd');

        $this->actingAs($this->admin)
            ->post('/admin/emails/layout', [ 'accent_color' => '#7b3fbf', 'footer_text' => 'Tot snel!' ])
            ->assertRedirect();

        $layout = $this->stored();
        $this->assertSame('#7b3fbf', $layout->accent_color);
        $this->assertSame('Tot snel!', $layout->footer_text);
        $this->assertNull($layout->custom_html);
        $this->assertStringContainsString('border-top:4px solid #7b3fbf', $this->preview(EmailTemplates::CONFIRMATION));
    }

    public function testAnInvalidAccentColorIsRefused()
    {
        foreach ([ 'red', '#12345', '#gggggg', 'red;background:url(//x)' ] as $color) {
            $this->actingAs($this->admin)
                ->post('/admin/emails/layout', [ 'accent_color' => $color ])
                ->assertSessionHasErrors('accent_color');
        }

        $this->assertNull($this->stored());
    }

    public function testABadAccentColorNeverReachesTheMail()
    {
        // The column holds 7 characters, so a stored value can only be a short one.
        $layout = new EmailLayout([ 'accent_color' => 'red;x:y' ]);
        $layout->organisation()->associate($this->organisation);
        $layout->save();

        $body = $this->preview(EmailTemplates::CONFIRMATION);
        $this->assertStringContainsString('#4cb050', $body);
        $this->assertStringNotContainsString('red;x:y', $body);

        // A draft is not limited by the column.
        $body = $this->actingAs($this->admin)
            ->post('/admin/emails/layout/preview', [ 'accent_color' => 'red;background:url(//x)' ])
            ->assertStatus(200)
            ->getContent();
        $this->assertStringContainsString('#4cb050', $body);
        $this->assertStringNotContainsString('url(//x)', $body);
    }

    public function testACustomLayoutNeedsExactlyOneContentPlaceholder()
    {
        foreach ([ '<p>geen</p>', '<p>{{ content }}</p>{{ content }}', '<!-- {{ content }} --><p>x</p>',
            '<html><head><title>{{ content }}</title></head><body>{{ content }}</body></html>',
            '<!-- {{ content }} --><div>{{ content }}</div>' ] as $html) {
            $this->actingAs($this->admin)
                ->post('/admin/emails/layout', [ 'custom_html' => $html ])
                ->assertSessionHasErrors([ 'custom_html' => 'De layout moet precies één keer {{ content }} bevatten.' ]);
        }
        $this->assertNull($this->stored());

        foreach ([ '<p>{{content}}</p>', '<p>{{  content  }}</p>' ] as $html) {
            $this->actingAs($this->admin)
                ->post('/admin/emails/layout', [ 'custom_html' => $html ])
                ->assertSessionHasNoErrors();
            $this->assertSame($html, $this->stored()->custom_html);
        }
    }

    public function testAResetReturnsToTheDefaultLayout()
    {
        $layout = new EmailLayout([
            'accent_color' => '#7b3fbf',
            'footer_text' => 'Hallo',
            'custom_html' => '<div id="eigen">{{ content }}</div>'
        ]);
        $layout->organisation()->associate($this->organisation);
        $layout->save();

        $this->actingAs($this->admin)->post('/admin/emails/layout/reset')->assertRedirect();

        $layout = $this->stored();
        $this->assertNull($layout->custom_html);
        $this->assertSame('#7b3fbf', $layout->accent_color);
        $this->assertSame('Hallo', $layout->footer_text);
        $this->assertStringContainsString('data-mail-header', $this->preview(EmailTemplates::CONFIRMATION));
    }

    public function testSavingTheUnchangedDefaultStoresNothing()
    {
        $default = app(\App\Services\EmailLayouts::class)->getDefaultHtml();

        $this->actingAs($this->admin)
            ->post('/admin/emails/layout', [ 'accent_color' => '#7b3fbf', 'custom_html' => $default ])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->stored()->custom_html);

        // Line endings of a browser post must not count as a change.
        $this->actingAs($this->admin)
            ->post('/admin/emails/layout', [ 'custom_html' => str_replace("\n", "\r\n", $default) ])
            ->assertSessionHasNoErrors();
        $this->assertNull($this->stored()->custom_html);
    }

    public function testUnknownPlaceholdersInTheLayoutStayVisible()
    {
        $this->actingAs($this->admin)
            ->post('/admin/emails/layout', [ 'custom_html' => '<div>{{ nope }}{{ content }}</div>' ])
            ->assertSessionHasNoErrors();

        $this->assertStringContainsString('{{ nope }}', $this->preview(EmailTemplates::CONFIRMATION));
    }

    public function testTheLayoutPreviewShowsTheDraftWithoutSaving()
    {
        $response = $this->actingAs($this->admin)
            ->post('/admin/emails/layout/preview', [
                'accent_color' => '#7b3fbf',
                'footer_text' => 'Concept voettekst',
                'custom_html' => ''
            ])
            ->assertStatus(200);

        $this->assertStringContainsString('Content-Security-Policy', (string) $response->headers);
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
        $body = $response->getContent();
        $this->assertStringContainsString('border-top:4px solid #7b3fbf', $body);
        $this->assertStringContainsString('Concept voettekst', $body);
        $this->assertStringContainsString('We zijn er bij!', $body);
        $this->assertNull($this->stored());

        $this->actingAs($this->admin)->get('/admin/emails/layout/preview')->assertStatus(200);
    }

    public function testOnlyAdminsCanChangeTheLayout()
    {
        $member = $this->createUser();
        $member->admin = true;
        $member->save();
        $this->organisation->users()->attach($member, [ 'role' => 1 ]);

        $this->actingAs($member)->get('/admin/emails/layout')->assertStatus(404);
        $this->actingAs($member)->post('/admin/emails/layout', [ 'accent_color' => '#7b3fbf' ])->assertStatus(404);
        $this->actingAs($member)->post('/admin/emails/layout/preview', [])->assertStatus(404);
        $this->actingAs($member)->post('/admin/emails/layout/reset')->assertStatus(404);

        $this->assertNull($this->stored());
    }
}
