@if(organisation())

    {{-- An explicit page image wins; series pages fall back to the series header. --}}
    @if(!empty($ogImageUrl))
        <meta property="og:image" content="{{ $ogImageUrl }}" />
    @elseif(isset($series) && $series->header)
        <meta property="og:image" content="{{ $series->header->getUrl([ 'width' => 1200, 'height' => 630 ]) }}" />
    @elseif(isset($nextEvent) && $nextEvent->series && $nextEvent->series->header)
        <meta property="og:image" content="{{ $nextEvent->series->header->getUrl([ 'width' => 1200, 'height' => 630 ]) }}" />
    @else
        <meta property="og:image" content="https://www.quizfabriek.be/images/share/QuizWitz-Backstay-event.jpg" />
    @endif
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="description" content="@yield('description', organisation()->getDescription())">
    <meta property="og:description" content="@yield('description', organisation()->getDescription())">
    <meta property="og:site_name" content="{{ organisation()->name }}" />

    <script type="application/ld+json">{!! json_encode(organisation()->getJsonLD(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

    @yield('jsonld-content')

@endif
