@extends('layouts/home')

@section('title'){{ $series->name }}@endsection
@section('description'){{ trim(strip_tags($series->teaser)) }}@endsection

@section('content')

    @if($nextEvent)
        <section id="banner">
            <div class="banner-item bg-overlay"
                 @if($series->header)
                    style="background-image:url('{{ $series->header->getUrl([ 'width' => 1280, 'height' => 768 ]) }}')"
                 @endif
            >

                <script>NEXT_EVENT_DATE = '<?php echo $nextEvent->startDate->format('D, d M Y H:i:s O'); ?>';</script>
                <?php $countdown = \App\Tools\CountdownHelper::getCountdown($nextEvent->startDate); ?>
                <div class="container">
                    <div class="banner-content">
                        <div class="banner-content-left">
                            <div class="countdown">
                                <div class="counter-day">
                                    <span class="days">{{ $countdown['days'] }}</span>
                                    <div class="smalltext">Dagen</div>
                                </div>
                                <div class="counter-hour">
                                    <span class="hours">{{ $countdown['hours'] }}</span>
                                    <div class="smalltext">Uren</div>
                                </div>
                                <div class="counter-minute">
                                    <span class="minutes">{{ $countdown['minutes'] }}</span>
                                    <div class="smalltext">Minuten</div>
                                </div>
                                <div class="counter-second">
                                    <span class="seconds">{{ $countdown['seconds'] }}</span>
                                    <div class="smalltext">Seconden</div>
                                </div>
                            </div><!-- Countdown end -->

                            <h1 class="banner-title">{{ $nextEvent->name }}</h1>
                            <h2 class="banner-subtitle">
                                {{ $series->name }}
                                <br />{{ $nextEvent->startDate->formatLocalized('%A %-d %B %Y, %k:%M') }}
                                @if($nextEvent->venue)
                                    <br />{{ $nextEvent->venue->getShortLocation() }}
                                @endif
                            </h2>

                            @if($urgencyMessage = $nextEvent->getUrgencyMessage())
                                <span class="lastTickets">{{$urgencyMessage}}</span>
                            @endif

                            <p class="banner-btn">

                                @if($nextEvent->event_url)
                                    <a href="{{ $nextEvent->event_url }}" class="btn btn-primary">Meer informatie</a>
                                @else
                                    <a href="{{ $nextEvent->getUrl() }}" class="btn btn-primary">Meer informatie</a>
                                @endif

                                @if($nextEvent->isSelling())
                                    <a href="{{ action('EventController@selectTicketCategory', [ $nextEvent->id ] ) }}" class="btn btn-primary"><i class="fa fa-ticket"></i> Inschrijven</a>
                                @elseif($nextEvent->isSoldOut())
                                    <a href="{{ action('WaitingListController@waitingList', [ $nextEvent->id ]) }}" class="btn btn-danger"><i class="fa fa-ticket"></i> {{ $nextEvent->getNotSellingReason() }} / Wachtlijst</a>
                                @elseif($nextEvent->willSell())
                                    <a href="{{ action('WaitingListController@waitingList', [ $nextEvent->id ]) }}" class="btn btn-success"><i class="fa fa-ticket"></i> Pre-registratie</a>
                                @elseif($nextEvent->hasTickets())
                                    <a class="btn btn-danger"><i class="fa fa-ticket"></i> {{ $nextEvent->getNotSellingReason() }}</a>
                                @endif

                                @if($nextEvent->getLiveStreamUrl() && $nextEvent->hasTickets())
                                    <a href="{{ $nextEvent->getLiveStreamUrl() }}" class="btn btn-primary"><i class="fa fa-play"></i> Livestream</a>
                                @endif

                                @if($nextEvent->getFacebookEventUrl())
                                    <a href="{{ $nextEvent->getFacebookEventUrl() }}" class="btn btn-success" aria-label="Facebook event {{$nextEvent->name}}" rel="noopener" target="_blank"><i class="fa fa-facebook-official"></i></a>
                                @endif

                                <!--<a href="#" class="btn btn-border">Watch Trailer</a>-->
                            </p>

                            @if ($nextEvent->isSelling())
                                <p class="price">
                                    @if($nextEvent->team_size)
                                        <a href="{{ $nextEvent->getUrl() }}" class="plain">Tickets: {!! $nextEvent->getFormattedPublishedPrice(true) !!} per team (max {{$nextEvent->team_size}} spelers)</a><br />
                                    @else
                                        <a href="{{ $nextEvent->getUrl() }}" class="plain">Tickets: {!! $nextEvent->getFormattedPublishedPrice(true) !!}</a><br />
                                    @endif
                                    <?php $priceDetails = $nextEvent->getPublishedPriceDetails(true); ?>
                                    @if($priceDetails)
                                        <span class="details">*: {!! $priceDetails !!}</span>
                                    @endif
                                </p>
                            @endif
                        </div><!-- Banner content wrap end -->
                    </div><!-- Banner content end -->
                </div><!-- Container end -->
            </div><!-- Banner item end -->
        </section><!-- Section banner end -->

    @else

        <div id="page-banner-area" class="page-banner-area bg-overlay"
            @if($series->header)
                style="background-image:url('{{ $series->header->getUrl([ 'width' => 1280, 'height' => 768 ]) }}')"
            @endif
        >
            <!-- Subpage title start -->
            <div class="page-banner-title">
                <div class="text-center">
                    <h2>{{ $series->name }}</h2>
                </div>
            </div><!-- Subpage title end -->
        </div><!-- Page Banner end -->

    @endif

    <section id="ts-intro" class="ts-intro @if($nextEvent) no-padding @endif">
        <div class="container">
            <div class="row">
                <div class="col-xs-12 col-sm-12 col-md-6">
                    <div class="gap-60"></div>

                    @if($series->teaser)

                        <h2 class="intro-title">{{ $series->name }}</h2>
                        <h3 class="intro-sub-title">Wat kan je verwachten?</h3>

                        {!! $series->teaser !!}
                        <!--<p><a href="#" class="btn btn-primary">Know More</a></p>-->

                    @else

                        <h2 class="intro-title">{{ $series->name }}</h2>
                        <h3 class="intro-sub-title">Video!</h3>

                    @endif
                </div>

                @if($series->hasVideo())
                    <div class="col-xs-12 col-sm-12 col-md-6">
                        <div class="intro-video">
                            <img class="img-responsive lazy" data-src="{{ $series->getVideoThumbnail() }}" alt="Quizreeks demo video" title="Quizreeks demo video" />
                            <a class="popup" href="{{ $series->getVideoUrl() }}">
                                <div class="video-icon">
                                    <i class="fa fa-play"></i>
                                </div>
                            </a>
                        </div>
                    </div>
                @endif

                <!-- Col end -->
            </div>
            <!-- Content row 1 end -->


        </div>
        <!-- Container end -->
    </section>


    @if($nextEvent)
        <section id="ts-intro" class="ts-intro">
            <div class="container">

                <div class="row">

                    <div class="col-md-9">

                        <h2 class="intro-title">Volgende quiz</h2>
                        <h3 class="intro-sub-title">{{ $nextEvent->name }}</h3>
                        <div>
                        {!! $nextEvent->description !!}
                        </div>

                        <a href="{{ $nextEvent->getUrl() }}" class="btn btn-primary">Meer informatie</a>

                        @if($nextEvent->hasTickets())
                            <a href="{{ $nextEvent->getUrl() }}" class="btn btn-primary">Inschrijven</a>
                        @endif

                        @if($nextEvent->getLiveStreamUrl() && $nextEvent->hasTickets())
                            <a href="{{ $nextEvent->getLiveStreamUrl() }}" class="btn btn-primary"><i class="fa fa-play"></i> Livestream</a>
                        @endif

                    </div>

                    <div class="col-md-3">
                        @if($nextEvent->poster)
                            <a class="image-popup" href="{{ $nextEvent->poster->getUrl([ 'width' => 1200, 'height' => 1692 ]) }}">
                                <img data-src="{{ $nextEvent->poster->getUrl([ 'width' => 300 ]) }}" class="img-responsive lazy" alt="{{ $nextEvent->name }} poster" title="Poster '{{ $nextEvent->name }}'" />
                            </a>
                        @endif
                    </div>

                </div>


            </div>
            <!-- Container end -->
        </section>
    @endif

    <section class="ts-intro">
        <div class="container">
            @if($events)
                <h2 class="intro-sub-title">Binnenkort</h2>

                @if(count($events) === 0)
                    <p>Er zijn nog geen evenementen aangekondigd, maar hou deze pagina in de gaten.</p>
                @endif

                {{-- @component('blocks.newsletterbutton', [ 'text' => 'Hou me op de hoogte' ])@endcomponent --}}

                @foreach($events as $v)
                    <div class="row">
                        <div class="col-md-3 hero-small-date text-center">
                            <h3>{{ $v->startDate->format('d') }}</h3>
                            <h4>{{ $v->startDate->formatLocalized('%B %Y') }}</h4>
                        </div>
                        <div class="col-md-9 hero-small-date-content">
                            <h2 class="banner-title">
                                <a href="{{ $v->getUrl() }}">{{ $v->name }}</a>
                                @if($v->isSoldOut(true))
                                    <span class="lastTickets">Uitverkocht!</span>
                                @endif
                            </h2>
                            <p class="banner-subtitle">
                                {{ $v->startDate->format('H:i') }}
                                @if($v->venue) - {{ $v->venue->getShortLocation() }}@endif
                            </p>
                        </div>
                    </div>

                    <br />
                @endforeach

                @if(count($pastEvents) > 0)
                    <h2 class="intro-sub-title">Reeds voorbij</h2>
                    @component('blocks.eventtable', [ 'events' => $pastEvents ])
                    @endcomponent
                @endif
            @endif

            <a href="{{ action('EventController@calendar') }}">Alle voorbije evenementen</a>
        </div>
    </section>

    {{--
    @if($series->description)
        <section class="no-padding">
            <div class="container">

                <h2 class="intro-title">Meer informatie</h2>
                <h3 class="intro-sub-title">{{ $series->name }}</h3>
                <div class="row">
                    @if($series->logo)
                        <div class="col-md-3">
                            <img class="img-responsive lazy" data-src="{{ $series->logo->getUrl() }}" alt="{{ $series->name }} logo" title="{{ $series->name }} logo" />
                        </div>

                        <div class="col-md-9">
                            {!! $series->description !!}
                        </div>
                    @else
                        <div class="col-md-12">
                            {!! $series->description !!}
                        </div>
                    @endif


                </div>

            </div>
        </section>
    @endif
    --}}

    {{--
    <section class="no-padding">
        <div class="container">
            <h2 class="intro-title">Nieuwsbrief</h2>
            <h3 class="intro-sub-title">Hou me op de hoogte</h3>
            <p>Wil je op de hoogte blijven van onze evenementen? Schrijf je in op onze nieuwsbrief en we houden je op de hoogte van al onze evenementen.</p>
            @component('blocks.newsletterbutton', [ 'text' => 'Inschrijven voor de nieuwsbrief' ])@endcomponent
        </div>
    </section>
    --}}

    {{--@include('layouts.blocks.sponsor')--}}

    @include('layouts.blocks.blog')

    @include('blocks.facts')

@endsection



@section('jsonld-content')
    <script type="application/ld+json"><?php
        $data = [
            '@context' => 'http://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => []
        ];

        $p = 0;
        foreach (organisation()->series()->get() as $v) {
            $p ++;
            $data['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $p,
                'item' => $v->getJsonLD()
            ];
        }

        echo json_encode($data, JSON_PRETTY_PRINT);
        ?>
    </script>
@endsection

@section('critical-css')
    <style>{!! file_get_contents(resource_path('criticalcss/series-view_critical.min.css')) !!}</style>
@endsection
