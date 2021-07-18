<!-- Basic Page Needs
================================================== -->
<meta charset="utf-8">

@if(isset($canonicalUrl))
    <link rel="canonical" href="{{ $canonicalUrl }}" />
@endif

<!-- Mobile Specific Metas
================================================== -->

<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">

<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

@if(organisation())
    @if (array_key_exists('title', View::getSections()))
        <title>@yield('title') – {{organisation()->name}}</title>
    @elseif(isset($nextEvent))
        <title>{{ $nextEvent->getPageTitle() }} – {{organisation()->name}}</title>
    @else
        <title>{{organisation()->name}}</title>
    @endif

    <link rel="sitemap" type="application/xml" title="{{organisation()->name}} Sitemap" href="/sitemap.xml" />
    <meta property="og:title" content="{{ organisation()->name }}" />
@endif

<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="icon" type="image/png" href="/favicon-32x32.png" sizes="32x32">
<link rel="icon" type="image/png" href="/favicon-16x16.png" sizes="16x16">
<link rel="manifest" href="/manifest.json">
<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">

<meta name="theme-color" content="#000000">

<meta property="fb:app_id" content="1124345767614916" />
<meta property="og:type" content="website" />
<meta property="og:url" content="{{ \Request::url() }}" />

<link rel="stylesheet" href="https://cookies.catlab.eu/cookie-consent.css"/>
<script src="https://cookies.catlab.eu/cookie-consent.js"></script>

<script>
    var cookieConsent = new CookieConsent({
        privacyPolicyUrl: '/docs/en/privacy'
    });
    cookieConsent.enableCrossDomain([
        'catlab.eu',
        'quizfabriek.be'
    ]);
</script>
