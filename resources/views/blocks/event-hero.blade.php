{{--
    The event hero: the poster on a colour field, next to the name, the facts
    and the order button. Used on the event page and, for the next quiz, on
    the series page.

    @param \App\Models\Event $heroEvent
    @param bool $heroOnSeriesPage  adds a link to the event page
--}}
<?php
    $heroOnSeriesPage = $heroOnSeriesPage ?? false;

    $posterRatio = ($heroEvent->poster && $heroEvent->poster->width && $heroEvent->poster->height)
        ? $heroEvent->poster->width / $heroEvent->poster->height
        : null;
    // Wide posters get the bigger column on desktop, tall ones share it evenly.
    $heroShape = $heroEvent->poster ? (($posterRatio !== null && $posterRatio >= 1.15) ? 'kv-hero-wide' : 'kv-hero-tall') : 'kv-hero-noposter';

    $heroCanRegister = $heroEvent->canRegister();
    $heroWillSell = $heroEvent->willSell();
    $heroSoldOut = $heroEvent->hasFiniteTickets() && $heroEvent->isSoldOut() && !$heroEvent->isFinished();
    $heroPriceDetails = $heroWillSell ? $heroEvent->getPublishedPriceDetails(true) : null;
    $heroUrgency = $heroEvent->getUrgencyMessage();

    $heroFacts = [];
    $heroFacts[] = $heroEvent->isPackage() ? 'Quizpakket, speel wanneer je wil' : $heroEvent->getEventDateDescription();
    if ($heroEvent->venue) {
        $heroFacts[] = $heroEvent->venue->name . ', ' . $heroEvent->venue->city;
    }
    if ($heroEvent->team_size) {
        $heroFacts[] = 'Max ' . $heroEvent->team_size . ' spelers per team';
    }
?>
<div class="kv-hero {{ $heroShape }} {{ $heroEvent->getColourClass() }}">

    @if($heroEvent->poster)
        <div class="kv-hero-field">
            @include('blocks.poster', [ 'poster' => $heroEvent->poster, 'alt' => $heroEvent->name, 'width' => 1000 ])
        </div>
    @endif

    <div class="kv-hero-info">
        @if($heroOnSeriesPage)
            <p class="kv-series"><i aria-hidden="true"></i>Volgende quiz</p>
        @elseif($heroEvent->series)
            <p class="kv-series"><i aria-hidden="true"></i><a href="{{ $heroEvent->series->getUrl($heroEvent) }}">{{ $heroEvent->series->name }}</a></p>
        @elseif($heroEvent->isPackage())
            <p class="kv-series"><i aria-hidden="true"></i>Quizpakket</p>
        @endif

        @if($heroOnSeriesPage)
            <h2 class="kv-title"><a href="{{ $heroEvent->getUrl() }}">{{ $heroEvent->name }}</a></h2>
        @else
            <h1 class="kv-title">{{ $heroEvent->name }}</h1>
        @endif

        <ul class="kv-hero-facts">@foreach($heroFacts as $fact)<li>{{ $fact }}</li>@endforeach</ul>

        @if($heroWillSell || $heroCanRegister)
            <div class="kv-buy" id="kv-cta">
                @if($heroWillSell || $heroUrgency)
                    <div class="kv-buy-top">
                        @if($heroWillSell)
                            <p class="kv-price">
                                <span class="kv-price-label">Tickets vanaf</span>
                                <strong>{!! $heroEvent->getFormattedPublishedPrice(true) !!}</strong>
                                @if($heroEvent->team_size)
                                    <span class="kv-price-unit">per team</span>
                                @endif
                            </p>
                        @endif
                        @if($heroUrgency)
                            <span class="kv-card-flag">{{ $heroUrgency }}</span>
                        @endif
                    </div>
                @endif

                @if($heroSoldOut)
                    <a href="{{ action('WaitingListController@waitingList', [ $heroEvent->id ]) }}" class="kv-btn">
                        Uitverkocht, zet je op de wachtlijst
                        <span class="kv-btn-dot" aria-hidden="true">&rarr;</span>
                    </a>
                @elseif($heroCanRegister)
                    <a href="{{ action('EventController@selectTicketCategory', [ $heroEvent->id ]) }}" class="kv-btn">
                        {{ $heroEvent->getOrderLabel() }}
                        <span class="kv-btn-dot" aria-hidden="true">&rarr;</span>
                    </a>
                @endif

                @if($heroPriceDetails)
                    <p class="kv-price-details">* {!! $heroPriceDetails !!}</p>
                @endif
            </div>
        @endif

        <?php
            $hasLinks = $heroOnSeriesPage
                || $heroEvent->event_url
                || ($heroEvent->getLiveStreamUrl() && !$heroEvent->hasTickets())
                || $heroEvent->hasScores()
                || $heroEvent->getFacebookEventUrl()
                || $heroCanRegister;
        ?>
        @if($hasLinks)
            <p class="kv-links">
                @if($heroOnSeriesPage)
                    <a href="{{ $heroEvent->getUrl() }}">Praktisch &amp; tickets</a>
                @elseif($heroCanRegister)
                    <a href="#tickets">Alle tickets</a>
                @endif

                @if($heroEvent->getLiveStreamUrl() && !$heroEvent->hasTickets())
                    <a href="{{ $heroEvent->getLiveStreamUrl() }}"><i class="fa fa-play" aria-hidden="true"></i>Livestream</a>
                @endif

                @if($heroEvent->hasScores())
                    <a href="{{ action('EventController@scores', [ $heroEvent->id ]) }}">Eindscore</a>
                @endif

                @if($heroEvent->event_url)
                    <a href="{{ $heroEvent->event_url }}">Meer informatie</a>
                @endif

                @if($heroEvent->getFacebookEventUrl())
                    <a href="{{ $heroEvent->getFacebookEventUrl() }}" target="_blank" rel="noopener">Facebook</a>
                @endif
            </p>
        @endif
    </div>

</div>
