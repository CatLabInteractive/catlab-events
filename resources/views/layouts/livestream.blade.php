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
<body>

<script>
    (function(d,t) {
        var BASE_URL = "https://catlab-chatwoot.herokuapp.com";
        var g=d.createElement(t),s=d.getElementsByTagName(t)[0];
        g.src= BASE_URL + "/packs/js/sdk.js";
        s.parentNode.insertBefore(g,s);
        g.onload=function(){
            window.chatwootSDK.run({
                websiteToken: 'nw4VNZ5cM1oWkh4m5TzxqNfL',
                baseUrl: BASE_URL
            })
        }
    })(document,"script");
</script>

<div>

    <div id="container">
        @yield('content')
    </div>

</div>

<script type="text/javascript" src="{{ mix('js/livestream.js') }}"></script>
@yield('script')
</body>
</html>
