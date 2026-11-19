<!-- Basic Page Needs
================================================== -->
<meta charset="utf-8">

@if(isset($canonicalUrl))
    <link rel="canonical" href="{{ $canonicalUrl }}" />
@endif

@if(!config('seo.indexable'))
    <meta name="robots" content="noindex, nofollow">
@endif

@stack('head')

<!-- Mobile Specific Metas
================================================== -->

<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

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
    <meta property="og:title" content="{{ $ogTitle ?? organisation()->name }}" />
@endif

<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="icon" type="image/png" href="/favicon-32x32.png" sizes="32x32">
<link rel="icon" type="image/png" href="/favicon-16x16.png" sizes="16x16">
<link rel="manifest" href="/manifest.json">
<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#1f3326">

<meta name="theme-color" content="#f3f1e9">

<meta property="fb:app_id" content="1124345767614916" />
<meta property="og:type" content="{{ $ogType ?? 'website' }}" />
<meta property="og:url" content="{{ \Request::url() }}" />

{{--
    The cookie banner does not block the first paint: its stylesheet is
    preloaded and its script deferred. The banner is set up on
    DOMContentLoaded, which runs after deferred scripts; Google Tag Manager
    (layouts/blocks/gtag) starts from the same handler, after the banner, so
    the order between the two is what it was.
--}}
<link rel="preconnect" href="https://cookies.catlab.eu" />
<link rel="preload" href="https://cookies.catlab.eu/cookie-consent.css" as="style" onload="this.onload=null;this.rel='stylesheet'"/>
<noscript><link rel="stylesheet" href="https://cookies.catlab.eu/cookie-consent.css"/></noscript>
<script src="https://cookies.catlab.eu/cookie-consent.js" defer></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof CookieConsent === 'undefined') {
            return;
        }
        window.cookieConsent = new CookieConsent({
            privacyPolicyUrl: '/docs/en/privacy',
            buttonPrimaryClass: 'kv-cc-primary',
            buttonSecondaryClass: 'kv-cc-secondary',
            // The chat bubble waits until a choice is made, so it never covers the banner.
            postSelectionCallback: function () {
                if (window.kvLoadChat) {
                    window.kvLoadChat();
                }
            }
        });
        window.cookieConsent.enableCrossDomain([
            'catlab.eu',
            'quizfabriek.be'
        ]);
    });
</script>
