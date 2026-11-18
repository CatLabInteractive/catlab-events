@extends('layouts/home')

@section('title'){{ $series->name }}@endsection
@section('description'){{ trim(strip_tags($series->teaser)) }}@endsection

@section('bodyClass')kv-page {{ $series->getColourClass() }}@endsection

@section('content')

    {{-- Series intro: name and teaser on the series colour. --}}
    <div class="kv-pagehead kv-pagehead-series {{ $series->getColourClass() }}">
        <div class="kv-wrap">
            <p class="kv-series"><i aria-hidden="true"></i>Quizreeks</p>
            <h1 class="kv-pagehead-title">{{ $series->name }}</h1>
            @if($series->teaser)
                <div class="kv-pagehead-lead">{!! $series->teaser !!}</div>
            @endif
            @if($series->hasVideo())
                <p class="kv-links"><a class="popup" href="{{ $series->getVideoUrl() }}"><i class="fa fa-play" aria-hidden="true"></i>Bekijk de video</a></p>
            @endif
        </div>
    </div>

    @if($nextEvent)
        @include('blocks.event-hero', [ 'heroEvent' => $nextEvent, 'heroOnSeriesPage' => true ])
    @endif

    @if($events)
        <section class="kv-sec" id="tickets">
            <div class="kv-wrap">
                <h2 class="kv-sec-title">Alle data <small>Kies een quiz voor praktische info en tickets.</small></h2>

                <div>
                    @if(count($events) === 0)
                        <p class="kv-text">Er zijn nog geen quizzen aangekondigd, maar hou deze pagina in de gaten.</p>
                    @else
                        <div class="kv-cards">
                            @foreach($events as $event)
                                @include('blocks.event-card', [ 'cardEvent' => $event ])
                            @endforeach
                        </div>
                    @endif

                    <p class="kv-links"><a href="{{ action('EventController@calendar') }}">Volledige kalender</a></p>
                </div>
            </div>
        </section>

        @if(count($pastEvents) > 0)
            <section class="kv-sec">
                <div class="kv-wrap">
                    <h2 class="kv-sec-title">Reeds voorbij</h2>
                    <div class="table-responsive">
                        @component('blocks.eventtable', [ 'events' => $pastEvents ])
                        @endcomponent
                    </div>
                </div>
            </section>
        @endif
    @endif

    @if($series->description)
        <section class="kv-sec kv-band">
            <div class="kv-wrap">
                <h2 class="kv-sec-title">Meer informatie</h2>
                <div class="kv-text">
                    @if($series->logo)
                        <img class="kv-series-logo" src="{{ $series->logo->getUrl([ 'width' => 240 ]) }}" alt="{{ $series->name }} logo" loading="lazy" />
                    @endif
                    {!! $series->description !!}
                </div>
            </div>
        </section>
    @endif

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
