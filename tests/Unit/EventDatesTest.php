<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\EventDate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

/**
 * startDate and endDate come from the loaded dates, so a list that eager
 * loads eventDates doesn't run a min()/max() query per event.
 */
class EventDatesTest extends TestCase
{
    private function eventWithDates(array $dates): Event
    {
        $event = new Event();
        $event->setRelation('eventDates', new Collection(array_map(function ($d) {
            $date = new EventDate();
            $date->startDate = Carbon::parse($d[0]);
            $date->endDate = Carbon::parse($d[1]);
            return $date;
        }, $dates)));
        return $event;
    }

    public function testStartAndEndDateSpanAllDates()
    {
        $event = $this->eventWithDates([
            [ '2026-12-20 20:00', '2026-12-20 23:00' ],
            [ '2026-12-19 20:00', '2026-12-19 23:30' ],
        ]);

        $this->assertInstanceOf(Carbon::class, $event->startDate);
        $this->assertSame('2026-12-19 20:00', $event->startDate->format('Y-m-d H:i'));
        $this->assertSame('2026-12-20 23:00', $event->endDate->format('Y-m-d H:i'));
    }

    public function testNoDatesMeansNoStartOrEnd()
    {
        $event = $this->eventWithDates([]);

        $this->assertNull($event->startDate);
        $this->assertNull($event->endDate);
    }
}
