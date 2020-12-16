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
                @include('livestream.blocks.chat')
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
