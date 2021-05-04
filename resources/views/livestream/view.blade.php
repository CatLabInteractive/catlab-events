@extends('layouts/livestream')

@section('title')
    {{$livestream->title}}
@endsection

@section('content')

    @if(!$embed)
        <h1>{{ $livestream->title }}</h1>
    @endif

    <div class="twitch">
        <div class="twitch-video @if($hasChat) with-chat @endif ">

            @if($livestream->twitch_key)
                <script src= "https://player.twitch.tv/js/embed/v1.js"></script>
                <div id="twitchPlayer"></div>
                <script type="text/javascript">
                    var options = {
                        width: '100%',
                        height: '100%',
                        channel: "{{$livestream->twitch_key}}",
                        parent: [ '{{request()->getHost()}}' ]
                    };
                    var player = new Twitch.Player("twitchPlayer", options);
                    player.setVolume(1);

                    setInterval(function() {
                        // if latency gets above 7 seconds, 'soft refresh' the player.
                        var latency = player.getPlaybackStats().hlsLatencyBroadcaster;

                        // no latency? Ignore.
                        if (typeof(latency) === 'undefined') {
                            return;
                        }

                        /*
                        try {
                            dataLayer.push({
                                event: 'livestream.measure',
                                latency: latency
                            });
                        } catch (e) {
                            console.log(e)
                        }
                         */

                        if (latency > {{ config('livestream.maxHlsLatencyBroadcaster', 10) }}) {
                            player.pause();
                            player.play();

                            console.log('Player is drifting too far from the livestream; soft refresh.');
                            /*
                            try {
                                dataLayer.push({
                                    event: 'livestream.resync',
                                    latency: latency
                                });
                            } catch (e) {
                                console.log(e)
                            }*/
                        }

                    }, 30000);
                </script>

            @elseif ($livestream->getYouTubeUrl())
                <iframe
                        width="100%" height="100%"
                        src="<?php echo $livestream->getYouTubeUrl(); ?>"
                        title="YouTube video player"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                ></iframe>
            @endif
        </div>

        @if(false)
        <div class="twitch-chat">
            <iframe
                    frameborder="0"
                    scrolling="no"
                    src="https://www.twitch.tv/embed/catlab/chat?parent={{request()->getHost()}}"
                    height="100%"
                    width="100%">
            </iframe>
        </div>
        @endif

        @if($hasChat)
            <div class="twitch-chat">
                @include('livestream.blocks.chat')
            </div>
        @endif

    </div>

    @include('livestream.footer')

@endsection

@section('script')

@endsection
