@extends('layouts/livestream')

@section('title')
    {{$livestream->title}}
@endsection

@section('content')

    @if(!$embed)
        <h1>{{ $livestream->title }}</h1>
    @endif
    <div class="twitch">
        <div class="waiting @if($hasChat) with-chat @endif ">
            <p>{{ __('livestreams.notStarted') }}</p>
        </div>

        @if($hasChat)
            <div class="twitch-chat">

                @if(!$user)
                    <p class="login-prompt">
                        {!! __('livestreams.loginToChat', [ 'action' => '<a href="'.$loginUrl.'">' . __('livestreams.loginAction') . '</a>' ]) !!}
                    </p>
                @endif

                @if($rocketChatUrl)
                    <iframe
                            frameborder="0"
                            scrolling="no"
                            src="{{$rocketChatUrl}}"
                            height="100%"
                            width="100%"
                            id="rocketChatIframe"
                    >
                    </iframe>


                @endif
            </div>
        @endif
    </div>

    @include('livestream.footer')

@endsection

@section('script')
    <script type="text/javascript">
        startLiveStreamPoller('{{ $poll }}');
    </script>
@endsection
