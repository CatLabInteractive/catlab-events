@extends('layouts/front')

@section('title'){{ $event->name }}@if($event->series) - {{ $event->series->name }}@endif @endsection

<?php
    $heroSeries = ($event->series && $event->series->header) ? $event->series : organisation()->getRandomSeries();
    $heroBackground = ($heroSeries && $heroSeries->header) ? $heroSeries->header->getUrl([ 'width' => 1280, 'height' => 768 ]) : null;

    $posterRatio = ($event->poster && $event->poster->width && $event->poster->height)
        ? $event->poster->width / $event->poster->height
        : null;
    // Wide posters get the bigger column on desktop, tall ones the smaller.
    $heroShape = $event->poster ? (($posterRatio !== null && $posterRatio >= 1.15) ? 'ev-hero-wide' : 'ev-hero-tall') : 'ev-hero-noposter';

    $canRegister = $event->canRegister();
    $willSell = $event->willSell();
    $priceDetails = $willSell ? $event->getPublishedPriceDetails(true) : null;

    $credits = [
        'Eindredactie' => $event->producers,
        'Auteurs' => $event->authors,
        'Presentatoren' => $event->presenters,
        'Muziek' => $event->musicians,
        'Regie & techniek' => $event->technicians,
    ];
?>

@section('banner')
    <div class="ev-hero {{ $heroShape }}">
        @if($heroBackground)
            <div class="ev-hero-bg" style="background-image: url('{{ $heroBackground }}')" aria-hidden="true"></div>
        @endif

        <div class="container">
            <div class="ev-hero-grid">

                @if($event->poster)
                    <div class="ev-hero-poster">
                        @include('blocks.poster', [ 'poster' => $event->poster, 'alt' => $event->name, 'width' => 900 ])
                    </div>
                @endif

                <div class="ev-hero-info">
                    @if($event->series)
                        <p class="ev-kicker"><a href="{{ $event->series->getUrl($event) }}">{{ $event->series->name }}</a></p>
                    @elseif($event->isPackage())
                        <p class="ev-kicker">Quizpakket</p>
                    @endif

                    <h1 class="ev-title">{{ $event->name }}</h1>

                    <ul class="ev-facts">
                        @if($event->isPackage())
                            <li><i class="fa fa-home" aria-hidden="true"></i> Quizpakket, speel wanneer je wil</li>
                        @else
                            <li><i class="fa fa-calendar" aria-hidden="true"></i> {{ $event->getEventDateDescription() }}</li>
                        @endif

                        @if($event->venue)
                            <li><i class="fa fa-map-marker" aria-hidden="true"></i> {{ $event->venue->name }}, {{ $event->venue->city }}</li>
                        @endif

                        @if($event->team_size)
                            <li><i class="fa fa-users" aria-hidden="true"></i> Max {{ $event->team_size }} spelers per team</li>
                        @endif
                    </ul>

                    <div class="ev-cta" id="ev-cta">
                        @if($willSell)
                            <div class="ev-cta-price">
                                <span class="ev-cta-price-label">Tickets vanaf</span>
                                <span class="ev-cta-price-amount">{!! $event->getFormattedPublishedPrice(true) !!}</span>
                                @if($event->team_size)
                                    <span class="ev-cta-price-unit">per team</span>
                                @endif
                                @if($priceDetails)
                                    <span class="ev-cta-price-details">*: {!! $priceDetails !!}</span>
                                @endif
                            </div>
                        @endif

                        @if($canRegister)
                            <a href="{{ action('EventController@selectTicketCategory', [ $event->id ]) }}" class="ev-button ev-button-primary">
                                {{ $event->getOrderLabel() }} <i class="fa fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        @endif

                        <?php
                            $hasSecondary = $event->event_url
                                || ($event->getLiveStreamUrl() && !$event->hasTickets())
                                || $event->hasScores()
                                || $event->getFacebookEventUrl();
                        ?>
                        @if($hasSecondary)
                            <div class="ev-cta-secondary">
                                @if($event->getLiveStreamUrl() && !$event->hasTickets())
                                    <a href="{{ $event->getLiveStreamUrl() }}" class="ev-button ev-button-primary"><i class="fa fa-play" aria-hidden="true"></i> Livestream</a>
                                @endif

                                @if($event->hasScores())
                                    <a href="{{ action('EventController@scores', [ $event->id ]) }}" class="ev-button ev-button-ghost"><i class="fa fa-trophy" aria-hidden="true"></i> Eindscore</a>
                                @endif

                                @if($event->event_url)
                                    <a href="{{ $event->event_url }}" class="ev-button ev-button-ghost"><i class="fa fa-external-link" aria-hidden="true"></i> Meer informatie</a>
                                @endif

                                @if($event->getFacebookEventUrl())
                                    <a href="{{ $event->getFacebookEventUrl() }}" class="ev-button ev-button-ghost" target="_blank" title="Facebook"><i class="fa fa-facebook" aria-hidden="true"></i><span class="sr-only">Facebook</span></a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

