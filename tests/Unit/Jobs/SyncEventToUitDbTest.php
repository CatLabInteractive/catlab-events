<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SyncEventToUitDb;
use App\Models\Event;
use App\UitDB\Contracts\UitDBService;
use App\UitDB\UitDBEvents;
use Tests\TestCase;

class SyncEventToUitDbTest extends TestCase
{
    public function testHandleCallsUploadOnEventService()
    {
        $event = new Event();

        $eventService = $this->createMock(UitDBEvents::class);
        $eventService->expects($this->once())
            ->method('upload')
            ->with($event);

        $service = $this->createMock(UitDBService::class);
        $service->method('getEventService')->willReturn($eventService);
        $this->app->instance(UitDBService::class, $service);

        (new SyncEventToUitDb($event))->handle();
    }

    public function testHandleDoesNothingWhenEventServiceUnavailable()
    {
        $event = new Event();

        $service = $this->createMock(UitDBService::class);
        $service->method('getEventService')->willReturn(null);
        $this->app->instance(UitDBService::class, $service);

        // Should not throw, simply return early.
        (new SyncEventToUitDb($event))->handle();
        $this->assertTrue(true);
    }

    public function testHandleLogsAndRethrowsOnFailure()
    {
        $event = new Event();

        $eventService = $this->createMock(UitDBEvents::class);
        $eventService->method('upload')->willThrowException(new \RuntimeException('boom'));

        $service = $this->createMock(UitDBService::class);
        $service->method('getEventService')->willReturn($eventService);
        $this->app->instance(UitDBService::class, $service);

        \Log::shouldReceive('error')->once();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        (new SyncEventToUitDb($event))->handle();
    }
}
