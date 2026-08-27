<?php

namespace Tests\Unit\Observers;

use App\Jobs\DeleteEventFromUitDb;
use App\Jobs\SyncEventToUitDb;
use App\Models\Event;
use App\Observers\EventObserver;
use App\UitDB\Contracts\UitDBService;
use App\UitDB\UitDBEvents;
use Illuminate\Support\Facades\Bus;
use ReflectionProperty;
use Tests\TestCase;

class EventObserverTest extends TestCase
{
    /**
     * Build an Event instance with a given set of current attribute values
     * and a given "changes since last save" set, without touching the
     * database (the local test environment has no PDO drivers available).
     *
     * @param array $attributes
     * @param array $changes
     * @return Event
     */
    private function makeEvent(array $attributes, array $changes): Event
    {
        $event = new Event();

        foreach ($attributes as $key => $value) {
            $event->{$key} = $value;
        }

        $reflection = new ReflectionProperty(Event::class, 'changes');
        $reflection->setAccessible(true);
        $reflection->setValue($event, $changes);

        return $event;
    }

    private function bindEventService(?UitDBEvents $eventService): void
    {
        $service = $this->createMock(UitDBService::class);
        $service->method('getEventService')->willReturn($eventService);

        $this->app->instance(UitDBService::class, $service);
    }

    public function testSavedDispatchesSyncJobForPublishedEvent()
    {
        Bus::fake();
        $this->bindEventService($this->createMock(UitDBEvents::class));

        $event = $this->makeEvent(
            ['is_published' => true, 'uitdb_event_id' => null],
            ['is_published' => true, 'name' => 'Foo']
        );

        (new EventObserver())->saved($event);

        Bus::assertDispatched(SyncEventToUitDb::class, function ($job) use ($event) {
            return $job->event === $event;
        });
    }

    public function testSavedDoesNotDispatchForUnpublishedEventWithoutUitdbId()
    {
        Bus::fake();
        $this->bindEventService($this->createMock(UitDBEvents::class));

        $event = $this->makeEvent(
            ['is_published' => false, 'uitdb_event_id' => null],
            ['is_published' => false, 'name' => 'Foo']
        );

        (new EventObserver())->saved($event);

        Bus::assertNotDispatched(SyncEventToUitDb::class);
    }

    public function testSavedDoesNotDispatchWhenOnlyUitdbEventIdChanged()
    {
        Bus::fake();
        $this->bindEventService($this->createMock(UitDBEvents::class));

        $event = $this->makeEvent(
            ['is_published' => true, 'uitdb_event_id' => 'abc-123'],
            ['uitdb_event_id' => 'abc-123', 'updated_at' => '2026-08-27 00:00:00']
        );

        (new EventObserver())->saved($event);

        Bus::assertNotDispatched(SyncEventToUitDb::class);
    }

    public function testSavedDoesNotDispatchWhenCredentialsMissing()
    {
        Bus::fake();
        $this->bindEventService(null);

        $event = $this->makeEvent(
            ['is_published' => true, 'uitdb_event_id' => null],
            ['is_published' => true, 'name' => 'Foo']
        );

        (new EventObserver())->saved($event);

        Bus::assertNotDispatched(SyncEventToUitDb::class);
    }

    public function testSavedDispatchesForPublishedEventWithExistingUitdbId()
    {
        Bus::fake();
        $this->bindEventService($this->createMock(UitDBEvents::class));

        $event = $this->makeEvent(
            ['is_published' => true, 'uitdb_event_id' => 'abc-123'],
            ['name' => 'Foo']
        );

        (new EventObserver())->saved($event);

        Bus::assertDispatched(SyncEventToUitDb::class);
    }

    public function testDeletedDispatchesDeleteJobWhenUitdbIdSet()
    {
        Bus::fake();
        $this->bindEventService($this->createMock(UitDBEvents::class));

        $event = $this->makeEvent(['uitdb_event_id' => 'abc-123'], []);

        (new EventObserver())->deleted($event);

        Bus::assertDispatched(DeleteEventFromUitDb::class, function ($job) {
            return $job->uitdbEventId === 'abc-123';
        });
    }

    public function testDeletedDoesNotDispatchWhenNoUitdbId()
    {
        Bus::fake();
        $this->bindEventService($this->createMock(UitDBEvents::class));

        $event = $this->makeEvent(['uitdb_event_id' => null], []);

        (new EventObserver())->deleted($event);

        Bus::assertNotDispatched(DeleteEventFromUitDb::class);
    }

    public function testDeletedDoesNotDispatchWhenCredentialsMissing()
    {
        Bus::fake();
        $this->bindEventService(null);

        $event = $this->makeEvent(['uitdb_event_id' => 'abc-123'], []);

        (new EventObserver())->deleted($event);

        Bus::assertNotDispatched(DeleteEventFromUitDb::class);
    }
}
