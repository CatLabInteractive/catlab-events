<?php

namespace Tests\Integration;

use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * /sitemap.xml was a 500 since the Laravel 9 upgrade dropped roumen/sitemap
 * but kept the route (Errbit, 2026-08-27). It is now rendered from a Blade
 * view without any package.
 */
class SitemapTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    public function testSitemapListsPublishedEventsAsXml()
    {
        $organisation = $this->createOrganisation();
        $published = $this->createEvent($organisation);

        $unpublished = $this->createEvent($organisation);
        $unpublished->is_published = false;
        $unpublished->save();

        $response = $this->get('/sitemap.xml?nocache=1');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');

        $xml = $response->getContent();
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        $this->assertStringContainsString('<loc>' . $published->getUrl() . '</loc>', $xml);
        $this->assertStringNotContainsString($unpublished->getUrl(), $xml);
        $this->assertStringContainsString('<loc>' . action('EventController@archive') . '</loc>', $xml);
        $this->assertStringContainsString('<loc>' . action('EventController@calendar') . '</loc>', $xml);
    }
}
