@extends('layouts/front')

@section('title')
    @if($order->isCancelled())
        Betaling mislukt
    @elseif($order->isPending())
        Bijna klaar
    @else
        Bedankt!
    @endif
@endsection

@php
    /** @var \App\Models\Order $order */
    /** @var \App\Models\Event $event */
    $startsLater = $firstDate && $firstDate->startDate && $firstDate->startDate->isFuture();

    // Packets whose quiz only opens on a later date get their link by mail on that date.
    $playLink = $order->isAccepted() && $order->play_link && !($isPackage && $startsLater) ? $order->play_link : null;
@endphp

@section('bodyClass')kv-page {{ $event->getColourClass() }}@endsection

@section('content')

    <section class="kv-sec">
    <div class="kv-wrap kv-wrap-narrow">
    <div class="order-thanks">

        @if($order->isCancelled())
            <div class="alert alert-danger order-thanks-retry">
                <p><strong>Oh nee!</strong> De betaling is mislukt. Er werd niets aangerekend.</p>

                <a href="{{ $retryUrl }}" class="btn btn-danger">Probeer het opnieuw</a>
            </div>
        @endif

        {{-- The ticket --}}
        <div class="order-ticket">
            <div class="order-ticket-poster @if($event->poster) order-ticket-poster-image @endif"
                @if($event->poster)
                    style="background-image: linear-gradient(to bottom, rgba(31,51,38,.15), rgba(31,51,38,.85)), url('{{ $event->poster->getUrl([ 'width' => 800 ]) }}')"
                @endif
            >
                <small>{{ $isPackage ? 'Quizpakket' : ($livestreamUrl ? 'Online quiz' : 'Live quiz') }}</small>
                <strong>{{ $event->name }}</strong>
            </div>

            <dl class="order-ticket-rows">
                @if($order->group)
                    <div><dt>Team</dt><dd>{{ $order->group->name }}</dd></div>
                @endif
                <div><dt>Bestelling</dt><dd>#{{ $order->id }}</dd></div>

                @if(!$isPackage && $firstDate && $firstDate->startDate)
                    <div>
                        <dt>Datum</dt>
                        <dd>
                            @foreach($eventDates as $eventDate)
                                {{ ucfirst($eventDate->startDate->translatedFormat('D j M, H:i')) }}@if(!$loop->last)<br />@endif
                            @endforeach
                        </dd>
                    </div>
                    @if($event->venue)
                        <div><dt>Locatie</dt><dd>{{ $event->venue->getShortLocation() }}</dd></div>
                    @endif
                @endif

                @if($isPackage)
                    @if($ticketCategory->max_players)
                        <div><dt>Spelers</dt><dd>max. {{ $ticketCategory->max_players }}</dd></div>
                    @endif
                    @if($startsLater)
                        <div><dt>Speelbaar vanaf</dt><dd>{{ $firstDate->startDate->translatedFormat('j F Y') }}</dd></div>
                    @elseif($order->play_link)
                        <div><dt>Speeltijd</dt><dd>72 uur na activatie</dd></div>
                    @endif
                @endif

                <div><dt>Ticket</dt><dd>{{ $ticketCategory->name }}</dd></div>
            </dl>

            <div class="order-ticket-perforation"></div>

            <div class="order-ticket-status order-ticket-status-{{ $order->isAccepted() ? 'ok' : ($order->isPending() ? 'pending' : 'failed') }}">
                @if($order->isAccepted())
                    <i class="fa fa-check"></i> Bevestigd
                @elseif($order->isPending())
                    <i class="fa fa-clock-o"></i> Betaling wordt verwerkt
                @else
                    <i class="fa fa-times"></i> Betaling mislukt
                @endif
            </div>
        </div>

        {{-- What now? --}}
        @if($order->isPending())
            <div class="order-thanks-mail">
                <i class="fa fa-envelope-o"></i>
                <p>
                    <strong>We verwerken je betaling.</strong>
                    Zodra die is goedgekeurd, sturen we
                    {{ $isPackage ? 'de link om te spelen' : 'je tickets' }}
                    @if($email) naar <strong>{{ $email }}</strong>.@else per e-mail.@endif
                    Dat duurt meestal maar een paar minuten.
                </p>
            </div>
        @elseif($order->isAccepted())
            <h3 class="order-thanks-title">Wat nu?</h3>
            <ol class="order-thanks-steps">

                @if($playLink)
                    <li>
                        <strong>Start de quiz</strong>
                        <p>Open de link op het scherm waar iedereen naar kijkt, bijvoorbeeld een laptop of tv.</p>
                        <a href="{{ $playLink }}" class="btn btn-primary order-thanks-play" target="_blank" rel="noopener">Start de quiz <i class="fa fa-arrow-right"></i></a>
                        <div class="order-thanks-link">
                            <input type="text" readonly value="{{ $playLink }}" id="order-play-link" aria-label="Link om te spelen">
                            <button type="button" class="btn btn-default btn-sm" data-copy="#order-play-link">Kopieer</button>
                        </div>
                    </li>
                    <li>
                        <strong>Activeer wanneer je klaar bent</strong>
                        <p>Je mag de link gerust al openen: de quiz start pas na activatie. Daarna kan je 72 uur spelen.</p>
                    </li>
                    <li>
                        <strong>Bewaar de mail</strong>
                        <p>
                            We stuurden de link en de uitleg ook
                            @if($email) naar <strong>{{ $email }}</strong>.@else per e-mail.@endif
                            Niets ontvangen? Kijk even in je spam.
                        </p>
                    </li>

                @elseif($isPackage)
                    <li>
                        <strong>Check je mailbox</strong>
                        <p>
                            We stuurden een bevestiging
                            @if($email) naar <strong>{{ $email }}</strong>.@else per e-mail.@endif
                            Niets ontvangen? Kijk even in je spam.
                        </p>
                    </li>
                    <li>
                        <strong>Wacht op de link</strong>
                        <p>
                            @if($startsLater)
                                Vanaf {{ $firstDate->startDate->translatedFormat('j F Y') }} krijg je een mail met de link en de instructies om de quiz thuis te spelen.
                            @else
                                Je krijgt een mail met de link en de instructies om de quiz thuis te spelen.
                            @endif
                        </p>
                    </li>

                @else
                    <li>
                        <strong>Check je mailbox</strong>
                        <p>
                            Je tickets en alle praktische info zijn onderweg
                            @if($email) naar <strong>{{ $email }}</strong>.@else per e-mail.@endif
                            Niets ontvangen na 10 minuten? Kijk even in je spam.
                        </p>
                    </li>
                    @if($firstDate && $firstDate->startDate)
                        <li>
                            <strong>Zet het in je agenda</strong>
                            <p>
                                @if($firstDate->doorsDate)
                                    {{ ucfirst($firstDate->startDate->translatedFormat('l j F Y')) }}: deuren open om {{ $firstDate->doorsDate->format('H:i') }}, de quiz start om {{ $firstDate->startDate->format('H:i') }}.
                                @else
                                    {{ ucfirst($firstDate->startDate->translatedFormat('l j F Y')) }} om {{ $firstDate->startDate->format('H:i') }}.
                                @endif
                                @if(count($eventDates) > 1)
                                    Je ticket geldt voor {{ count($eventDates) }} data, ze staan allemaal in het agendabestand.
                                @endif
                            </p>
                            <a href="{{ $calendarUrl }}" class="btn btn-default"><i class="fa fa-calendar-plus-o"></i> Toevoegen aan agenda</a>
                        </li>
                    @endif
                    @if($livestreamUrl)
                        <li>
                            <strong>Speel online mee</strong>
                            <p>Je kan het spel nu al opstarten. De link staat ook in je mail.</p>
                            <a href="{{ $livestreamUrl }}" class="btn btn-primary order-thanks-play">Start spel <i class="fa fa-arrow-right"></i></a>
                        </li>
                    @elseif($event->venue)
                        <li>
                            <strong>Kom quizzen</strong>
                            <p>{{ $event->venue->getAddressFull() }}. Neem je team mee!</p>
                        </li>
                    @endif
                @endif
            </ol>
        @endif

        <div class="order-thanks-footer">
            @auth
                <a href="{{ action('OrderController@view', [ $order->id ]) }}" class="btn btn-default">Bekijk je bestelling</a>
            @endauth

            @if($order->isAccepted() && $event->getFacebookEventUrl())
                <p>
                    Zet je ook 'aanwezig' op ons <a href="{{ $event->getFacebookEventUrl() }}"><i class="fa fa-facebook"></i> Facebook-evenement</a>? Dank je! <i class="fa fa-heart"></i>
                </p>
            @endif
        </div>
    </div>

    <script>
        document.querySelectorAll('[data-copy]').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.querySelector(button.getAttribute('data-copy'));
                var done = function () { button.textContent = 'Gekopieerd!'; };
                input.select();
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(input.value).then(done, function () { document.execCommand('copy'); done(); });
                } else {
                    document.execCommand('copy');
                    done();
                }
            });
        });
    </script>

    @if($trackConversion)

        <script type="text/javascript">

            // load the page again to avoid double tracking.
            var redirect_url = '{{ $redirectUrl }}';
            // fallback
            setTimeout(function() {
                window.location = redirect_url;
            }, 2000);


            var trackerData = {!! json_encode([
                'event' => 'order.confirmed',
                'orderId' => $order->id,
                'orderEvent' => $order->ticketCategory->event->name,
                'orderEventId' => $order->ticketCategory->event->id,
                'ticketCategory' => $order->ticketCategory->name,
                'ticketCategoryId' => $order->ticketCategory->id,
                'orderPrice' => $order->ticketCategory->getTotalPrice()
            ]) !!};

            trackerData.eventCallback = function() {
                window.location = redirect_url;
            };

            dataLayer.push(trackerData);

        </script>
    @endif

    </div>
    </section>
@endsection
