<?php

namespace Tests\Unit\UitDB;

use App\Models\Event;
use App\Models\EventDate;
use App\Models\TicketCategory;
use App\Models\Venue;
use App\UitDB\UitDatabankService;
use App\UitDB\UitDBEvents;
use Carbon\Carbon;
use Tests\TestCase;

class UitDBEventsTest extends TestCase
{
    /**
     * Test that upload returns null when client credentials are not configured.
     */
    public function testUploadReturnsNullWithoutCredentials()
    {
        $service = new UitDatabankService('test', 'api-key', 'consumer', 'secret', null, null);
        $events = new UitDBEvents($service);

        $event = $this->createMock(Event::class);
        $event->method('getUitDBId')->willReturn(null);

        $result = $events->upload($event);
        $this->assertNull($result);
    }

    /**
     * Test that hasClientCredentials returns true when both are set.
     */
    public function testHasClientCredentials()
    {
        $service = new UitDatabankService('test', 'api-key', 'consumer', 'secret', 'client-id', 'client-secret');
        $this->assertTrue($service->hasClientCredentials());
    }

    /**
     * Test that hasClientCredentials returns false when not set.
     */
    public function testHasNoClientCredentials()
    {
        $service = new UitDatabankService('test', 'api-key', 'consumer', 'secret', null, null);
        $this->assertFalse($service->hasClientCredentials());
    }

    /**
     * Test that getEventService returns null without credentials.
     */
    public function testGetEventServiceReturnsNullWithoutCredentials()
    {
        $service = new UitDatabankService('test', 'api-key', 'consumer', 'secret', null, null);
        $this->assertNull($service->getEventService());
    }

    /**
     * Test that getEventService returns instance with credentials.
     */
    public function testGetEventServiceReturnsInstanceWithCredentials()
    {
        $service = new UitDatabankService('test', 'api-key', 'consumer', 'secret', 'client-id', 'client-secret');
        $this->assertInstanceOf(UitDBEvents::class, $service->getEventService());
    }

    /**
     * Test environment URLs include uitpas and auth.
     */
    public function testEnvironmentIncludesNewUrls()
    {
        $service = new UitDatabankService('test', 'api-key', 'consumer', 'secret', 'client-id', 'client-secret');
        $env = $service->getEnvironment();

        $this->assertArrayHasKey('uitpas', $env);
        $this->assertArrayHasKey('auth', $env);
        $this->assertEquals('https://api-test.uitpas.be', $env['uitpas']);
        $this->assertEquals('https://account-test.uitid.be', $env['auth']);
    }

    /**
     * Test production environment URLs.
     */
    public function testProductionEnvironmentUrls()
    {
        $service = new UitDatabankService('production', 'api-key', 'consumer', 'secret', 'client-id', 'client-secret');
        $env = $service->getEnvironment();

        $this->assertArrayHasKey('uitpas', $env);
        $this->assertArrayHasKey('auth', $env);
        $this->assertEquals('https://api.uitpas.be', $env['uitpas']);
        $this->assertEquals('https://account.uitid.be', $env['auth']);
    }
}
