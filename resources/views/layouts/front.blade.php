<!DOCTYPE html>
<html lang="{{ mb_substr(app()->getLocale(), 0, 2) }}">
<head>
    @include('layouts.blocks.head')
    @include('layouts.blocks.style')
    @include('layouts.blocks.seo')
    @include('layouts.blocks.gtag')
</head>
<body class="kv-site @yield('bodyClass')">
    @include('blocks.chatwoot', [ 'organisation' => organisation() ])

    <div id="fb-root"></div>
    <script>(function(d, s, id) {
            var js, fjs = d.getElementsByTagName(s)[0];
            if (d.getElementById(id)) return;
            js = d.createElement(s); js.id = id;
            js.src = 'https://connect.facebook.net/nl_BE/sdk.js#xfbml=1&version=v3.1&appId=1124345767614916&autoLogAppEvents=1';
            fjs.parentNode.insertBefore(js, fjs);
        }(document, 'script', 'facebook-jssdk'));</script>

    <div class="body-inner compact">

        <header id="header" class="header kv-header">
            <div class="container">
                <div class="row">

                    @include('layouts.blocks.navigation')

                </div><!--/ Row end -->
            </div><!--/ Container end -->
        </header><!--/ Header end -->


        @hasSection('banner')
            @yield('banner')
        @else
            <?php $pageHeadClass = (isset($series) && $series instanceof \App\Models\Series) ? $series->getColourClass() : 'kv-field-0'; ?>
            <div class="kv-pagehead {{ $pageHeadClass }}">
                <div class="kv-wrap">
                    <h1 class="kv-pagehead-title">@yield('title')</h1>
                </div>
            </div>
        @endif

        <section>
            <div class="container">

                @if (Session::has('message'))
                    <div class="alert alert-info">{{ Session::get('message') }}</div>
                @endif

                @yield('content')
            </div>
        </section>

        <section>
            <div class="container">
                @yield('second-content')
            </div>
        </section>

        @include('layouts/blocks.footer')


    </div>

</body>
</html>
