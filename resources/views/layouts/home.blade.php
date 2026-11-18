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

    @if (Session::has('message'))
        <div class="alert alert-info">{{ Session::get('message') }}</div>
    @endif

    @yield('content')

    @yield('second-content')

    @include('layouts/blocks.footer')


</div>

</body>
</html>
