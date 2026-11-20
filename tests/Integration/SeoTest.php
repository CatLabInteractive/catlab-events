<?php

namespace Tests\Integration;

use App\Models\Organisation;
use Carbon\Carbon;
use Tests\Integration\Concerns\CreatesCmsFixtures;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * robots.txt, llms.txt, noindex outside production, the sitemap's CMS
 * content, event page meta tags and the canonical-domain redirect.
 */
class SeoTest extends IntegrationTestCase
{
    use CreatesEventFixtures;
    use CreatesCmsFixtures;

    protected function tearDown(): void
    {
        $this->actAsHost('localhost');
        parent::tearDown();
    }

    public function testRobotsOpensEverythingAndListsTheSitemapWhenIndexable()
    {
        config([ 'seo.indexable' => true ]);
        $this->createOrganisation();

        $response = $this->get('/robots.txt');

        $response->assertStatus(200);
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Sitemap: ' . url('sitemap.xml'), $response->getContent());
        $this->assertStringNotContainsString("Disallow: /\n", $response->getContent());
        $this->assertNull($response->headers->get('X-Robots-Tag'));
    }

    public function testNonIndexableEnvironmentBlocksCrawlers()
    {
        config([ 'seo.indexable' => false ]);
        $organisation = $this->createOrganisation();
        $event = $this->createEvent($organisation);

        $robots = $this->get('/robots.txt');
        $this->assertSame("User-agent: *\nDisallow: /\n", $robots->getContent());

        $page = $this->get($event->getUrl());
        $page->assertStatus(200);
        $page->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $page->getContent());
    }

    public function testSitemapListsCmsPagesAndPostsOfThisOrganisationOnly()
    {
        $organisation = $this->createOrganisation();
        $this->createPage($organisation, 'over-ons');
        $this->createPage($organisation, 'concept', [], 'nl', false);
        $this->createPost($organisation, 'eerste-post', Carbon::parse('2026-05-04 12:00', 'Europe/Brussels'));

        $other = $this->createOrganisation();
        $otherEvent = $this->createEvent($other);
        $otherEvent->name = 'Other organisation quiz';
        $otherEvent->save();
        $this->createPage($other, 'andere-pagina');

        $xml = $this->get('/sitemap.xml?nocache=1')->assertStatus(200)->getContent();

        $this->assertStringContainsString('<loc>' . url('over-ons') . '</loc>', $xml);
        $this->assertStringContainsString('<loc>' . url('2026/05/04/eerste-post') . '</loc>', $xml);
        $this->assertStringContainsString('<loc>' . url('blog') . '</loc>', $xml);
        $this->assertStringNotContainsString('concept', $xml);
        $this->assertStringNotContainsString('andere-pagina', $xml);
        $this->assertStringNotContainsString($otherEvent->getUrl(), $xml);
    }

    public function testLlmsTxtSummarisesTheOrganisation()
    {
        $organisation = $this->createOrganisation();
        $organisation->support_email = 'info@example.com';
        $organisation->save();
        $event = $this->createEvent($organisation);
        $this->createPage($organisation, 'over-ons');

        $response = $this->get('/llms.txt');

        $response->assertStatus(200);
        $this->assertStringStartsWith('text/markdown', $response->headers->get('Content-Type'));
        $body = $response->getContent();
        $this->assertStringStartsWith('# Test organisation', $body);
        $this->assertStringContainsString('Contact: info@example.com', $body);
        $this->assertStringContainsString('[Test quiz night](' . $event->getUrl() . ')', $body);
        $this->assertStringContainsString('(' . url('over-ons') . ')', $body);
    }

    public function testEventPageHasItsOwnMetaTagsAndOneOrganizationBlock()
    {
        $organisation = $this->createOrganisation();
        $event = $this->createEvent($organisation);
        $event->description = '<p>Een avond vol <strong>vragen</strong> &amp; bier.</p>';
        $event->save();

        $html = $this->get($event->getUrl())->assertStatus(200)->getContent();

        $this->assertMatchesRegularExpression('/<meta name="description" content="[^"]*Een avond vol vragen &amp; bier\./', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Test quiz night" />', $html);
        // One top-level Organization block (the event's organizer is nested deeper)
        $this->assertSame(1, preg_match_all('/^    "@type": "Organization"/m', $html));
    }

    public function testPhoneNumberShowsInFooterAndOrganizationJsonLd()
    {
        $organisation = $this->createOrganisation();
        $organisation->support_email = 'info@example.com';
        $organisation->phone = '+32 (0)9 123 45 67';
        $organisation->save();
        $event = $this->createEvent($organisation);

        $html = $this->get($event->getUrl())->assertStatus(200)->getContent();

        $this->assertStringContainsString('<a href="tel:+3291234567">+32 (0)9 123 45 67</a>', $html);
        $this->assertStringContainsString('"telephone": "+32 (0)9 123 45 67"', $html);

        $this->assertStringContainsString('Telefoon: +32 (0)9 123 45 67', $this->get('/llms.txt')->getContent());
    }

    public function testNoPhoneNumberMeansNoTelLink()
    {
        $organisation = $this->createOrganisation();
        $event = $this->createEvent($organisation);

        $html = $this->get($event->getUrl())->assertStatus(200)->getContent();

        $this->assertStringNotContainsString('href="tel:', $html);
        $this->assertStringNotContainsString('"telephone"', $html);
    }

    public function testFaqBlocksBecomeFaqPageStructuredData()
    {
        $organisation = $this->createOrganisation();
        $this->createPage($organisation, 'faq', [
            [ 'id' => 'f1', 'type' => 'faq', 'data' => [ 'title' => 'Vragen', 'items' => [
                [ 'question' => 'Hoe groot is een ploeg?', 'answer' => '<p>Maximaal 6 spelers.</p>' ],
            ] ] ],
        ]);

        $html = $this->get('/faq')->assertStatus(200)->getContent();

        $this->assertStringContainsString('"@type": "FAQPage"', $html);
        $this->assertStringContainsString('"name": "Hoe groot is een ploeg?"', $html);
        $this->assertStringContainsString('"text": "Maximaal 6 spelers."', $html);
    }

    public function testSecondaryDomainRedirectsToCanonicalDomainWhenEnabled()
    {
        $organisation = $this->createOrganisation();
        $this->createOrganisationDomain($organisation, 'tickets.example.test');
        $canonical = $this->createOrganisationDomain($organisation, 'www.example.test');
        $canonical->is_canonical = true;
        $canonical->save();
        $this->createPage($organisation, 'over-ons');

        // Off: nothing happens
        config([ 'seo.canonical_redirect' => false ]);
        $this->actAsHost('tickets.example.test');
        $this->get('http://tickets.example.test/over-ons?x=1')->assertStatus(200);

        config([ 'seo.canonical_redirect' => true ]);

        $this->get('http://tickets.example.test/over-ons?x=1')
            ->assertStatus(301)
            ->assertRedirect('http://www.example.test/over-ons?x=1');

        // POSTs (payment webhooks, forms) are never redirected
        $this->assertNotSame(301, $this->post('http://tickets.example.test/over-ons')->getStatusCode());

        // The canonical domain itself and unknown hosts are served
        $this->actAsHost('www.example.test');
        $this->get('http://www.example.test/over-ons')->assertStatus(200);
        $this->get('http://unknown.example.test/over-ons')->assertStatus(200);
    }
}
