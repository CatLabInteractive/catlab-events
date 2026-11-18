@extends('layouts/front')

@section('title')
    {{ $event->name }}
@endsection

@section('bodyClass')kv-page {{ $event->getColourClass() }}@endsection

@section('banner')
    <?php
        $_eventDates = isset($ticketCategory) ? $ticketCategory->eventDates : $event->eventDates;
        $_facts = [];
        if ($event->isPackage()) {
            $_facts[] = 'Quizpakket, speel wanneer je wil';
        } else {
            foreach ($_eventDates as $_eventDate) {
                if ($_eventDate->startDate) {
                    $_facts[] = ucfirst($_eventDate->startDate->translatedFormat('l j F Y, H:i'));
                }
            }
        }
        if ($event->venue) {
            $_facts[] = $event->venue->getShortLocation();
        }
    ?>
    <div class="kv-pagehead kv-pagehead-event">
        <div class="kv-wrap">
            @if($event->series)
                <p class="kv-series"><i aria-hidden="true"></i>{{ $event->series->name }}</p>
            @endif
            <p class="kv-pagehead-title"><a href="{{ $event->getUrl() }}">{{ $event->name }}</a></p>
            @if(count($_facts) > 0)
                <p class="kv-facts">@foreach($_facts as $_fact)<span>{{ $_fact }}</span>@endforeach</p>
            @endif
        </div>
    </div>
@endsection

@section('content')
    <section class="kv-sec kv-sec-form">
        <div class="kv-wrap">
            @yield('register-content')
        </div>
    </section>
@endsection
