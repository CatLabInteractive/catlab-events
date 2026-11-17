@if(\App\Http\Controllers\EventController::getValidWaitingListToken($event))
    <div class="ev-notice">
        <p>
            Je staat op de <a href="{{ action('WaitingListController@waitingList', [ $event->id ]) }}">wachtlijst</a> voor {{ $event->name }}.
            Als er nog tickets vrijgekomen zijn, kan je deze hieronder bestellen.
        </p>
    </div>
@elseif(isset($event) && $event->isSoldOut() && !$event->isFinished())
    <div class="ev-notice ev-notice-error">
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
    <div class="ev-notice ev-notice-error">
        <p>
            <strong>De ticketverkoop voor {{$event->name}} is nog niet gestart.</strong>
        </p>

        <p>
            Om op de hoogte te blijven kan je wel al
            <a href="{{ action('WaitingListController@waitingList', [ $event->id ]) }}">pre-registreren</a>.
        </p>
    </div>
@endif

{{-- Each ticket is a card you tap anywhere on; it opens the register form with that ticket selected. --}}
<div class="ev-tickets">
    @foreach($ticketCategories as $ticketCategory)
        <?php $isAvailable = $ticketCategory->isAvailable(); ?>
        @if($isAvailable)
            <a class="ev-ticket" href="{{ action('EventController@register', [ $event->id, $ticketCategory->id ]) }}">
        @else
            <div class="ev-ticket ev-ticket-unavailable">
        @endif

            <span class="ev-ticket-head">
                <span class="ev-ticket-name">{{ $ticketCategory->name }}</span>
                <span class="ev-ticket-price">{{ $ticketCategory->getFormattedTotalPrice() }}</span>
            </span>

            @if($ticketCategory->description)
                <span class="ev-ticket-description">{{ $ticketCategory->description }}</span>
            @endif

            @if($ticketCategory->eventDates->count() > 0)
                <span class="ev-ticket-meta">{{ $ticketCategory->getDatesForDisplay() }}</span>
            @endif

            @if($showAvailableTickets && $ticketCategory->countAvailableTickets() !== null)
                <span class="ev-ticket-meta">Nog {{ $ticketCategory->countAvailableTickets() }} beschikbaar</span>
            @endif

            @foreach ($ticketCategory->getAvailabilityWarnings() as $warning)
                <span class="ev-ticket-warning">{{ $ticketCategory->errorToString($warning) }}</span>
            @endforeach

            @if($isAvailable)
                <span class="ev-ticket-action">{{ $event->getOrderLabel() }} <i class="fa fa-arrow-right" aria-hidden="true"></i></span>
            @else
                <span class="ev-ticket-warning">{{ $ticketCategory->errorToString($ticketCategory->getAvailableError()) }}</span>
            @endif

        @if($isAvailable)
            </a>
        @else
            </div>
        @endif
    @endforeach
</div>

@if($event->hasFiniteTickets())
    <p class="ev-tickets-footnote">Geen ticket kunnen bemachtigen? Onbetaalde tickets worden na {{ \App\Models\Event::ORDER_TIMEOUT_MINUTES }} minuten terug vrijgegeven.</p>
@endif
