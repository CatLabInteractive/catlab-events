@extends('layouts/livestream')

@section('title')
    {{$livestream->title}}
@endsection

@section('content')

    <h1>{{ $livestream->title }}</h1>
    <p>Dit evenement is nog niet begonnen.</p>

    @include('livestream.footer')

@endsection

@section('script')
    <script type="text/javascript">
        startLiveStreamPoller('{{ $poll }}');
    </script>
@endsection
