<!DOCTYPE html>
<html lang="{{ mb_substr(app()->getLocale(), 0, 2) }}">
<head>
    @include('layouts.blocks.head')
    @include('layouts.blocks.style')
    @include('layouts.blocks.seo')
    @include('layouts.blocks.gtag')
</head>
<body>

<div class="body-inner compact">

    <header id="header" class="header header-transparent">
        <div class="container">
            <div class="row">

                @include('layouts.blocks.navigation')

            </div><!--/ Row end -->
        </div><!--/ Container end -->
    </header><!--/ Header end -->

    @if (Session::has('message'))
        <div class="alert alert-info">{{ Session::get('message') }}</div>
    @endif

    @yield('content')

    @yield('second-content')

    @include('layouts/blocks.footer')


</div>

</body>
</html>
