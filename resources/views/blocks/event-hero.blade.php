{{--
    The event hero: the poster on a colour field, next to the name, the facts
    and the order button. Used on the event page and, for the next quiz, on
    the series page.

    @param \App\Models\Event $heroEvent
    @param bool $heroOnSeriesPage  adds a link to the event page; the series name becomes the page's h1

    When the series has photos (Series > Photos in the admin), they fill the
    hero as a slow crossfade, fading into the series colour behind the text.
--}}
<?php
    $heroOnSeriesPage = $heroOnSeriesPage ?? false;

    $posterRatio = ($heroEvent->poster && $heroEvent->poster->width && $heroEvent->poster->height)
        ? $heroEvent->poster->width / $heroEvent->poster->height
        : null;
    // Wide posters get the bigger column on desktop, tall ones share it evenly.
    $heroPhotos = $heroEvent->series ? $heroEvent->series->getHeroPhotos() : [];
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
<div class="kv-hero {{ $heroShape }} @if(count($heroPhotos) > 0) kv-hero-photo @endif {{ $heroEvent->getColourClass() }}">

    @if(count($heroPhotos) > 0)
        <div class="kv-hero-photos" @if(count($heroPhotos) > 1) data-kv-slideshow @endif aria-hidden="true">
            @foreach($heroPhotos as $photoIndex => $heroPhoto)
                <?php
                    $photoSrcset = implode(', ', array_map(function ($w) use ($heroPhoto) {
                        return $heroPhoto->getUrl([ 'width' => $w ]) . ' ' . $w . 'w';
                    }, [ 800, 1400, 2000 ]));
                ?>
                @if($photoIndex === 0)
                    <img class="is-shown" src="{{ $heroPhoto->getUrl([ 'width' => 1400 ]) }}" srcset="{{ $photoSrcset }}" sizes="100vw" alt="" fetchpriority="high" />
                @else
                    <img data-src="{{ $heroPhoto->getUrl([ 'width' => 1400 ]) }}" data-srcset="{{ $photoSrcset }}" sizes="100vw" alt="" />
                @endif
            @endforeach
        </div>
    @endif

    @if($heroEvent->poster)
        <div class="kv-hero-field">
            @include('blocks.poster', [ 'poster' => $heroEvent->poster, 'alt' => $heroEvent->name, 'width' => 1000 ])
        </div>
    @endif

    <div class="kv-hero-info">
        @if($heroOnSeriesPage && $heroEvent->series)
            <h1 class="kv-series kv-series-h1"><i aria-hidden="true"></i>{{ $heroEvent->series->name }}<span class="kv-series-sub">volgende quiz</span></h1>
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

@once
    <script>
        // Hero slideshow: a slow crossfade between the series photos. Each photo
        // only loads just before it is shown; reduced motion keeps the first one.
        (function () {
            var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (reduce) {
                return;
            }
            Array.prototype.forEach.call(document.querySelectorAll('[data-kv-slideshow]'), function (show) {
                var images = show.querySelectorAll('img');
                var current = 0;

                function prepare(img) {
                    if (img.dataset.src) {
                        img.srcset = img.dataset.srcset;
                        img.src = img.dataset.src;
                        delete img.dataset.src;
                    }
                }

                prepare(images[1]);
                setInterval(function () {
                    if (document.hidden) {
                        return;
                    }
                    var next = (current + 1) % images.length;
                    var img = images[next];
                    if (!img.complete) {
                        return;
                    }
                    images[current].classList.remove('is-shown');
                    img.classList.add('is-shown');
                    current = next;
                    prepare(images[(current + 1) % images.length]);
                }, 6500);
            });
        })();
    </script>
@endonce
