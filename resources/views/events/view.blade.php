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
                        {{ $event->getEventDateDescription() }}

                        @if($event->venue)
                        - {{ $event->venue->city }}
                        @endif
                    </p>

                    @if($event->description)
                        <div class="description">
                            {!! $event->description !!}
                        </div>
                    @endif

                        <div>
                            <p>
                                @if($event->event_url)
                                    <a href="{{ $event->event_url }}" class="btn btn-default"><i class="fa fa-external-link"></i>  Meer informatie</a>
                                @endif

                                @if($event->getLiveStreamUrl() && !$event->hasTickets())
                                    <a href="{{ $event->getLiveStreamUrl() }}" class="btn btn-primary"><i class="fa fa-play"></i> Livestream</a>
                                @endif

                                @if($event->hasScores())
                                    <a href="{{ action('EventController@scores', [ $event->id ]) }}" class="btn btn-default">Eindscore</a>
                                @endif

                                @if($event->canRegister())
                                    <a href="{{ action('EventController@selectTicketCategory', [ $event->id ]) }}"
                                       class="btn btn-primary">{{ $event->getOrderLabel() }}</a>
                                @endif

                                @if($event->getFacebookEventUrl())
                                    <a href="{{ $event->getFacebookEventUrl() }}" class="btn btn-primary" target="_blank"><i
                                                class="fa fa-facebook"></i></a>
                                @endif
                            </p>
                        </div>

                        @if ($event->willSell())
                            <p class="price">
                                @if($event->team_size)
                                    Tickets: vanaf {!! $event->getFormattedPublishedPrice(true) !!} per team (max {{$event->team_size}} spelers)
                                @else
                                    Tickets: vanaf {!! $event->getFormattedPublishedPrice(true) !!}
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

                    @if(count($event->people) > 0)

                        <h4>Credits</h4>
                        @if(count($event->producers) > 0)
                            <p><strong>Eindredactie:</strong><br />
                                @foreach($event->producers as $person){{ $loop->first ? '' : ', ' }}<a href="{{ $person->getUrl() }}">{{ $person->name }}</a>@endforeach
                            </p>
                        @endif

                        @if(count($event->authors) > 0)
                            <p><strong>Auteurs:</strong><br />
                                @foreach($event->authors as $person){{ $loop->first ? '' : ', ' }}<a href="{{ $person->getUrl() }}">{{ $person->name }}</a>@endforeach
                            </p>
                        @endif

                        @if(count($event->presenters) > 0)
                            <p><strong>Presentatoren:</strong><br />
                                @foreach($event->presenters as $person){{ $loop->first ? '' : ', ' }}<a href="{{ $person->getUrl() }}">{{ $person->name }}</a>@endforeach
                            </p>
                        @endif

                        @if(count($event->musicians) > 0)
                            <p><strong>Muziek:</strong><br />
                                @foreach($event->musicians as $person){{ $loop->first ? '' : ', ' }}<a href="{{ $person->getUrl() }}">{{ $person->name }}</a>@endforeach
                            </p>
                        @endif

                        @if(count($event->technicians) > 0)
                            <p><strong>Regie & techniek:</strong><br />
                                @foreach($event->technicians as $person){{ $loop->first ? '' : ', ' }}<a href="{{ $person->getUrl() }}">{{ $person->name }}</a>@endforeach
                            </p>
                        @endif

                    @endif

                    @if($event->competition)
                        <p>
                            <strong>Competitie:</strong>
                            <a href="{{ action('CompetitionController@show', $event->competition->id) }}">
                                {{ $event->competition->name }}
                            </a>
                        </p>
                    @endif

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
                                {{ $event->getEventDateDescription() }}
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

                        <p>
                            <a href="{{ $series->getUrl() }}" class="btn btn-primary">Meer over {{ $series->name }}</a> 

                            @if($series->id === 8)
                                <a href="{{ $series->getUrl() }}#faq" class="btn btn-primary">Vaak gestelde vragen</a> 
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
                                    <th>{{ ucfirst($v['date']->formatLocalized('%A %-d %B')) }}</th>
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
                                    @if($v->startDate)
                                        {{ $v->startDate->format('d M H:i') }} -
                                    @endif
                                    {{ $v->venue->getShortLocation() }}
                                @endif
                                </strong>
                            </p>

                            @if($v->series && (!$event->series || $v->series->id !== $event->series->id))
                                {!! $v->series->teaser!!}
                            @elseif($v->description)
                                {!! $v->description !!}
                            @endif

                            <p>
                                @if($v->event_url)
                                    <a href="{{ $v->event_url }}"><i class="fa fa-external-link"></i> Meer informatie</a>,
                                @endif
                                <a href="{{ $v->getUrl() }}">Praktisch</a>
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
