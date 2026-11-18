<!DOCTYPE html>
<html lang="{{ mb_substr(app()->getLocale(), 0, 2) }}">
<head>
    @include('layouts.blocks.head')
    @include('layouts.blocks.style')
    @include('layouts.blocks.seo')
    @include('layouts.blocks.gtag')
</head>
<body>
@include('blocks.chatwoot', [ 'organisation' => organisation() ])

<div class="body-inner compact">

    @include('layouts.blocks.header')


    <div id="page-banner-area" class="page-banner-area bg-overlay"
         @if(isset($series) && $series->header)
         style="background-image:url('{{ $series->header->getUrl([ 'width' => 1280, 'height' => 768 ]) }}')"
         @else
         <?php $randomSeries = organisation() ? organisation()->getRandomSeries() : null; ?>
         @if($randomSeries && $randomSeries->header)
         style="background-image:url('{{ $randomSeries->header->getUrl([ 'width' => 1280, 'height' => 768 ]) }}')"
            @endif
            @endif
    >
        <!-- Subpage title start -->
        <div class="container page-banner-title">
            <div class="text-center container">
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
