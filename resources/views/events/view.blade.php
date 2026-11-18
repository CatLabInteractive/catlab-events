@extends('layouts/front')

@section('title'){{ $event->name }}@if($event->series) - {{ $event->series->name }}@endif @endsection

@section('bodyClass')kv-page {{ $event->getColourClass() }}@endsection

<?php
    $canRegister = $event->canRegister();
    $willSell = $event->willSell();

    $credits = [
        'Eindredactie' => $event->producers,
        'Auteurs' => $event->authors,
        'Presentatoren' => $event->presenters,
        'Muziek' => $event->musicians,
        'Regie & techniek' => $event->technicians,
    ];
?>

@section('banner')
    @include('blocks.event-hero', [ 'heroEvent' => $event ])
@endsection

@section('content')

    @if($event->description || $event->competition)
        <section class="kv-sec" id="over">
            <div class="kv-wrap">
                <h2 class="kv-sec-title">Over deze quiz</h2>
                <div class="kv-text">
                    {!! $event->description !!}

                    @if($event->competition)
                        <p>
                            Deze quiz telt mee voor de competitie
                            <a href="{{ action('CompetitionController@show', $event->competition->id) }}">{{ $event->competition->name }}</a>.
                        </p>
                    @endif
                </div>
            </div>
        </section>
    @endif

    @if($canRegister)
        <section class="kv-sec" id="tickets">
            <div class="kv-wrap">
                <h2 class="kv-sec-title">Tickets <small>Tik op een ticket om te bestellen.</small></h2>
                <div>
                    @include('blocks.tickets-table')
                </div>
            </div>
        </section>
    @endif

    @if(count($event->people) > 0)
        <section class="kv-sec kv-band">
            <div class="kv-wrap">
                <h2 class="kv-sec-title">Credits</h2>
                <dl class="kv-credits">
                    @foreach($credits as $role => $people)
                        @if(count($people) > 0)
                            <div>
                                <dt>{{ $role }}</dt>
                                <dd>@foreach($people as $person){{ $loop->first ? '' : ', ' }}<a href="{{ $person->getUrl() }}">{{ $person->name }}</a>@endforeach</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </div>
        </section>
    @endif

    <?php $venue = $event->venue; ?>
    @if($venue)
        @include('blocks.venue')
    @endif

    @if($event->series)
        <?php $series = $event->series; ?>
        <section class="kv-sec">
            <div class="kv-wrap">
                <h2 class="kv-sec-title">Quizreeks <small>{{ $series->name }}</small></h2>
                <div class="kv-text">
                    @if($series->teaser)
                        {!! $series->teaser !!}
                    @endif

                    <p class="kv-links">
                        <a href="{{ $series->getUrl($event) }}">Meer over {{ $series->name }}</a>
                        @if($series->hasFaq())
                            <a href="{{ $series->getUrl($event) }}#faq">Vaak gestelde vragen</a>
                        @endif
                        @if($series->hasVideo())
                            <a class="popup" href="{{ $series->getVideoUrl() }}">@include('blocks.icon', [ 'name' => 'play' ])Bekijk de video</a>
                        @endif
                    </p>
                </div>
            </div>
        </section>
    @endif

    @if(isset($eventDateAttendees) && $eventDateAttendees && $eventDateAttendees['maxGroups'] > 0)
        <section class="kv-sec">
            <div class="kv-wrap">
                <h2 class="kv-sec-title">Deelnemers</h2>
                <div class="table-responsive">
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
        </section>
    @endif

    <?php
        $alternatives = organisation()->events()
            ->where('id', '!=', $event->id)
            ->upcoming()
            ->published()
            ->withListRelations()
            ->get();
    ?>
    @if(count($alternatives) > 0)
        <section class="kv-sec">
            <div class="kv-wrap">
                <h2 class="kv-sec-title">Meer quizzen <small>Kom je ook?</small></h2>
                <div class="kv-cards">
                    @foreach($alternatives as $v)
                        @include('blocks.event-card', [ 'cardEvent' => $v ])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if($canRegister)
        {{-- On phones, a slim order bar shows once the order button in the hero and the tickets have scrolled away. --}}
        <div class="kv-buybar" id="kv-buybar" aria-hidden="true">
            <div class="kv-buybar-text">
                <strong>{{ $event->name }}</strong>
                @if($willSell)
                    <span>vanaf {!! strip_tags($event->getFormattedPublishedPrice(false)) !!}</span>
                @endif
            </div>
            <a href="{{ action('EventController@selectTicketCategory', [ $event->id ]) }}" class="kv-btn" tabindex="-1">
                {{ $event->getOrderLabel() }}
                <span class="kv-btn-dot" aria-hidden="true">&rarr;</span>
            </a>
        </div>
    @endif

@endsection

@section('jsonld-content')
    <script type="application/ld+json">{!! json_encode($event->getJsonLD(), JSON_PRETTY_PRINT) !!}</script>
@endsection
