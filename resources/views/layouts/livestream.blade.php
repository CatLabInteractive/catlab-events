<!DOCTYPE html>
<html lang="{{ mb_substr(app()->getLocale(), 0, 2) }}">
<head>

    @include('layouts.blocks.gtag')

    @if(isset($livestream))
        <title>{{ $organisation->name }} - {{ $livestream->title }}</title>
    @else
        <title>{{ $organisation->name }} - Livestream</title>
    @endif

    @if($organisation->favicon)
        <link rel="shortcut icon" type="image/png" href="{{ $organisation->favicon->getUrl() }}"/>
    @endif

    <style>

        @font-face {
            font-family: 'SharpGroteskSmBold25-Regular';
            src: url('/fonts/SharpGroteskSmBold25-Regular.ttf');
        }

        @font-face {
            font-family: 'SharpGroteskBook20-Regular';
            src: url('/fonts/SharpGroteskBook20-Regular.ttf');
        }

        body {
            background: #004e66;
            color: white;
            margin: 0;
            padding: 0;
            font-family: 'SharpGroteskBook20-Regular';
        }

        body.embed {
            margin: 0px;
            padding: 0px;
            padding-bottom: 20px;
        }

        h1 {
            font-family: 'SharpGroteskSmBold25-Regular';
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
</head>
<body @if($embed)class="embed"@endif>

@include('blocks.chatwoot', [ 'livestream' => true ])

<div>

    <div id="container">
        @yield('content')
    </div>

</div>

<script type="text/javascript" src="{{ mix('js/livestream.js') }}"></script>
@yield('script')
</body>
</html>
