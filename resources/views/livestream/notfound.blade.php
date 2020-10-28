@extends('layouts/livestream')

@section('title')
    Livestream
@endsection

@section('content')


    <p>{{ __('livestreams.notFound') }}</p>
    <p>{{ __('livestreams.clickEmailLink') }}</p>

    @include('livestream.footer')

@endsection
