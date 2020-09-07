@extends('layouts/front')

@section('title')
    {{ $event->name }}
@endsection

@section('content')

    <section>

        <div class="row">

            <div class="col-md-12">
                <div class="col-md-3 hero-small-date text-center">
                    <h3>{{ $event->startDate->format('d') }}</h3>
                    <h4>{{ $event->startDate->formatLocalized('%B %Y') }}</h4>
                </div>
                <div class="col-md-9 hero-small-date-content">
                    <h1 class="banner-title">
                        <a href="{{ $event->getUrl() }}">{{ $event->name }}</a>
                    </h1>
                    <h2 class="banner-subtitle">
                        @if($event->venue)

                            {{ $event->startDate->format('H:i') }} -
                            {{ $event->venue->getShortLocation() }}

                        @endif
                    </h2>
                </div>
            </div>

        </div><br />

        <div class="row">

            <div class="col-md-12">

                @yield('register-content')

            </div>

        </div>

    </section>


@endsection