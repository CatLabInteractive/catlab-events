{{--
    Past events, grouped by year. The most recent year is open, older years
    fold away so a long history doesn't turn into one endless table.

    @param \Illuminate\Support\Collection|\App\Models\Event[] $pastEvents
--}}
<?php
    $pastByYear = collect($pastEvents)->groupBy(function ($pastEvent) {
        return $pastEvent->startDate ? $pastEvent->startDate->format('Y') : '';
    });
?>
<div class="kv-past">
    @foreach($pastByYear as $year => $yearEvents)
        <details class="kv-past-year" @if($loop->first) open @endif>
            <summary>
                <span>{{ $year ?: 'Zonder datum' }}</span>
                <small>{{ count($yearEvents) }} {{ count($yearEvents) === 1 ? 'quiz' : 'quizzen' }}</small>
            </summary>

            <ul>
                @foreach($yearEvents as $pastEvent)
                    <?php
                        $pastDates = $pastEvent->eventDates->sortBy('startDate')->filter(function ($d) { return $d->startDate; });
                    ?>
                    <li class="{{ $pastEvent->getColourClass() }}">
                        <span class="kv-past-date">
                            @if($pastDates->count() > 0)
                                {{ $pastDates->map(function ($d) { return $d->startDate->translatedFormat('j M'); })->implode(', ') }}
                            @elseif($pastEvent->startDate)
                                {{ $pastEvent->startDate->translatedFormat('j M') }}
                            @endif
                        </span>
                        <a class="kv-past-name" href="{{ $pastEvent->getUrl() }}">{{ $pastEvent->name }}</a>
                        <span class="kv-past-meta">
                            @if($pastEvent->venue){{ $pastEvent->venue->name }}@else{{ $pastEvent->getNonVenueLocation() }}@endif
                            @if($pastEvent->series) &middot; {{ $pastEvent->series->name }}@endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </details>
    @endforeach
</div>
