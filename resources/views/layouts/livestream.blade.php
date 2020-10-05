<!DOCTYPE html>
<html lang="{{ mb_substr(app()->getLocale(), 0, 2) }}">
<head>

    @if(isset($livestream))
        <title>{{ $organisation->name }} - {{ $livestream->title }}</title>
    @else
        <title>{{ $organisation->name }} - Livestream</title>
    @endif

    @if($organisation->favicon)
        <link rel="shortcut icon" type="image/png" href="{{ $organisation->favicon->getUrl() }}"/>
    @endif

    <style type="text/css">

        body {
            color: white;
            background: #303030;
            margin: 0;
            padding: 0;

            font-family: "Lato", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
        }

        body.embed {
            margin: 0;
            padding: 0;
            padding-bottom: 20px;
        }

        h1 {
            font-family: "Lato", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
        }

        body a {
            color: #ff8c57;
        }

        #container {
            width: 80%;
            box-sizing: border-box;
            margin: 20px auto;
        }

        body.embed #container {
            width: 100%;
            margin: 0;
        }

        body.embed p,
        body.embed h1,
        body.embed h2,
        body.embed h3 {
            padding: 5px 20px;
        }

        .twitch .twitch-video {
            padding-top: 56.25%;
            position: relative;
            height: 0;
        }

        .twitch .twitch-video iframe {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
        }

        .twitch .twitch-chat {
            height: 400px;
        }

        .twitch .twitch-chat iframe {
            width: 100%;
            height: 100%;
        }

        @media  screen and (min-width: 850px) {
            .twitch {
                position: relative;
            }

            .twitch .twitch-chat + .twitch-video {
                width: 75%;
                padding-top: 42.1875%;
            }

            .twitch .twitch-chat {
                width: 25%;
                height: auto;
                position: absolute;
                top: 0;
                right: 0;
                bottom: 0;
            }
        }

    </style>

    <style type="text/css">
        {!! $organisation->livestream_css !!}
    </style>
</head>
<body @if($embed)class="embed"@endif>

@include('blocks.chatwoot', [ 'livestream' => true, 'organisation' => $organisation ])

<div>

    <div id="container">
        @yield('content')
    </div>

</div>

<script type="text/javascript" src="{{ mix('js/livestream.js') }}"></script>
@yield('script')
</body>
</html>
