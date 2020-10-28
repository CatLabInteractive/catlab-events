@extends('layouts/livestream')

@section('title')
    {{$livestream->title}}
@endsection

@section('content')

    @if(!$embed)
        <h1>{{ $livestream->title }}</h1>
    @endif

    <p>{{ __('livestreams.notStarted') }}</p>

    @include('livestream.footer')

@endsection

@section('script')
    <script type="text/javascript">
        startLiveStreamPoller('{{ $poll }}');
    </script>
@endsection
