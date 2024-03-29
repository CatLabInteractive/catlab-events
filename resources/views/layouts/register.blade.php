@extends('layouts/front')

@section('title')
    {{ $event->name }}
@endsection

@section('content')

    <section>

        <div class="row">

            <div class="col-md-12">
                @if($event->eventDates->count() > 0)

                    <?php $_eventDates = isset($ticketCategory) ? $ticketCategory->eventDates : $event->eventDates; ?>

                    @if(count($_eventDates) === 1)
                        <div class="col-md-3 hero-small-date text-center">
                            <h3>{{ $_eventDates[0]->startDate->format('d') }}</h3>
                            <h4>{{ $_eventDates[0]->startDate->formatLocalized('%B %Y') }}</h4>
                        </div>
                        <div class="col-md-9 hero-small-date-content">
                    @else
                        @foreach($_eventDates as $eventDate)
                            <div class="col-md-{{ ceil(12 / count($_eventDates)) }} hero-small-date text-center">
                                <h3>{{ $eventDate->startDate->format('d') }}</h3>
                                <h4>{{ $eventDate->startDate->formatLocalized('%B %Y') }}</h4>
                            </div>
                        @endforeach
                        <div class="col-md-12 hero-small-date-content">
                    @endif

                @else
                    <div class="col-md-12 hero-small-date-content">
                @endif

                    <h1 class="banner-title">
                        <a href="{{ $event->getUrl() }}">{{ $event->name }}</a>
                    </h1>
                    <h2 class="banner-subtitle">
                        @if($event->venue)

                            @if(count($_eventDates) === 1)
                                {{ $event->startDate->format('H:i') }} -
                            @endif

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
