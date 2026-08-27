<?php

namespace Tests\Unit\Jobs;

use App\Jobs\DeleteEventFromUitDb;
use App\UitDB\Contracts\UitDBService;
use App\UitDB\UitDBEvents;
use Tests\TestCase;

class DeleteEventFromUitDbTest extends TestCase
{
    public function testHandleCallsDeleteEventOnEventService()
    {
        $eventService = $this->createMock(UitDBEvents::class);
        $eventService->expects($this->once())
            ->method('deleteEvent')
            ->with('abc-123');

        $service = $this->createMock(UitDBService::class);
        $service->method('getEventService')->willReturn($eventService);
        $this->app->instance(UitDBService::class, $service);

        (new DeleteEventFromUitDb('abc-123'))->handle();
    }

    public function testHandleDoesNothingWhenEventServiceUnavailable()
    {
        $service = $this->createMock(UitDBService::class);
        $service->method('getEventService')->willReturn(null);
        $this->app->instance(UitDBService::class, $service);

        (new DeleteEventFromUitDb('abc-123'))->handle();
        $this->assertTrue(true);
    }

    public function testHandleLogsAndRethrowsOnFailure()
    {
        $eventService = $this->createMock(UitDBEvents::class);
        $eventService->method('deleteEvent')->willThrowException(new \RuntimeException('boom'));

        $service = $this->createMock(UitDBService::class);
        $service->method('getEventService')->willReturn($eventService);
        $this->app->instance(UitDBService::class, $service);

        \Log::shouldReceive('error')->once();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        (new DeleteEventFromUitDb('abc-123'))->handle();
    }
}
