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

    <div class="body-inner compact">

        @include('layouts.blocks.header')


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
