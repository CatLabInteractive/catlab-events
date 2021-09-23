<table class="table agenda">

    <tr>
        <th style="width: 20%">Datum</th>
        <th style="width: 40%">Naam</th>
        <th style="width: 20%">Reeks</th>
        <th style="width: 20%">Locatie</th>
    </tr>

    @foreach($events as $v)

        <tr>
            <td>
                @if(count($v->eventDates) > 0)
                    @foreach ($v->eventDates as $eventDate)
                        @if($eventDate->startDate)
                            @if(!$eventDate->isFinished() && $v->series)
                                <a href="{{ $v->series->getUrl($v) }}">{{ $eventDate->startDate->format('d/m/Y H:i') }}</a>
                            @else
                                <a href="{{ $v->getUrl() }}">{{ $eventDate->startDate->format('d/m/Y H:i') }}</a>
                            @endif
                            <br />
                        @endif
                    @endforeach
                @endif
            </td>

            <td>
                <a href="{{ $v->getUrl() }}">
                    {{ $v->name }}
                </a>

                @if(!$v->isFinished() && $v->canRegister())
                    @if($v->isSoldOut())
                        <span class="lastTickets">Uitverkocht!</span>
                    @elseif($v->isLastTicketsWarning())
                        <?php $availableTickets = $v->countAvailableTickets(); ?>
                        <span class="lastTickets">Laatste {{ $availableTickets }} tickets!</span>
                    @endif
                @endif
            </td>

            <td>
                @if($v->series)
                    <a href="{{ $v->series->getUrl() }}">{{ $v->series->name }}</a>
                @endif
            </td>

            <td>
                @if($v->venue)
                    <a href="{{ $v->venue->getLocalUrl() }}">{{ $v->venue->name }}</a>
                @endif
            </td>
        </tr>

    @endforeach
</table>
