{{--
    The page shell of the public site: head, header, main and footer.
    layouts/front (page head + content), layouts/home (full-width content)
    and layouts/auth build on it. Pages fill the 'page' section through those.
--}}
<!DOCTYPE html>
<html lang="{{ mb_substr(app()->getLocale(), 0, 2) }}">
<head>
    @include('layouts.blocks.head')
    @include('layouts.blocks.style')
    @include('layouts.blocks.seo')
    @include('layouts.blocks.gtag')
</head>
<body class="kv-site @yield('bodyClass')">
    <a class="kv-skip" href="#main">Naar de inhoud</a>

    @include('blocks.chatwoot', [ 'organisation' => organisation() ])

    @include('layouts.blocks.header')

    <main id="main" tabindex="-1">
        @yield('page')
    </main>

    @include('layouts.blocks.footer')
</body>
</html>
