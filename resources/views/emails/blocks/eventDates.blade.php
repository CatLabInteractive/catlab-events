{{-- Needs $event and $ticketCategory. --}}
@foreach ($ticketCategory->eventDates as $eventDate)

    <h3>{{ $eventDate->startDate->format('d/m/Y') }}</h3>
    @if($event->venue)
        <p>
            Op <strong>{{ $eventDate->startDate->format('d/m/Y') }}</strong> gaan we naar
            <strong>{{ $event->venue->name }}</strong> om deel te nemen aan <strong>{{ $event->name }}</strong>.
        </p>
    @else
        <p>
            Op <strong>{{ $eventDate->startDate->format('d/m/Y') }}</strong> spelen we <strong>{{ $event->name }}</strong>.
        </p>
    @endif


    @if($eventDate->doorsDate)
        <p>
            Aanmelden kan vanaf <strong>{{ $eventDate->doorsDate->format('H:i') }}</strong>, de quiz zelf start stipt om
            {{ $eventDate->startDate->format('H:i') }}.
        </p>
    @else
        <p>
            De quiz start stipt om <strong>{{ $eventDate->startDate->format('H:i') }}</strong>, meld je daarom zeker voor {{ $eventDate->startDate->format('H:i') }} aan.
        </p>
    @endif

@endforeach
