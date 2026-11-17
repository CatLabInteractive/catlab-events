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
}
