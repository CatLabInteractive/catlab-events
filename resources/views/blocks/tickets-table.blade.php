@if(\App\Http\Controllers\EventController::getValidWaitingListToken($event))
    <div class="alert alert-info">
        <p>
            Je staat op de <a href="{{ action('WaitingListController@waitingList', [ $event->id ]) }}">wachtlijst</a> voor {{ $event->name }}.
            Als er nog tickets vrijgekomen zijn, kan je deze hieronder bestellen.
        </p>
    </div>
@elseif(isset($event) && $event->isSoldOut() && !$event->isFinished())
    <div class="alert alert-danger">
        <p>
            <strong>Te laat! </strong>
            {{ $event->name }} is helemaal uitverkocht.
        </p>

        <p>
            Annulaties kunnen zorgen voor extra vrije tickets, zet je daarvoor op de
            <a href="{{ action('WaitingListController@waitingList', [ $event->id ]) }}">wachtlijst</a>.
        </p>
    </div>
@elseif(isset($event) && !$event->hasSaleStarted())
    <div class="alert alert-danger">
        <p>
            <strong>De ticketverkoop voor {{$event->name}} is nog niet gestart.</strong>
        </p>

        <p>
            Om op de hoogte te blijven kan je wel al
            <a href="{{ action('WaitingListController@waitingList', [ $event->id ]) }}">pre-registreren</a>.
        </p>
    </div>
@endif

<table class="table tickets">

    <tr>
        <th class="col-md-3">Type</th>
        <th class="col-md-2">Prijs</th>

        @if($showAvailableTickets)
            <th class="col-md-2">Beschikbaar</th>
        @endif

        <th class="col-md-3"></th>

        <th class="col-md-2"></th>
    </tr>

    <?php $first = true; ?>
    @foreach($ticketCategories as $ticketCategory)
        <tr <?php if($first) { $first = false; } else { echo 'class="inactive"'; } ?> >
            <td >
                {{ $ticketCategory->name }}
                @if($ticketCategory->eventDates->count() > 0)
                    <br />
                    <small>
                    {{ $ticketCategory->getDatesForDisplay() }}
                    </small>
                @endif
            </td>
            <td >
                {{ $ticketCategory->getFormattedTotalPrice() }}
            </td>

            @if($showAvailableTickets)
                <td>
                    {{ $ticketCategory->countAvailableTickets() }}
                </td>
            @endif

            <td>
                <strong>
                    @foreach ($ticketCategory->getAvailabilityWarnings() as $warning)
                        {{ $ticketCategory->errorToString($warning) }}<br>
                    @endforeach
                </strong>
            </td>

            <td >
                @if($ticketCategory->isAvailable())
                    <a href="{{ action('EventController@register', [ $event->id, $ticketCategory->id] ) }}" class="btn btn-default">{{ $event->getOrderLabel() }}</a>
                @else
                    {{ $ticketCategory->errorToString($ticketCategory->getAvailableError()) }}
                @endif
            </td>
        </tr>
    @endforeach
</table>

@if($event->hasFiniteTickets())
    <p>Geen ticket kunnen bemachtigen? Onbetaalde tickets worden na {{ \App\Models\Event::ORDER_TIMEOUT_MINUTES }} minuten terug vrijgegeven.</p>
@endif
