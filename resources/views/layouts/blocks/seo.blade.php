@if(isset($series) && $series->header)
    <meta property="og:image" content="{{ $series->header->getUrl([ 'width' => 1200, 'height' => 630 ]) }}" />
@elseif(isset($nextEvent) && $nextEvent->series && $nextEvent->series->header)
    <meta property="og:image" content="{{ $nextEvent->series->header->getUrl([ 'width' => 1200, 'height' => 630 ]) }}" />
@else
    <meta property="og:image" content="https://www.quizfabriek.be/images/share/QuizWitz-Backstay-event.jpg" />
@endif

<meta property="description" content="@yield('description', 'De quizfabriek organiseert elk jaar een aantal quizzes waarbij zowel nadruk wordt gelegd op kennis als op entertainment. Onze quizzes zijn dan ook wat minder traditioneel. Natuurlijk staat kennis centraal, maar laat je niet vangen door een ronde armworstelen, retoriek of zelfs bier-proeven.')">
<meta name="description" content="@yield('description', 'De quizfabriek organiseert elk jaar een aantal quizzes waarbij zowel nadruk wordt gelegd op kennis als op entertainment. Onze quizzes zijn dan ook wat minder traditioneel. Natuurlijk staat kennis centraal, maar laat je niet vangen door een ronde armworstelen, retoriek of zelfs bier-proeven.')">
<meta property="og:description" content="@yield('description', 'De quizfabriek organiseert elk jaar een aantal quizzes waarbij zowel nadruk wordt gelegd op kennis als op entertainment. Onze quizzes zijn dan ook wat minder traditioneel. Natuurlijk staat kennis centraal, maar laat je niet vangen door een ronde armworstelen, retoriek of zelfs bier-proeven.')">

<!--[if lt IE 9]>
    <script src="{{ asset('js/html5shiv.js') }}"></script>
    <script src="{{ asset('js/respond.min.js') }}"></script>
<![endif]-->

<script type="application/ld+json">{!! json_encode(organisation()->getJsonLD(), JSON_PRETTY_PRINT) !!}}</script>

<script type="application/ld+json">{!! json_encode(organisation()->getOrganisationJsonLd(), JSON_PRETTY_PRINT) !!}}</script>

@yield('jsonld-content')
