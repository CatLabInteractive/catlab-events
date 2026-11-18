@if(\App\Http\Controllers\EventController::getValidWaitingListToken($event))
    <div class="kv-notice">
        <p>
            Je staat op de <a href="{{ action('WaitingListController@waitingList', [ $event->id ]) }}">wachtlijst</a> voor {{ $event->name }}.
            Als er nog tickets vrijgekomen zijn, kan je deze hieronder bestellen.
        </p>
    </div>
@elseif(isset($event) && $event->isSoldOut() && !$event->isFinished())
    <div class="kv-notice kv-notice-alert">
        <p>
            <strong>Te laat!</strong>
            {{ $event->name }} is helemaal uitverkocht.
        </p>
        <p>
            Annulaties kunnen zorgen voor extra vrije tickets, zet je daarvoor op de
            <a href="{{ action('WaitingListController@waitingList', [ $event->id ]) }}">wachtlijst</a>.
        </p>
    </div>
@elseif(isset($event) && !$event->hasSaleStarted())
    <div class="kv-notice kv-notice-alert">
        <p><strong>De ticketverkoop voor {{ $event->name }} is nog niet gestart.</strong></p>
        <p>
            Om op de hoogte te blijven kan je wel al
            <a href="{{ action('WaitingListController@waitingList', [ $event->id ]) }}">pre-registreren</a>.
        </p>
    </div>
@endif

{{-- Each ticket is a colour tile you tap anywhere on; it opens the order form with that ticket selected. --}}
<div class="kv-tickets">
    @foreach($ticketCategories as $ticketCategory)
        <?php $isAvailable = $ticketCategory->isAvailable(); ?>
        @if($isAvailable)
            <a class="kv-ticket" href="{{ action('EventController@register', [ $event->id, $ticketCategory->id ]) }}">
        @else
            <div class="kv-ticket kv-ticket-unavailable">
        @endif

            <span class="kv-ticket-name">{{ $ticketCategory->name }}</span>

            @if($ticketCategory->description)
                <span class="kv-ticket-description">{{ $ticketCategory->description }}</span>
            @endif

            @if($ticketCategory->eventDates->count() > 0)
                <span class="kv-ticket-meta">{{ $ticketCategory->getDatesForDisplay() }}</span>
            @endif

            @if($showAvailableTickets && $ticketCategory->countAvailableTickets() !== null)
                <span class="kv-ticket-meta">Nog {{ $ticketCategory->countAvailableTickets() }} beschikbaar</span>
            @endif

            @foreach ($ticketCategory->getAvailabilityWarnings() as $warning)
                <span class="kv-ticket-warning">{{ $ticketCategory->errorToString($warning) }}</span>
            @endforeach

            <span class="kv-ticket-price">{{ $ticketCategory->getFormattedTotalPrice() }}</span>

            @if($isAvailable)
                <span class="kv-ticket-action">{{ $event->getOrderLabel() }} &rarr;</span>
            @else
                <span class="kv-ticket-warning">{{ $ticketCategory->errorToString($ticketCategory->getAvailableError()) }}</span>
            @endif

        @if($isAvailable)
            </a>
        @else
            </div>
        @endif
    @endforeach
</div>

@if($event->hasFiniteTickets())
    <p class="kv-footnote">Geen ticket kunnen bemachtigen? Onbetaalde tickets worden na {{ \App\Models\Event::ORDER_TIMEOUT_MINUTES }} minuten terug vrijgegeven.</p>
@endif