@section('content')

    <div class="ev-body @if($canRegister) ev-body-with-tickets @endif">

        <div class="ev-main">
            @if($event->description)
                <div class="ev-description description">
                    {!! $event->description !!}
                </div>
            @endif

            @if(count($event->people) > 0)
                <h2 class="ev-section-title">Credits</h2>
                <dl class="ev-credits">
                    @foreach($credits as $role => $people)
                        @if(count($people) > 0)
                            <div>
                                <dt>{{ $role }}</dt>
                                <dd>@foreach($people as $person){{ $loop->first ? '' : ', ' }}<a href="{{ $person->getUrl() }}">{{ $person->name }}</a>@endforeach</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            @endif

            @if($event->competition)
                <p class="ev-competition">
                    <i class="fa fa-trophy" aria-hidden="true"></i>
                    Competitie:
                    <a href="{{ action('CompetitionController@show', $event->competition->id) }}">
                        {{ $event->competition->name }}
                    </a>
                </p>
            @endif
        </div>

        @if($canRegister)
            <aside class="ev-aside" id="tickets">
                <div class="ev-aside-card">
                    <h2 class="ev-section-title">Tickets</h2>
                    @include('blocks.tickets-table')
                </div>
            </aside>
        @endif

    </div>

    @if($canRegister)
        {{-- On phones, a slim order bar shows once the order button in the hero has scrolled away. --}}
        <div class="ev-buybar" id="ev-buybar" aria-hidden="true">
            <div class="ev-buybar-text">
                <strong>{{ $event->name }}</strong>
                @if($willSell)
                    <span>vanaf {!! strip_tags($event->getFormattedPublishedPrice(false)) !!}</span>
                @endif
            </div>
            <a href="{{ action('EventController@selectTicketCategory', [ $event->id ]) }}" class="ev-button ev-button-primary" tabindex="-1">{{ $event->getOrderLabel() }}</a>
        </div>

        <script>
            (function () {
                var bar = document.getElementById('ev-buybar');
                var watched = [ document.getElementById('ev-cta'), document.getElementById('tickets') ];
                if (!bar || !('IntersectionObserver' in window)) {
                    return;
                }
                var visible = {};
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        visible[entry.target.id] = entry.isIntersecting;
                    });
                    var show = !visible['ev-cta'] && !visible['tickets'];
                    bar.classList.toggle('ev-buybar-visible', show);
                    bar.setAttribute('aria-hidden', show ? 'false' : 'true');
                });
                watched.forEach(function (el) {
                    if (el) {
                        observer.observe(el);
                    }
                });
            })();
        </script>
    @endif

    <?php $venue = $event->venue; ?>
    @if($venue)
        @include('blocks.venue')
    @endif

    @if($event->series)
        <?php $series = $event->series; ?>
        <section id="ts-intro" class="ts-intro">
            <div class="container">
                <div class="row">
                    <div class="col-xs-12 col-sm-12 col-md-6">

                        <h2 class="intro-title">Quizreeks</h2>
                        <h3 class="intro-sub-title">{{ $series->name }}</h3>

                        @if($series->teaser)
                            {!! $series->teaser !!}
                        @endif

                        <p>
                            <a href="{{ $series->getUrl($event) }}" class="btn btn-primary">Meer over {{ $series->name }}</a>

                            @if($series->hasFaq())
                                <a href="{{ $series->getUrl($event) }}#faq" class="btn btn-primary">Vaak gestelde vragen</a>
                            @endif
                        </p>

                    </div>
                    <!-- Col end -->

                    @if($series->hasVideo())
                        <div class="col-xs-12 col-sm-12 col-md-6">
                            <img class="img-responsive lazy" data-src="{{ $series->getVideoThumbnail() }}" alt=""/>
                            <a class="popup" href="{{ $series->getVideoUrl() }}">
                                <div class="video-icon">
                                    <i class="fa fa-play"></i>
                                </div>
                            </a>
                        </div>
                    @endif
                    <!-- Col end -->
                </div>
                <!-- Content row 1 end -->


            </div>
            <!-- Container end -->
        </section>
    @endif

    @if(isset($eventDateAttendees) && $eventDateAttendees && $eventDateAttendees['maxGroups'] > 0)
        <section>
            <div class="container">
                <div class="row">

                    <div class="col-md-12">
                        <h3>Deelnemers</h3>
                        <table class="table">
                            <tr>
                                <th>#</th>
                                @foreach($eventDateAttendees['eventDates'] as $v)
                                    <th>{{ ucfirst($v['date']->translatedFormat('l j F')) }}</th>
                                @endforeach
                            </tr>

                            @for($i = 0; $i < $eventDateAttendees['maxGroups']; $i ++)
                                <tr>
                                    <td>{{$i + 1}}</td>
                                    @foreach($eventDateAttendees['eventDates'] as $v)
                                        <td>
                                            @if(isset($v['groups'][$i]))
                                                <a href="{{ $v['groups'][$i]->getUrl() }}">
                                                    {{ $event->getAttendeeName($v['groups'][$i]) }}
                                                </a>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endfor
                        </table>
                    </div>


                </div>
            </div>
        </section>
    @endif

    <?php
        $alternatives = organisation()->events()
            ->where('id', '!=', $event->id)
            ->upcoming()
            ->published()
            ->get();
    ?>
    @if(count($alternatives) > 0)
        <section class="ev-more">
            <h2 class="intro-title">Meer quizzen</h2>
            <h3 class="intro-sub-title">Kom je ook?</h3>

            <div class="ev-cards">
                @foreach($alternatives as $v)
                    <a class="ev-card" href="{{ $v->getUrl() }}">
                        <span class="ev-card-image">
                            @if($v->poster)
                                <span class="ev-poster-backdrop" style="background-image: url('{{ $v->poster->getUrl([ 'width' => 100 ]) }}')" aria-hidden="true"></span>
                                <img src="{{ $v->poster->getUrl([ 'width' => 600 ]) }}" alt="Poster '{{ $v->name }}'" loading="lazy" />
                            @else
                                <span class="ev-card-placeholder" aria-hidden="true"><i class="fa fa-question"></i></span>
                            @endif
                        </span>
                        <span class="ev-card-body">
                            <span class="ev-card-kicker">
                                @if($v->series)
                                    {{ $v->series->name }}
                                @elseif($v->isPackage())
                                    Quizpakket
                                @endif
                            </span>
                            <span class="ev-card-title">{{ $v->name }}</span>
                            <span class="ev-card-meta">
                                @if($v->isPackage())
                                    Speel wanneer je wil
                                @else
                                    @if($v->startDate)
                                        {{ $v->startDate->translatedFormat('j M H:i') }}
                                    @endif
                                    @if($v->venue)
                                        &middot; {{ $v->venue->getShortLocation() }}
                                    @endif
                                @endif
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

@endsection

@section('jsonld-content')
    <script type="application/ld+json">{!! json_encode($event->getJsonLD(), JSON_PRETTY_PRINT) !!}</script>
@endsection
