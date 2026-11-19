{{--
    One event in a list: a colour field with the poster (any ratio, never
    cropped) next to the date, name and place.

    @param \App\Models\Event $cardEvent
--}}
<a class="kv-card {{ $cardEvent->getColourClass() }}" href="{{ $cardEvent->getUrl() }}">
    <span class="kv-card-image">
        @if($cardEvent->poster)
            <img src="{{ $cardEvent->poster->getUrl([ 'width' => 400 ]) }}" alt="Poster '{{ $cardEvent->name }}'" loading="lazy" />
        @endif
    </span>
    <span class="kv-card-body">
        <span class="kv-card-date">
            @if($cardEvent->isPackage())
                <b>Quizpakket</b>
            @elseif($cardEvent->startDate)
                <b>{{ ucfirst($cardEvent->startDate->translatedFormat('D j M')) }}</b> &middot; {{ $cardEvent->startDate->format('H:i') }}
            @endif
        </span>
        <span class="kv-card-title">{{ $cardEvent->name }}</span>
        @if(!$cardEvent->isFinished() && $cardEvent->canRegister())
            @if($cardEvent->isSoldOut())
                <span class="kv-card-flag">Uitverkocht</span>
            @elseif($cardEvent->isLastTicketsWarning())
                <span class="kv-card-flag">Laatste {{ $cardEvent->countAvailableTickets() }} tickets</span>
            @endif
        @endif
        <span class="kv-card-meta">
            @if($cardEvent->series){{ $cardEvent->series->name }}@endif
            @if($cardEvent->series && $cardEvent->venue) &middot; @endif
            @if($cardEvent->venue){{ $cardEvent->venue->getShortLocation() }}@endif
        </span>
    </span>
</a>
