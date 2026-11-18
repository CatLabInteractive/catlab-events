@extends('layouts/home')

@section('title'){{ $series->name }}@endsection
@section('description'){{ trim(strip_tags($series->teaser)) }}@endsection

@section('bodyClass')kv-page {{ $series->getColourClass() }}@endsection

@section('content')

<?php
    // A series of packages lists "other packages"; a series of dated quizzes lists all dates.
    $isPackageSeries = $events && count($events) > 0 && collect($events)->every(function ($e) { return $e->isPackage(); });
    $listedEvents = $events ? collect($events) : collect();
    if ($isPackageSeries && $nextEvent) {
        $listedEvents = $listedEvents->reject(function ($e) use ($nextEvent) { return $e->id === $nextEvent->id; });
    }
?>

    @if(!$nextEvent)
        {{-- Without a next quiz there is no hero to carry the series name: a slim head instead. --}}
        <div class="kv-pagehead kv-pagehead-series {{ $series->getColourClass() }}">
            <div class="kv-wrap">
                <h1 class="kv-series kv-series-h1"><i aria-hidden="true"></i>{{ $series->name }}</h1>
            </div>
        </div>
    @endif

    @if($nextEvent)
        @include('blocks.event-hero', [ 'heroEvent' => $nextEvent, 'heroOnSeriesPage' => true ])
    @endif

    @if($series->teaser || $series->hasVideo())
        <section class="kv-sec">
            <div class="kv-wrap">
                <h2 class="kv-sec-title">Wat kan je verwachten?</h2>
                <div class="kv-text">
                    {!! $series->teaser !!}
                    @if($series->hasVideo())
                        <p class="kv-links"><a class="popup" href="{{ $series->getVideoUrl() }}"><i class="fa fa-play" aria-hidden="true"></i>Bekijk de video</a></p>
                    @endif
                </div>
            </div>
        </section>
    @endif

    @if($events && !($isPackageSeries && $listedEvents->count() === 0))
        <section class="kv-sec" id="tickets">
            <div class="kv-wrap">
                @if($isPackageSeries)
                    <h2 class="kv-sec-title">Andere quizpakketten <small>Kies een pakket voor meer info en tickets.</small></h2>
                @else
                    <h2 class="kv-sec-title">Alle data <small>Kies een quiz voor praktische info en tickets.</small></h2>
                @endif

                <div>
                    @if($listedEvents->count() === 0)
                        <p class="kv-text">Er zijn nog geen quizzen aangekondigd, maar hou deze pagina in de gaten.</p>
                    @else
                        <div class="kv-cards">
                            @foreach($listedEvents as $event)
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
                    @include('blocks.past-events', [ 'pastEvents' => $pastEvents ])
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
