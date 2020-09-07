<!DOCTYPE html>
<html lang="{{ mb_substr(app()->getLocale(), 0, 2) }}">
<head>
    @include('layouts.blocks.head')
    @include('layouts.blocks.style')
    @include('layouts.blocks.seo')
    @include('layouts.blocks.gtag')
</head>
<body>
    <div id="fb-root"></div>
    <script>(function(d, s, id) {
            var js, fjs = d.getElementsByTagName(s)[0];
            if (d.getElementById(id)) return;
            js = d.createElement(s); js.id = id;
            js.src = 'https://connect.facebook.net/nl_BE/sdk.js#xfbml=1&version=v3.1&appId=1124345767614916&autoLogAppEvents=1';
            fjs.parentNode.insertBefore(js, fjs);
        }(document, 'script', 'facebook-jssdk'));</script>

    <div class="body-inner compact">

        <header id="header" class="header header-transparent">
            <div class="container">
                <div class="row">

                    @include('layouts.blocks.navigation')

                </div><!--/ Row end -->
            </div><!--/ Container end -->
        </header><!--/ Header end -->


        <div id="page-banner-area" class="page-banner-area bg-overlay"
            @if(isset($series) && $series->header)
                style="background-image:url('{{ $series->header->getUrl([ 'width' => 1280, 'height' => 768 ]) }}')"
            @else
                <?php $randomSeries = organisation()->getRandomSeries(); ?>
                @if($randomSeries && $randomSeries->header)
                    style="background-image:url('{{ $randomSeries->header->getUrl([ 'width' => 1280, 'height' => 768 ]) }}')"
                @endif
            @endif
        >
            <!-- Subpage title start -->
            <div class="page-banner-title">
                <div class="text-center">
                    <h2>@yield('title')</h2>
                </div>
            </div><!-- Subpage title end -->
        </div><!-- Page Banner end -->

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
