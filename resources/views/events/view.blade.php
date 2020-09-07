@extends('layouts/front')

@section('title'){{ $event->name }}@if($event->series) - {{ $event->series->name }}@endif @endsection

@section('content')

    <section>
        <div class="container">

            <div class="row">

                <div class="col-md-12">
                @if($event->series)
                    <h2 class="intro-title">{{ $event->series->name }}</h2>
                @endif
                <h3 class="intro-sub-title">{{ $event->name }}</h3>
                </div>
            </div>

            <div class="row">

                @if($event->poster)
                    <div class="col-md-3">
                        <a class="image-popup" href="{{ $event->poster->getUrl([ 'width' => 1200, 'height' => 1692 ]) }}">
                            <img data-src="{{ $event->poster->getUrl([ 'width' => 300 ]) }}"
                                 class="lazy img-responsive" />
                        </a>
                    </div>

                    <div class="col-md-9">
                @else
                    <div class="col-md-12">
                @endif

                    <p class="date">
                        {{ ucfirst($event->startDate->formatLocalized('%A')) }} {{ $event->startDate->formatLocalized('%-d %B %Y') }}, {{ $event->startDate->format('H:i') }}
                        @if($event->doorsDate)
                            (deuren open: {{ $event->doorsDate->format('H:i') }})
                        @endif
                        @if($event->venue)
                        - {{ $event->venue->city }}
                        @endif
                    </p>

                    @if($event->description)
                        <div class="description">
                            {!! $event->description !!}
                        </div>
                    @endif

                    @if(count($event->authors) > 0)
                        <p><strong>Auteurs:</strong>
                            @foreach($event->authors as $person){{ $loop->first ? '' : ', ' }}<a href="{{ $person->getUrl() }}">{{ $person->name }}</a>@endforeach
                        </p>
                    @endif

                    @if(count($event->presenters) > 0)
                        <p><strong>Presentatoren:</strong>
                            @foreach($event->presenters as $person){{ $loop->first ? '' : ', ' }}<a href="{{ $person->getUrl() }}">{{ $person->name }}</a>@endforeach
                        </p>
                    @endif

                    @if(count($event->musicians) > 0)
                        <p><strong>Muziek:</strong>
                            @foreach($event->musicians as $person){{ $loop->first ? '' : ', ' }}<a href="{{ $person->getUrl() }}">{{ $person->name }}</a>@endforeach
                        </p>
                    @endif

                    @if($event->competition)
                        <p>
                            <strong>Competitie:</strong>
                            <a href="{{ action('CompetitionController@show', $event->competition->id) }}">
                                {{ $event->competition->name }}
                            </a>
                        </p>
                    @endif

                    <div>
                        <p>
                            @if($event->event_url)
                                <a href="{{ $event->event_url }}" class="btn btn-default">Meer informatie</a>
                            @endif

                            @if($event->getLiveStreamUrl() && !$event->hasTickets())
                                <a href="{{ $event->getLiveStreamUrl() }}" class="btn btn-primary"><i class="fa fa-play"></i> Livestream</a>
                            @endif

                            @if($event->hasScores())
                                <a href="{{ action('EventController@scores', [ $event->id ]) }}" class="btn btn-default">Eindscore</a>
                            @endif

                            @if($event->canRegister())
                                <a href="{{ action('EventController@selectTicketCategory', [ $event->id ]) }}"
                                   class="btn btn-primary">Registreren</a>
                            @endif

                            @if($event->getFacebookEventUrl())
                                <a href="{{ $event->getFacebookEventUrl() }}" class="btn btn-primary" target="_blank"><i
                                            class="fa fa-facebook"></i></a>
                            @endif
                        </p>
                    </div>

                    @if ($event->isSelling())
                        <p class="price">
                            @if($event->team_size)
                                Tickets: {!! $event->getFormattedPublishedPrice(true) !!} per team (max {{$event->team_size}} spelers)
                            @else
                                Tickets: {!! $event->getFormattedPublishedPrice(true) !!}
                            @endif

                            <?php $priceDetails = $event->getPublishedPriceDetails(true); ?>
                            @if($priceDetails)<br />
                                <span class="details">*: {!! $priceDetails !!}</span>
                            @endif
                        </p>
                    @endif

                        <!--
                    <p class="alert alert-success">Op zoek naar ploegmaten? Vind ze op
                        <a href="https://www.quizploeg.com/">Quizzer zkt. ploeg</a>.</p>
                        -->

                </div>

                        <!--
                <div class="col-md-3">
                    <div
                        class="fb-page"
                        data-href="https://www.facebook.com/quizfabriek/"
                        data-tabs="events"
                        data-small-header="true"
                        data-adapt-container-width="true"
                        data-hide-cover="true"
                        data-show-facepile="true"
                    >
                        <blockquote cite="https://www.facebook.com/quizfabriek/" class="fb-xfbml-parse-ignore">
                            <a href="https://www.facebook.com/quizfabriek/">De Quizfabriek</a>
                        </blockquote>
                    </div>
                </div>
                -->
            </div>
            </div>
        </div>
    </section>

    @if($event->canRegister())
        <section>
            <div class="container">

                <div class="row">


                        <div class="col-md-12">

                            <p class="intro-title">
                                {{ $event->startDate->formatLocalized('%-d %B %Y') }}, {{ $event->startDate->format('H:i') }}
                            </p>
                            <h3 class="intro-sub-title">Tickets</h3>

                            @include('blocks.tickets-table')
                        </div>
                </div>
            </div>
        </section>
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
                        @else

                        @endif

                        <p><a href="{{ $series->getUrl() }}" class="btn btn-primary">Meer over {{ $series->name }}</a>
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

    @if($attendees && count($attendees) > 0)

        <section>
            <div class="container">
                <div class="row">

                    <div class="col-md-12">
                        <h3>Deelnemers</h3>
                        @if($isAdmin)
                            <a href="{{ action('EventController@exportMembers', [ $event->id ]) }}" class="btn btn-default">Email
                                csv</a>
                            <a href="{{ action('EventController@exportSales', [ $event->id ]) }}" class="btn btn-default">Deelnemers
                                csv</a>
                            <a href="{{ action('EventController@exportSalesTimeline', [ $event->id ]) }}" class="btn btn-default">Historiek registraties</a>
                            <a href="{{ action('EventController@exportClearing', [ $event->id ]) }}" class="btn btn-default">Clearing
                                pdf</a>
                            <br><br>
                        @endif

                        <table class="table">
                            @foreach($attendees as $attendee)
                                <tr>
                                    <td>
                                        <a href="{{ $attendee->getUrl() }}">{{ $attendee->name }}</a>
                                    </td>
                                </tr>
                            @endforeach
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
            ->get();
    ?>
    @if(count($alternatives) > 0)
        <section class="ts-intro">
            <div class="container">

                <h2 class="intro-title">Meer quizzen</h2>
                <h3 class="intro-sub-title">Kom je ook?</h3>

                <?php $index = 0; ?>
                @foreach($alternatives as $v)

                    @if ($index % 2 === 0)
                        @if($index !== 0)
                            </div>
                        @endif
                        <div class="row">
                    @endif

                    <div class="col-md-6">

                        <div class="row">
                        @if($v->poster)
                            <div class="col-xs-4">
                                <a class="image-popup" href="{{ $v->poster->getUrl([ 'width' => 1200, 'height' => 1692 ]) }}">
                                    <img data-src="{{ $v->poster->getUrl([ 'width' => 300 ]) }}"
                                         class="img-responsive lazy" />
                                </a>
                            </div>

                            <div class="col-xs-8">
                        @else
                            <div class="col-xs-12">
                        @endif

                            <h4><a href="{{ $v->getUrl() }}">{{ $v->name }}</a></h4>
                            <p>
                                <strong>
                                @if($v->series)
                                    {{ $v->series->name }}<br />
                                @endif

                                @if($v->venue)
                                    {{ $v->startDate->format('d M H:i') }} -
                                    {{ $v->venue->getShortLocation() }}
                                @endif
                                </strong>
                            </p>

                            @if($v->series)
                                {!! $v->series->teaser!!}
                            @endif

                            <p>
                                @if($v->event_url)
                                    <a href="{{ $v->event_url }}">Meer informatie</a>
                                @else
                                    <a href="{{ $v->getUrl() }}">Meer informatie</a>
                                @endif
                            </p>

                            </div>
                        </div>
                    </div>
                    <?php $index ++; ?>
                @endforeach
                </div>
            </div>
        </section>
    @endif

@endsection

@section('jsonld-content')
    <script type="application/ld+json">{!! json_encode($event->getJsonLD(), JSON_PRETTY_PRINT) !!}</script>
@endsection

@section('critical-css')
    <style>{!! file_get_contents(resource_path('criticalcss/events-view_critical.min.css')) !!}</style>
@endsection
